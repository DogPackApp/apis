<?php

namespace App\Services\Merchant;

use App\Models\Product\Item;
use App\Services\Store\StoreShippingCountry;
use App\Services\SubscribeSave\SubscribeSaveEligibilityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use XMLWriter;

/**
 * Google Merchant Center promotions feed for Subscribe & Save.
 *
 * One promotion per distinct first-order percent (seller % + DogPack %), per country.
 * Products are linked to their promotion by g:promotion_id in the product feed, which
 * GoogleMerchantFeedService fills in through promotionIdFor(), so both files always
 * agree on the id. Eligibility and percents come from SubscribeSaveEligibilityService.
 *
 * Spec: https://support.google.com/merchants/answer/2906014
 */
class GoogleMerchantPromotionService
{
    // Storefront page that lists Subscribe & Save products. Confirm against the frontend route.
    private const PROMOTION_PATH = '/marketplace/subscribe-and-save';

    // Rolling window, refreshed by the daily job. Google's hard limit is 183 days.
    private const PROMOTION_DAYS = 30;

    private const TIMEZONE = 'America/Toronto';

    private const COUNTRY_FILE_MAP = [
        MarketplaceFeedConfig::COUNTRY_CANADA => 'feeds/google-promotions.xml',
        MarketplaceFeedConfig::COUNTRY_USA => 'feeds/google-promotions-us.xml',
        MarketplaceFeedConfig::COUNTRY_UK => 'feeds/google-promotions-uk.xml',
    ];

    public function __construct(
        private SubscribeSaveEligibilityService $eligibility,
        private StoreShippingCountry $storeShippingCountry
    ) {}

    /**
     * The id a product carries in the product feed, null when it has no Subscribe & Save offer.
     * Letters and digits only, as Google requires. Example: CASS20.
     *
     * @param  object  $item  needs id, store_id and subscribe_save_available
     */
    public function promotionIdFor($item, string $countryCode): ?string
    {
        $percent = $this->firstOrderPercent(
            (int) $item->id,
            (int) $item->store_id,
            (bool) $item->subscribe_save_available
        );

        return $percent === null ? null : $countryCode.'SS'.$percent;
    }

    /**
     * Streams the promotions file for one country: chunk the items, append each new
     * percent to the temp file as soon as it is seen, then upload (unless docker-local).
     *
     * @return array{success: bool, message: string, count: int, url?: string, path?: string}
     */
    public function generate(int $countryId = MarketplaceFeedConfig::COUNTRY_CANADA): array
    {
        $countryCode = MarketplaceFeedConfig::COUNTRY_CODES[$countryId] ?? null;
        if ($countryCode === null) {
            return ['success' => false, 'message' => "Unsupported country id: {$countryId}", 'count' => 0];
        }

        $storeIds = $this->storeShippingCountry->getStoreIdsByCountry([$countryId]);
        if (empty($storeIds)) {
            return ['success' => false, 'message' => 'No stores found for this country', 'count' => 0];
        }

        $isLocal = config('app.env') === 'docker-local';
        $s3Path = self::COUNTRY_FILE_MAP[$countryId];
        $tmpFile = $this->tempFilePath($countryCode);

        try {
            $count = $this->buildFileChunked($storeIds, $countryId, $countryCode, $tmpFile);

            // Commented out to allow the file to be generated even if there are no Subscribe & Save products in case of non active promotions
            // if ($count === 0) {
            //     @unlink($tmpFile);
            //     return ['success' => false, 'message' => 'No Subscribe & Save products found', 'count' => 0];
            // }

            if ($isLocal) {
                return [
                    'success' => true,
                    'message' => "Promotions file generated with {$count} promotions (kept locally, not uploaded)",
                    'count' => $count,
                    'path' => $tmpFile,
                ];
            }

            $this->uploadToS3($tmpFile, $s3Path);

            return [
                'success' => true,
                'message' => "Promotions feed generated with {$count} promotions",
                'count' => $count,
                'url' => config('services.marketplace_media').$s3Path,
            ];
        } finally {
            if (! $isLocal) {
                @unlink($tmpFile);
            }
        }
    }

    /**
     * @param  list<int>  $storeIds
     * @return int Number of promotions written
     */
    private function buildFileChunked(array $storeIds, int $countryId, string $countryCode, string $tmpFile): int
    {
        $xml = new XMLWriter;
        $xml->openURI($tmpFile);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->setIndent(true);
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:g', MarketplaceFeedConfig::GOOGLE_NS);
        $xml->startElement('channel');
        $xml->writeElement('title', 'DogPack Marketplace Promotions');
        $xml->writeElement('link', MarketplaceFeedConfig::SITE_URL);
        $xml->writeElement('description', 'DogPack Marketplace Subscribe & Save Promotions');

        $excluded = array_fill_keys(GoogleFeedExcludedProductIds::IDS, true);
        $seenPercents = [];
        $lastId = 0;

        while (true) {
            $items = $this->buildItemQuery($storeIds, $lastId);

            if ($items->isEmpty()) {
                break;
            }

            // Two queries for the whole batch instead of one per item on a cold cache.
            $this->eligibility->warmOfferCaches(
                $items->pluck('id')->all(),
                $items->pluck('store_id')->all()
            );

            foreach ($items as $item) {
                if (isset($excluded[$countryCode.'-'.$item->uuid])) {
                    continue;
                }

                $percent = $this->firstOrderPercent((int) $item->id, (int) $item->store_id, true);
                if ($percent === null || isset($seenPercents[$percent])) {
                    continue;
                }

                $seenPercents[$percent] = true;
                $this->writePromotion($xml, $percent, $countryId, $countryCode);
                $xml->flush();
            }

            $lastId = $items->last()->id;
            unset($items);
        }

        $xml->endElement(); // channel
        $xml->endElement(); // rss
        $xml->endDocument();
        $xml->flush();

        return count($seenPercents);
    }

    /**
     * Same product rules as the product feed (active, priced, store ships to the country),
     * narrowed to items flagged for Subscribe & Save.
     *
     * @param  list<int>  $storeIds
     */
    private function buildItemQuery(array $storeIds, int $lastId)
    {
        return Item::on('mysql_reader')
            ->select('items.id', 'items.uuid', 'items.store_id')
            ->join('item_prices', 'items.id', '=', 'item_prices.item_id')
            ->where('item_prices.domestic_price', '>', 0)
            ->whereIn('items.store_id', $storeIds)
            ->where('items.status', 1)
            ->where('items.subscribe_save_available', 1)
            ->where('items.id', '>', $lastId)
            ->orderBy('items.id')
            ->limit(MarketplaceFeedConfig::BATCH_SIZE)
            ->get();
    }

    /**
     * First-order percent (seller + DogPack) for a product, null when there is none to advertise.
     */
    private function firstOrderPercent(int $itemId, int $storeId, bool $available): ?int
    {
        $offer = $this->eligibility->offerFor($itemId, $storeId, $available);
        $percent = (int) ($offer['first_order_discount_percent'] ?? 0);

        // percent_off must be a whole number between 1 and 99.
        return ($percent >= 1 && $percent <= 99) ? $percent : null;
    }

    private function writePromotion(XMLWriter $xml, int $percent, int $countryId, string $countryCode): void
    {
        $currency = MarketplaceFeedConfig::CURRENCIES[$countryId] ?? MarketplaceFeedConfig::DEFAULT_CURRENCY;

        // Only set when the country caps the first-order discount for this percent.
        $cap = $this->eligibility->firstOrderCap(['first_order_discount_percent' => $percent], $countryId);

        $finePrint = 'Applies to your first Subscribe & Save order only. '
            .'Later deliveries use the Subscribe & Save rate shown on the product page. '
            .'Cancel or change your subscription at any time.';
        if ($cap !== null) {
            $finePrint .= ' Maximum first-order discount of '
                .number_format($cap['max_discount_amount'], 2, '.', '')." {$currency} per order line.";
        }

        $xml->startElement('item');
        $this->writeGElement($xml, 'promotion_id', $countryCode.'SS'.$percent);
        $this->writeGElement($xml, 'product_applicability', 'specific_products');
        $this->writeGElement($xml, 'offer_type', 'no_code');
        $this->writeGElement($xml, 'long_title', "Save {$percent}% on your first Subscribe & Save order");
        $this->writeGElement($xml, 'promotion_effective_dates', $this->effectiveDates());
        $this->writeGElement($xml, 'redemption_channel', 'online');
        // Repeated field: one element per destination.
        $this->writeGElement($xml, 'promotion_destination', 'shopping_ads');
        $this->writeGElement($xml, 'promotion_destination', 'free_listings');
        $this->writeGElement($xml, 'promotion_url', $this->promotionUrl($countryCode));
        $this->writeGElement($xml, 'coupon_value_type', 'percent_off');
        $this->writeGElement($xml, 'percent_off', (string) $percent);
        $this->writeGElement($xml, 'redemption_restriction', 'subscribe_and_save');
        if ($cap !== null) {
            $this->writeGElement($xml, 'max_discount_amount', number_format($cap['max_discount_amount'], 2, '.', '').' '.$currency);
        }
        $this->writeGElement($xml, 'fine_print', $finePrint);
        $this->writeGElement($xml, 'description', "Subscribe & Save first-order discount of {$percent}% (seller plus DogPack).");
        $xml->endElement(); // item
    }

    private function effectiveDates(): string
    {
        $start = Carbon::now(self::TIMEZONE)->startOfDay();
        $end = $start->copy()->addDays(self::PROMOTION_DAYS);

        return $start->format('Y-m-d\TH:i:sP').'/'.$end->format('Y-m-d\TH:i:sP');
    }

    private function promotionUrl(string $countryCode): string
    {
        $localePrefix = MarketplaceFeedConfig::LOCALE_PREFIXES[$countryCode] ?? '';

        return MarketplaceFeedConfig::SITE_URL.$localePrefix.self::PROMOTION_PATH;
    }

    /**
     * storage/app/temp is inside the project, so a local run can be opened in the IDE.
     */
    private function tempFilePath(string $countryCode): string
    {
        $dir = storage_path('app/temp');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir.'/google-promotions-'.strtolower($countryCode).'.xml';
    }

    private function uploadToS3(string $tmpFile, string $s3Path): void
    {
        $stream = fopen($tmpFile, 'r');

        try {
            Storage::disk('mps3')->put($s3Path, $stream, [
                'ContentType' => 'application/xml',
                'CacheControl' => 'no-cache, max-age=0',
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        Storage::disk('mps3')->setVisibility($s3Path, 'public');
    }

    private function writeGElement(XMLWriter $xml, string $tag, string $value): void
    {
        $xml->startElementNS('g', $tag, null);
        $xml->text($value);
        $xml->endElement();
    }
}
