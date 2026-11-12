<?php

namespace App\Services\Merchant;

use App\Models\Product\Item;
use App\Models\Store\ShippingZoneRate;
use App\Services\Order\CountryService;
use App\Services\Order\CurrencyService;
use App\Services\Order\PriceCalculator;
use App\Services\Product\CategoryService;
use App\Services\Store\StoreShippingCountry;
use App\Services\SubscribeSave\SubscribeSaveEligibilityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use XMLWriter;

class GoogleMerchantFeedService
{
    private const DESCRIPTION_LIMIT = 5000;

    private const SHIPPING_LABEL_FREE = 'free_shipping';

    private const SHIPPING_LABEL_PAID = 'paid_shipping';

    private const SHIPPING_LABEL_UNKNOWN = 'shipping_unknown';

    private const COUNTRY_FILE_MAP = [
        MarketplaceFeedConfig::COUNTRY_CANADA => 'feeds/google-products.xml',
        MarketplaceFeedConfig::COUNTRY_USA => 'feeds/google-products-us.xml',
        MarketplaceFeedConfig::COUNTRY_UK => 'feeds/google-products-uk.xml',
    ];

    private const AWIN_COUNTRY_FILE_MAP = [
        MarketplaceFeedConfig::COUNTRY_CANADA => 'feeds/awin-products.csv',
        MarketplaceFeedConfig::COUNTRY_USA => 'feeds/awin-products-us.csv',
        MarketplaceFeedConfig::COUNTRY_UK => 'feeds/awin-products-uk.csv',
    ];

    /** @var list<string> */
    private const AWIN_CSV_HEADERS = [
        'product_id',
        'product_name',
        'price',
        'deep_link',
        'image_url',
        'description',
        'currency',
        'in_stock',
        'merchant_category',
        'brand_name',
        'ean',
        'delivery_cost',
        'condition',
        'language',
    ];

    private const GOOGLE_PRODUCT_CATEGORY_MAP = [
        // Old root categories (legacy)
        1 => 3367,    // Cat Food
        2 => 5,       // Pet Food & Treats → Dog Supplies
        3 => 5010,    // Pet Toys → Dog Toys
        4 => 4434,    // Pet Furniture → Dog Beds
        5 => 6250,    // Pet Collars → Pet Collars & Harnesses
        6 => 3530,    // Dry Food → Dog Food
        7 => 6250,    // Pet Harnesses → Pet Collars & Harnesses
        8 => 2,       // Pet Accessories → Pet Supplies

        // Dog Supplies
        75 => 5,      // Dog supplies (generic)
        76 => 3530,   // Food → Dog Food
        77 => 3530,   // Dry Food
        78 => 3530,   // Wet Food
        79 => 3530,   // Raw & Freeze-Dried Food
        80 => 3530,   // Puppy Food
        81 => 3530,   // Food Toppers & Mixers
        82 => 5011,   // Treats → Dog Treats
        83 => 5011,   // Bones & Bully sticks
        84 => 5011,   // Soft & Chewy Treats
        85 => 5011,   // Biscuits & Cookies
        86 => 5011,   // Dental Treats
        87 => 5011,   // Training Treats
        88 => 5010,   // Toys → Dog Toys
        89 => 5010,   // Chew Toys
        90 => 5010,   // Interactive Toys
        91 => 5010,   // Plush Toys
        92 => 5010,   // Rope & Tug Toys
        93 => 5010,   // Fetch Toys
        94 => 5081,   // Health & Wellness → Pet Vitamins & Supplements
        95 => 5081,   // Vitamins & Supplements
        96 => 6248,   // Flea & Tick → Pet Flea & Tick Control
        97 => 5081,   // Dental Care
        98 => 5081,   // Dewormers
        99 => 5081,   // First Aid & Recovery
        100 => 6250,  // Collars, Leashes & Harnesses → Pet Collars & Harnesses
        101 => 6250,  // Collars
        102 => 6249,  // Leashes → Pet Leashes
        103 => 6250,  // Harnesses
        104 => 5093,  // ID Tags → Pet ID Tags
        105 => 4434,  // Beds & Furniture → Dog Beds
        106 => 4434,  // Orthopedic Beds
        107 => 6973,  // Ramps & Steps → Pet Steps & Ramps
        108 => 4434,  // Elevated beds
        109 => 4434,  // Pillow beds
        110 => 4434,  // Blankets & Throws
        111 => 7274,  // Crates, Gates & Pens → Dog Kennels & Runs
        112 => 7274,  // Crates & Kennels
        113 => 7428,  // Crate Covers & Mats → Dog Kennel & Run Accessories
        114 => 7274,  // Pens & Accessories
        115 => 542,   // Doors & Gates → Baby & Pet Gates
        116 => 7274,  // Fence Systems
        117 => 6383,  // Grooming → Pet Grooming Supplies
        118 => 6406,  // Shampoos & Conditioners → Pet Shampoo & Conditioner
        119 => 6385,  // Brushes & Combs → Pet Combs & Brushes
        120 => 6384,  // Hair Clippers & Trimmers
        121 => 6383,  // Ear & Eye Care
        122 => 5004,  // Apparel & Accessories → Dog Apparel
        123 => 5004,  // Coats & Jackets
        124 => 5004,  // Sweaters & Hoodies
        125 => 5004,  // Costumes
        126 => 5004,  // T-Shirts
        127 => 5004,  // Boots & Shoes
        128 => 5004,  // Life Jackets & Swim Suits
        129 => 5004,  // Bandanas, Bows & Hats
        130 => 505314, // Training & Behavior → Pet Training Aids
        131 => 505314, // Training Collars
        132 => 6846,  // Potty Training & Repellents → Pet Training Pads
        133 => 505313, // Clickers & Whistles → Pet Training Clickers
        134 => 505314, // Bark Control
        135 => 6252,  // Bowls & Feeders → Pet Bowls, Feeders & Waterers
        136 => 6252,  // Food Bowls
        137 => 6252,  // Water Dispensers
        138 => 6252,  // Elevated Bowls
        139 => 6252,  // Lick & Snuffle Mats
        140 => 5162,  // Storage & Scoops → Pet Food Containers
        141 => 6252,  // Place Mats
        142 => 6252,  // Slow Feeders
        143 => 8070,  // Cleaning Supplies → Pet Waste Bags
        144 => 505297, // Stain & Odour Removers → Pet Waste Disposal
        145 => 7372,  // Pee Pads & Diapers → Dog Diaper Pads & Liners
        146 => 8070,  // Poop Bags & Scoopers → Pet Waste Bags
        147 => 505297, // Pet Hair Removers
        149 => 6251,  // Carriers & Travel → Pet Carriers & Crates
        150 => 6251,  // Carriers
        151 => 6276,  // Strollers & Bicycle Baskets → Pet Strollers
        152 => 6251,  // Car Accessories
        153 => 6252,  // Travel Bowls → Pet Bowls
        303 => 6983,  // Agility Equipment → Pet Agility Equipment
        309 => 5011,  // Long-Lasting Chews → Dog Treats
        310 => 6250,  // Health Tracker → Pet Collars & Harnesses
        311 => 6250,  // GPS Trackers
        312 => 5081,  // Topical Supplements
        313 => 500084, // Dog Bed Sheets → Pet Bed Accessories

        // Cat Supplies
        154 => 4,     // Cat supplies (generic)
        155 => 3367,  // Food & Treats → Cat Food
        156 => 3367,  // Dry Food
        157 => 3367,  // Wet Food
        158 => 3367,  // Freeze-Dried & Raw
        159 => 5002,  // Catnip & Grass → Cat Treats
        160 => 3367,  // Food Toppers
        161 => 5002,  // Treats → Cat Treats
        162 => 4999,  // Litter & Accessories → Cat Litter
        163 => 4999,  // Litter
        164 => 5000,  // Litter Boxes → Cat Litter Boxes
        165 => 7142,  // Litter Mats → Cat Litter Box Mats
        166 => 4999,  // Deodorizers & Filters
        167 => 505297, // Waste Disposal
        168 => 5001,  // Toys → Cat Toys
        169 => 5001,  // Teaser & Wands
        170 => 5001,  // Catnip Toys
        171 => 5001,  // Interactive & Electronic Toys
        172 => 5001,  // Tunnels, Tents & Sacks
        173 => 5001,  // Plush & Mice Toys
        174 => 5081,  // Health & Wellness → Pet Vitamins & Supplements
        175 => 6248,  // Flea & Tick → Pet Flea & Tick Control
        176 => 5081,  // Hairball Remedies
        177 => 5081,  // Vitamins & Supplements
        178 => 5081,  // First Aid & Recovery
        179 => 5081,  // Milk Replacers & Nursing Kits
        180 => 5081,  // Dental Care
        181 => 4433,  // Beds & Furniture → Cat Beds
        182 => 4433,  // Covered Beds
        183 => 4433,  // Pillow Beds
        184 => 4433,  // Window Perches
        185 => 4433,  // Heated Beds
        186 => 6383,  // Grooming → Pet Grooming Supplies
        187 => 6385,  // Brushes & Combs
        188 => 6406,  // Shampoos & Conditioners
        189 => 7319,  // Nail Clippers → Pet Nail Tools
        190 => 4997,  // Trees, Scratchers & Condos → Cat Furniture
        191 => 4997,  // Scratching Posts
        192 => 4997,  // Trees & Condos
        193 => 4997,  // Wall Shelves
        194 => 505297, // Cleaning Supplies
        195 => 505297, // Stain & Odour Removers
        196 => 505297, // Pet Hair Removers
        197 => 505297, // Repellents
        198 => 5082,  // Apparel & Accessories → Cat Apparel
        199 => 6250,  // Collars with Bells → Pet Collars & Harnesses
        200 => 5082,  // Costumes
        201 => 5082,  // GPS Trackers
        202 => 6251,  // Carriers & Travel → Pet Carriers & Crates
        203 => 6251,  // Car Accessories
        204 => 6251,  // Backpacks & Carriers
        205 => 6251,  // Kennels
        206 => 6251,  // Travel Litter Boxes

        // Other Animals
        207 => 2,     // Other animals (generic Pet Supplies)
        208 => 6,     // Fish & Aquatic Pets → Fish Supplies
        209 => 3238,  // Tanks & Aquariums → Aquariums
        210 => 5024,  // Fish Food
        211 => 5019,  // Tank Decor → Aquarium Decor
        212 => 5020,  // Filters & Media → Aquarium Filters
        213 => 6,     // Tank Supplies → Fish Supplies
        214 => 3,     // Birds → Bird Supplies
        215 => 4989,  // Cages & Stands → Bird Cages & Stands
        216 => 4990,  // Food & Treats → Bird Food
        217 => 3,     // Litter & Nesting → Bird Supplies
        218 => 4992,  // Toys, Perches & Accessories → Bird Toys
        219 => 3257,  // Horses → Horse Care
        220 => 7482,  // Feed & Treats → Horse Feed
        221 => 5025,  // Grooming Supplies → Horse Grooming
        222 => 7459,  // Health & Supplements → Horse Vitamins & Supplements
        223 => 3257,  // Stable Supplies → Horse Care
        224 => 5593,  // Riding Gear → Horse Tack
        225 => 5013,  // Small animals → Small Animal Supplies
        226 => 5013,  // Rabbit
        227 => 5015,  // Rabbit Food & Treats → Small Animal Food
        228 => 5014,  // Rabbit Bedding → Small Animal Bedding
        229 => 5013,  // Rabbit Toys
        230 => 5016,  // Rabbit Beds & Hideouts → Small Animal Habitat Accessories
        231 => 5017,  // Rabbit Cages & Habitats → Small Animal Habitats & Cages
        232 => 5013,  // Rabbit Grooming & Cleaning
        233 => 5017,  // Rabbit Hutches & Playpens
        234 => 5013,  // Guinea Pig
        235 => 5015,  // Guinea Pig Food & Treats
        236 => 5014,  // Guinea Pig Bedding
        237 => 5013,  // Guinea Pig Toys
        238 => 5016,  // Guinea Pig Beds & Hideouts
        239 => 5017,  // Guinea Pig Cages & Habitats
        240 => 5013,  // Guinea Pig Grooming & Cleaning
        241 => 5017,  // Guinea Pig Hutches & Playpens
        242 => 5013,  // Hamster & Gerbil
        243 => 5015,  // Hamster Food & Treats
        244 => 5014,  // Hamster Bedding
        245 => 5013,  // Hamster Toys
        246 => 5016,  // Hamster Beds & Hideouts
        247 => 5017,  // Hamster Cages & Habitats
        248 => 5013,  // Hamster Grooming & Cleaning
        249 => 5017,  // Hamster Hutches & Playpens
        250 => 5013,  // Ferret
        251 => 5015,  // Ferret Food & Treats
        252 => 5014,  // Ferret Bedding
        253 => 5013,  // Ferret Toys & Tunnels
        254 => 6252,  // Ferret Water Bottles & Feeders → Pet Bowls
        255 => 5016,  // Ferret Hammocks, Beds & Hideouts
        256 => 5013,  // Ferret Grooming & Cleaning
        257 => 5017,  // Ferret Cages & Habitats
        258 => 7,     // Reptiles & Amphibians
        259 => 5026,  // Live Feed → Reptile & Amphibian Food
        260 => 5029,  // Terrariums → Reptile & Amphibian Habitats
        261 => 5026,  // Food & Treats → Reptile & Amphibian Food
        262 => 5028,  // Heating & Lighting
        314 => 4990,  // Quail Eggs → Bird Food

        // Pet Lover Merchandise
        263 => 1604,  // Pet Lover Merchandise → Clothing
        264 => 1604,  // Apparel → Clothing
        265 => 1604,  // T-Shirts
        266 => 1604,  // Hoodies & Sweatshirts
        267 => 1604,  // Hats & Caps
        268 => 209,   // Socks
        269 => 2580,  // Pajamas
        270 => 175,   // Accessories → Keychains
        271 => 3032,  // Bags → Handbags
        272 => 2353,  // Phone Cases → Mobile Phone Cases
        273 => 175,   // Keychains
        274 => 638,   // Home & Kitchen → Kitchen & Dining
        275 => 2169,  // Mugs
        276 => 2951,  // Tumblers & Bottles → Tumblers
        277 => 4454,  // Pillows & Blankets → Throw Pillows
        278 => 500044, // Wall Art → Posters, Prints, & Visual Artwork
        279 => 188,   // Jewelry & Keepsakes → Jewelry
        280 => 196,   // Necklaces
        281 => 191,   // Bracelets
        282 => 200,   // Rings
        283 => 188,   // Engraved Tags → Jewelry
        304 => 784,   // Books & Magazines → Books
        305 => 638,   // Kitchen Accessories → Kitchen & Dining
        306 => 696,   // Flags → Decor
        307 => 598,   // Doormats & Rugs → Rugs
        308 => 696,   // Gifts & Cards → Decor

        // Digital Products
        284 => 500044, // Digital products → Posters, Prints, & Visual Artwork
        285 => 500044, // Art & Decor
        286 => 500044, // Printable Wall Art
        287 => 500044, // Custom Digital Pet Portraits
        288 => 4054,  // Printable Stickers → Decorative Stickers
        289 => 784,   // Training & Education → Books
        290 => 784,   // Training eBooks
        291 => 784,   // Training Video Courses
        292 => 784,   // Care & Behavior Guides
        293 => 5093,  // ID & Safety → Pet ID Tags
        294 => 5093,  // Digital Tags (QR Code)
        295 => 5093,  // Emergency Pet Info Cards
        296 => 784,   // Stationery & Templates → Books
        297 => 784,   // Printable Calendars
        298 => 784,   // Pet Routine Trackers
        299 => 784,   // Adoption Certificates
        300 => 500044, // Personalized Gifts
        301 => 500044, // Name-based Posters
        302 => 500044, // Digital Greeting Cards
    ];

    private array $categoryLabelCache = [];

    private array $storeShippingCache = [];

    private array $policyCache = [];

    private array $storeDefaultPolicyCache = [];

    private $allCategories;

    private string $targetCurrency;

    private string $s3Path;

    private string $countryCode;

    private StoreShippingCountry $storeShippingCountry;

    private PriceCalculator $priceCalculator;

    private CurrencyService $currencyService;

    private CountryService $countryService;

    private GoogleMerchantPromotionService $promotionService;

    private SubscribeSaveEligibilityService $subscribeSave;

    public function __construct(
        StoreShippingCountry $storeShippingCountry,
        PriceCalculator $priceCalculator,
        CurrencyService $currencyService,
        CountryService $countryService,
        GoogleMerchantPromotionService $promotionService,
        SubscribeSaveEligibilityService $subscribeSave
    ) {
        $this->promotionService = $promotionService;
        $this->subscribeSave = $subscribeSave;
        $this->storeShippingCountry = $storeShippingCountry;
        $this->priceCalculator = $priceCalculator;
        $this->currencyService = $currencyService;
        $this->countryService = $countryService;
    }

    public function generate(int $countryId = MarketplaceFeedConfig::COUNTRY_CANADA, int $limit = 0, bool $saveLocally = false): array
    {
        $boot = $this->bootstrapFeed($countryId);
        if ($boot === null) {
            return ['success' => false, 'message' => 'No stores found for this country', 'count' => 0];
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'gfeed_');
        $counts = $this->buildFeedsChunked($boot, $tmpFile, null, $limit, true, false);

        if ($counts['google'] === 0) {
            @unlink($tmpFile);

            return ['success' => false, 'message' => 'No active products found', 'count' => 0];
        }

        $this->s3Path = self::COUNTRY_FILE_MAP[$countryId] ?? 'feeds/google-products.xml';

        if ($saveLocally) {
            // Debug mode: write to storage/app/<feed path> and skip the S3 upload.
            $localPath = storage_path('app/'.$this->s3Path);
            if (! is_dir(dirname($localPath))) {
                mkdir(dirname($localPath), 0775, true);
            }
            copy($tmpFile, $localPath);
            @unlink($tmpFile);

            return [
                'success' => true,
                'message' => "Feed generated locally with {$counts['google']} products",
                'count' => $counts['google'],
                'url' => $localPath,
            ];
        }

        $this->uploadToS3($tmpFile);
        @unlink($tmpFile);

        $feedUrl = config('services.marketplace_media').$this->s3Path;

        return [
            'success' => true,
            'message' => "Feed generated with {$counts['google']} products",
            'count' => $counts['google'],
            'url' => $feedUrl,
        ];
    }

    public function generateAwin(int $countryId = MarketplaceFeedConfig::COUNTRY_CANADA, int $limit = 0): array
    {
        $boot = $this->bootstrapFeed($countryId);
        if ($boot === null) {
            return ['success' => false, 'message' => 'No stores found for this country', 'count' => 0];
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'awinfeed_');
        $counts = $this->buildFeedsChunked($boot, null, $tmpFile, $limit, false, true);

        if ($counts['awin'] === 0) {
            @unlink($tmpFile);

            return ['success' => false, 'message' => 'No active products found', 'count' => 0];
        }

        $this->s3Path = self::AWIN_COUNTRY_FILE_MAP[$countryId] ?? 'feeds/awin-products.csv';
        $this->uploadToS3($tmpFile, 'text/csv');
        @unlink($tmpFile);

        $feedUrl = config('services.marketplace_media').$this->s3Path;

        return [
            'success' => true,
            'message' => "Awin feed generated with {$counts['awin']} products",
            'count' => $counts['awin'],
            'url' => $feedUrl,
        ];
    }

    /**
     * One DB pass: same product batches feed Google XML and Awin CSV uploads.
     *
     * @return array{success: bool, google: array{success: bool, message: string, count: int, url?: string}, awin: array{success: bool, message: string, count: int, url?: string}}
     */
    public function generateGoogleAndAwin(int $countryId = MarketplaceFeedConfig::COUNTRY_CANADA, int $limit = 0): array
    {
        $boot = $this->bootstrapFeed($countryId);
        if ($boot === null) {
            $empty = ['success' => false, 'message' => 'No stores found for this country', 'count' => 0];

            return ['success' => false, 'google' => $empty, 'awin' => $empty];
        }

        $xmlTmp = tempnam(sys_get_temp_dir(), 'gfeed_');
        $csvTmp = tempnam(sys_get_temp_dir(), 'awinfeed_');
        $counts = $this->buildFeedsChunked($boot, $xmlTmp, $csvTmp, $limit, true, true);

        $mediaBase = config('services.marketplace_media');
        $googlePath = self::COUNTRY_FILE_MAP[$countryId] ?? 'feeds/google-products.xml';
        $awinPath = self::AWIN_COUNTRY_FILE_MAP[$countryId] ?? 'feeds/awin-products.csv';

        $googleResult = ['success' => false, 'message' => 'No active products found', 'count' => 0];
        $awinResult = ['success' => false, 'message' => 'No active products found', 'count' => 0];

        if ($counts['google'] > 0) {
            $this->s3Path = $googlePath;
            $this->uploadToS3($xmlTmp);
            $googleResult = [
                'success' => true,
                'message' => "Feed generated with {$counts['google']} products",
                'count' => $counts['google'],
                'url' => $mediaBase.$googlePath,
            ];
        }

        if ($counts['awin'] > 0) {
            $this->s3Path = $awinPath;
            $this->uploadToS3($csvTmp, 'text/csv');
            $awinResult = [
                'success' => true,
                'message' => "Awin feed generated with {$counts['awin']} products",
                'count' => $counts['awin'],
                'url' => $mediaBase.$awinPath,
            ];
        }

        @unlink($xmlTmp);
        @unlink($csvTmp);

        return [
            'success' => $googleResult['success'] || $awinResult['success'],
            'google' => $googleResult,
            'awin' => $awinResult,
        ];
    }

    /**
     * @return list<int>|null Store IDs when ready to query products; null if none.
     */
    private function bootstrapFeed(int $countryId): ?array
    {
        $this->targetCurrency = MarketplaceFeedConfig::CURRENCIES[$countryId] ?? MarketplaceFeedConfig::DEFAULT_CURRENCY;
        $this->countryCode = MarketplaceFeedConfig::COUNTRY_CODES[$countryId] ?? MarketplaceFeedConfig::DEFAULT_COUNTRY_CODE;

        $storeIds = $this->getStoreIdsByCountryId($countryId);
        if (empty($storeIds)) {
            return null;
        }

        $categoryService = app(CategoryService::class);
        $this->allCategories = $categoryService->getAllCategories()->keyBy('id');

        $this->preloadShippingData($storeIds, $countryId);
        $this->preloadPolicies();

        return $storeIds;
    }

    // ──────────────────────────────────────────────
    // Store filtering by shipping zone destination country
    // ──────────────────────────────────────────────

    private function getStoreIdsByCountryId(int $countryId): array
    {
        return $this->storeShippingCountry->getStoreIdsByCountry([$countryId]);
    }

    // ──────────────────────────────────────────────
    // Price calculation (converted from store's home currency
    // to the feed's target currency, since a store can now ship
    // to — and appear in the feed for — countries other than its own)
    // ──────────────────────────────────────────────

    private function calculatePrice(int $domesticPrice, float $discount, ?int $storeCountryId): array
    {
        $rates = $this->priceCalculator->getDiscountedPrice(
            $domesticPrice,
            $discount,
            $storeCountryId,
            $this->targetCurrency
        );

        return [
            'price' => $rates['convertedPrice'],
            'discountedPrice' => $rates['discountedPrice'],
        ];
    }

    /**
     * Replicate the same variant selection logic used on the frontend (ProductDetailService::pickVariant)
     * to ensure the feed price matches what the user sees on the website.
     *
     * Returns [domestic_price, stock_quantity, allow_overselling] of the selected variant,
     * or null if no variants exist / product doesn't vary by price.
     */
    private function pickVariantForFeed($item): ?array
    {
        if (! $item->relationLoaded('itemVariants') || $item->itemVariants->isEmpty()) {
            return null;
        }

        if (! $item->relationLoaded('varyBy') || ! $item->varyBy) {
            return null;
        }

        $varyByPrice = (int) ($item->varyBy->price ?? 0);
        $varyByQuantity = (int) ($item->varyBy->quantity ?? 0);

        $visibleVariants = $item->itemVariants->filter(function ($variant) {
            return (int) $variant->is_visible === 1;
        });

        if ($visibleVariants->isEmpty()) {
            return null;
        }

        $sorted = $visibleVariants->sortBy(function ($variant) {
            return is_numeric($variant->domestic_price) ? (float) $variant->domestic_price : INF;
        });

        $selectedVariant = $sorted->first();

        if ($varyByQuantity === 1) {
            $inStockVariant = $sorted->first(function ($variant) {
                if ((int) ($variant->allow_overselling ?? 0) === 1) {
                    return true;
                }

                return (int) ($variant->stock_quantity ?? 0) > 0;
            });

            if ($inStockVariant) {
                $selectedVariant = $inStockVariant;
            }
        }

        return [
            'domestic_price' => $varyByPrice === 1
                ? (int) $selectedVariant->domestic_price
                : (int) $item->domestic_price,
            'stock_quantity' => $varyByQuantity === 1
                ? (int) ($selectedVariant->stock_quantity ?? 0)
                : (int) ($item->stock_quantity ?? 0),
            'allow_overselling' => $varyByQuantity === 1
                ? (int) ($selectedVariant->allow_overselling ?? 0)
                : (int) ($item->allow_overselling ?? 0),
            'gtin' => $selectedVariant->gtin ?? null,
        ];
    }

    private function resolveDiscount($item): float
    {
        $discount = $item->discount;
        if (! $discount) {
            return 0;
        }

        $now = Carbon::now();

        if ($discount->is_limited ?? 0) {
            return ($discount->value ?? 0) / 100;
        }

        $start = ! empty($discount->start_date) ? Carbon::parse($discount->start_date) : null;
        $end = ! empty($discount->end_date) ? Carbon::parse($discount->end_date) : null;

        if ($start && $end && $now->between($start, $end)) {
            return ($discount->value ?? 0) / 100;
        } elseif ($start && ! $end && $now->gte($start)) {
            return ($discount->value ?? 0) / 100;
        } elseif (! $start && $end && $now->lt($end)) {
            return ($discount->value ?? 0) / 100;
        }

        return 0;
    }

    // ──────────────────────────────────────────────
    // Product query
    // ──────────────────────────────────────────────

    private function buildFeedQuery(array $storeIds, int $lastId, int $batchSize)
    {
        return Item::on('mysql_reader')
            ->with(['discount', 'store', 'medias', 'itemVariants', 'varyBy'])
            ->select(
                'items.id',
                'items.uuid',
                'items.store_id',
                'items.title',
                'items.media_url',
                'items.type',
                'items.slug',
                'items.description',
                'items.category_id',
                'items.policy_id',
                'items.subscribe_save_available',
                'item_prices.domestic_price',
                'item_prices.stock_quantity',
                'item_prices.allow_overselling',
                'item_prices.gtin'
            )
            ->join('item_prices', 'items.id', '=', 'item_prices.item_id')
            ->where('item_prices.domestic_price', '>', 0)
            ->whereIn('items.store_id', $storeIds)
            ->where('items.status', 1)
            ->where('items.id', '>', $lastId)
            ->orderBy('items.id')
            ->limit($batchSize)
            ->get();
    }

    // ──────────────────────────────────────────────
    // Feed file generation (shared product query loop)
    // ──────────────────────────────────────────────

    /**
     * @param  list<int>  $storeIds
     * @return array{google: int, awin: int}
     */
    private function buildFeedsChunked(
        array $storeIds,
        ?string $xmlTmpFile,
        ?string $csvTmpFile,
        int $limit,
        bool $writeGoogle,
        bool $writeAwin
    ): array {
        $xml = null;
        if ($writeGoogle && $xmlTmpFile !== null) {
            $xml = new XMLWriter;
            $xml->openURI($xmlTmpFile);
            $xml->startDocument('1.0', 'UTF-8');
            $xml->setIndent(true);
            $xml->startElement('rss');
            $xml->writeAttribute('version', '2.0');
            $xml->writeAttribute('xmlns:g', MarketplaceFeedConfig::GOOGLE_NS);
            $xml->startElement('channel');
            $xml->writeElement('title', 'DogPack Marketplace');
            $xml->writeElement('link', MarketplaceFeedConfig::SITE_URL);
            $xml->writeElement('description', 'DogPack Marketplace Products');
        }

        $csvHandle = null;
        if ($writeAwin && $csvTmpFile !== null) {
            $csvHandle = fopen($csvTmpFile, 'w');
            if ($csvHandle === false) {
                throw new \RuntimeException('Unable to open Awin feed temp file for writing');
            }
            fputcsv($csvHandle, self::AWIN_CSV_HEADERS);
        }

        $lastId = 0;
        $googleCount = 0;
        $awinCount = 0;
        $batchSize = ($limit > 0 && $limit < MarketplaceFeedConfig::BATCH_SIZE) ? $limit : MarketplaceFeedConfig::BATCH_SIZE;

        while (true) {
            $items = $this->buildFeedQuery($storeIds, $lastId, $batchSize);

            if ($items->isEmpty()) {
                break;
            }

            // Prime the Subscribe & Save caches for the batch so writeItem's
            // promotion lookup is a cache hit rather than a query per product.
            if ($xml !== null) {
                $flagged = $items->where('subscribe_save_available', true);
                $this->subscribeSave->warmOfferCaches(
                    $flagged->pluck('id')->all(),
                    $flagged->pluck('store_id')->all()
                );
            }

            foreach ($items as $item) {
                if ($xml !== null && ! $this->isExcludedFromGoogleFeed($item)) {
                    $this->writeItem($xml, $item);
                    $googleCount++;
                }

                if ($csvHandle !== null) {
                    $row = $this->buildAwinCsvRow($item);
                    if ($row !== null) {
                        fputcsv($csvHandle, $row);
                        $awinCount++;
                    }
                }
            }

            $lastId = $items->last()->id;

            unset($items);

            $limitReached = $limit > 0 && (
                ($writeGoogle && $googleCount >= $limit)
                || (! $writeGoogle && $writeAwin && $awinCount >= $limit)
                || ($writeGoogle && $writeAwin && $googleCount >= $limit)
            );

            if ($limitReached) {
                break;
            }
        }

        if ($xml !== null) {
            $xml->endElement(); // channel
            $xml->endElement(); // rss
            $xml->endDocument();
            $xml->flush();
        }

        if ($csvHandle !== null) {
            fclose($csvHandle);
        }

        return ['google' => $googleCount, 'awin' => $awinCount];
    }

    /**
     * @return list<string>|null
     */
    private function buildAwinCsvRow($item): ?array
    {
        $link = $this->productPageUrl($item);
        $description = $this->cleanFeedDescription($item->description ?? '');
        $categoryLabel = $item->category_id ? $this->resolveCategoryLabel((int) $item->category_id) : '';
        $imageLink = $item->media_url ?? '';

        if ($imageLink === '') {
            return null;
        }

        $discountPct = $this->resolveDiscount($item);

        $variantData = $this->pickVariantForFeed($item);
        $domesticPrice = $variantData ? $variantData['domestic_price'] : $item->domestic_price;
        $storeCountryId = $item->store->country_id ?? null;
        $priceData = $this->calculatePrice($domesticPrice, $discountPct, $storeCountryId);

        $displayPrice = $discountPct > 0 ? $priceData['discountedPrice'] : $priceData['price'];
        if ($displayPrice <= 0) {
            return null;
        }

        $allowOverselling = $variantData ? $variantData['allow_overselling'] : ($item->allow_overselling ?? 0);
        $stockQuantity = $allowOverselling ? 1000 : ($variantData ? $variantData['stock_quantity'] : ($item->stock_quantity ?? 0));
        if ($item->type == 1) {
            $stockQuantity = 1000;
        }

        $shipping = $this->storeShippingCache[$item->store_id] ?? null;
        $deliveryCost = '';
        if ($shipping && ! $shipping['is_carrier']) {
            if ($shipping['is_free']) {
                $deliveryCost = '0.00';
            } elseif ($shipping['lowest_rate'] !== null) {
                $deliveryCost = number_format($shipping['lowest_rate'], 2, '.', '');
            }
        }

        $productId = (string) $item->uuid;
        if (strlen($productId) > 75) {
            $productId = substr($productId, 0, 75);
        }

        $gtin = $this->resolveGtin($item, $variantData);

        return [
            $productId,
            $this->normalizeCase($item->title),
            number_format($displayPrice, 2, '.', ''),
            $this->awinDeepLink($link, $item->uuid),
            $this->prodImageUrl($imageLink),
            $description,
            $this->targetCurrency,
            $stockQuantity > 0 ? '1' : '0',
            $categoryLabel,
            $item->store->name ?? '',
            $gtin,
            $deliveryCost,
            'New',
            'en',
        ];
    }

    private function productPageUrl($item): string
    {
        $localePrefix = MarketplaceFeedConfig::LOCALE_PREFIXES[$this->countryCode] ?? '';

        return MarketplaceFeedConfig::SITE_URL.$localePrefix."/marketplace/{$item->slug}/{$item->uuid}";
    }

    private function awinDeepLink(string $productUrl, string $productId): string
    {
        $query = http_build_query([
            'utm_source' => 'awin',
            'utm_medium' => 'affiliate',
            'utm_campaign' => 'product-data-feed',
            'utm_content' => 'product-ads',
            'utm_term' => $productId,
        ]);

        $separator = str_contains($productUrl, '?') ? '&' : '?';

        return $productUrl.$separator.$query;
    }

    private function writeItem(XMLWriter $xml, $item): void
    {
        $xml->startElement('item');

        $link = $this->productPageUrl($item);
        $description = $this->cleanFeedDescription($item->description ?? '');
        $categoryLabel = $item->category_id ? $this->resolveCategoryLabel((int) $item->category_id) : '';
        $imageLink = $item->media_url ?? '';

        $discountPct = $this->resolveDiscount($item);

        $variantData = $this->pickVariantForFeed($item);
        $domesticPrice = $variantData ? $variantData['domestic_price'] : $item->domestic_price;
        $storeCountryId = $item->store->country_id ?? null;
        $priceData = $this->calculatePrice($domesticPrice, $discountPct, $storeCountryId);

        $price = $priceData['price'] > 0
            ? number_format($priceData['price'], 2, '.', '').' '.$this->targetCurrency
            : '';

        $salePrice = '';
        if ($discountPct > 0) {
            $salePrice = number_format($priceData['discountedPrice'], 2, '.', '').' '.$this->targetCurrency;
        }

        $allowOverselling = $variantData ? $variantData['allow_overselling'] : ($item->allow_overselling ?? 0);
        $stockQuantity = $allowOverselling ? 1000 : ($variantData ? $variantData['stock_quantity'] : ($item->stock_quantity ?? 0));
        if ($item->type == 1) {
            $stockQuantity = 1000;
        }
        $availability = ($stockQuantity > 0) ? 'in_stock' : 'out_of_stock';

        $this->writeGElement($xml, 'id', $this->countryCode.'-'.$item->uuid);
        $this->writeGElement($xml, 'title', $this->normalizeCase($item->title));
        $this->writeGElement($xml, 'description', $description);
        $this->writeGElement($xml, 'link', $link);

        if ($imageLink) {
            $this->writeGElement($xml, 'image_link', $this->prodImageUrl($imageLink));
        }

        if ($item->relationLoaded('medias')) {
            foreach ($item->medias->take(10) as $media) {
                if ($media->url && $media->url !== $item->media_url) {
                    $this->writeGElement($xml, 'additional_image_link', $this->prodImageUrl($media->url));
                }
            }
        }

        if ($price) {
            $this->writeGElement($xml, 'price', $price);
        }

        if ($salePrice) {
            $this->writeGElement($xml, 'sale_price', $salePrice);
        }

        $this->writeGElement($xml, 'availability', $availability);
        $this->writeGElement($xml, 'condition', 'new');

        $gtin = $this->resolveGtin($item, $variantData);

        if ($gtin !== '') {
            $this->writeGElement($xml, 'gtin', $gtin);
        } else {
            $this->writeGElement($xml, 'identifier_exists', 'no');
        }

        $storeName = $item->store->name ?? '';
        if ($storeName) {
            $this->writeGElement($xml, 'brand', $storeName);
        }

        if ($categoryLabel) {
            $this->writeGElement($xml, 'product_type', $categoryLabel);
        }

        if ($item->category_id && isset(self::GOOGLE_PRODUCT_CATEGORY_MAP[(int) $item->category_id])) {
            $this->writeGElement($xml, 'google_product_category', (string) self::GOOGLE_PRODUCT_CATEGORY_MAP[(int) $item->category_id]);
        }

        $this->writeGElement($xml, 'custom_label_0', $this->getPriceBucket($priceData['price']));

        // Links the product to its Subscribe & Save promotion (see GoogleMerchantPromotionService).
        $promotionId = $this->promotionService->promotionIdFor($item, $this->countryCode);
        if ($promotionId !== null) {
            $this->writeGElement($xml, 'promotion_id', $promotionId);
        }

        // Checkout compares the (discounted) order value to the tiers, so use the price the buyer pays.
        $effectivePrice = $discountPct > 0 ? $priceData['discountedPrice'] : $priceData['price'];
        $this->writeShippingAttributes($xml, $item->store_id, (float) $effectivePrice);
        $this->writeReturnPolicyLabel($xml, $item);

        $xml->endElement(); // item
    }

    // ──────────────────────────────────────────────
    // Shipping & Returns pre-loading
    // ──────────────────────────────────────────────

    private function preloadShippingData(array $storeIds, int $countryId): void
    {
        // Only consider zones that actually cover the feed's destination country —
        // a store may have separate zones/rates for different countries it ships to.
        $zones = DB::table('shipping_zones as sz')
            ->join('shipping_zone_country as szc', 'sz.id', '=', 'szc.shipping_zone_id')
            ->whereIn('sz.store_id', $storeIds)
            ->where('szc.country_id', $countryId)
            ->whereNull('sz.deleted_at')
            ->whereNull('szc.deleted_at')
            ->select('sz.id', 'sz.store_id')
            ->distinct()
            ->get();

        $zoneIds = $zones->pluck('id')->toArray();

        $zoneRates = DB::table('shipping_zone_rates')
            ->whereIn('shipping_zone_id', $zoneIds)
            ->whereNull('deleted_at')
            ->get();

        $stores = DB::table('stores')
            ->whereIn('id', $storeIds)
            ->select('id', 'slug', 'country_id')
            ->get()
            ->keyBy('id');

        foreach ($storeIds as $storeId) {
            $storeZoneIds = $zones->where('store_id', $storeId)->pluck('id')->toArray();
            $storeRates = $zoneRates->whereIn('shipping_zone_id', $storeZoneIds);
            $storeSlug = $stores[$storeId]->slug ?? '';

            $hasCarrierRate = $storeRates->where('shipping_type', 2)->isNotEmpty();

            if ($hasCarrierRate) {
                $this->storeShippingCache[$storeId] = [
                    'slug' => $storeSlug,
                    'is_carrier' => true,
                    'is_free' => false,
                    'free_threshold' => null,
                    'lowest_rate' => null,
                    'has_weight_rate' => false,
                    'tiers' => [],
                ];

                continue;
            }

            $flatRates = $storeRates->where('shipping_type', 1);
            $hasFreeShipping = false;

            $flatFreeRates = $flatRates
                ->whereIn('condition_type', [null, ''])
                ->where('price', 0);
            if ($flatFreeRates->isNotEmpty() && $flatRates->where('price', '>', 0)->isEmpty()) {
                $hasFreeShipping = true;
            }

            $freeThreshold = null;
            $lowestPaidRate = null;

            if (! $hasFreeShipping) {
                $conditionalFree = $flatRates
                    ->where('condition_type', 2)
                    ->where('price', 0)
                    ->sortBy('low_value')
                    ->first();

                if ($conditionalFree) {
                    $freeThreshold = (float) $conditionalFree->low_value;
                }

                $paidRates = $flatRates->where('price', '>', 0);
                if ($paidRates->isNotEmpty()) {
                    $lowestPaidRate = (float) $paidRates->min('price');
                }
            }

            // Shipping rates are entered in the store's home currency; convert to the
            // feed's target currency since a store may now ship to (and appear in the
            // feed for) a country other than its own.
            $storeCountryId = $stores[$storeId]->country_id ?? null;
            $storeCurrency = $this->countryService->getCurrencyCodeByCountryId($storeCountryId);

            if ($freeThreshold !== null) {
                $freeThreshold = $this->convertShippingAmount($freeThreshold, $storeCurrency);
            }

            if ($lowestPaidRate !== null) {
                $lowestPaidRate = $this->convertShippingAmount($lowestPaidRate, $storeCurrency);
            }

            // Per-item shipping tiers (flat rates only, order-value or unconditional),
            // converted to the feed currency. Weight-based rates can't be priced per item.
            $hasWeightRate = $flatRates->contains(fn ($r) => (int) $r->condition_type === ShippingZoneRate::ZONE_CONDITION_TYPE[ShippingZoneRate::ZONE_CONDITION_WEIGHT]);
            $tiers = [];
            foreach ($flatRates as $rate) {
                $isUnconditional = $rate->condition_type === null || $rate->condition_type === '';
                $isPriceTier = (int) $rate->condition_type === ShippingZoneRate::ZONE_CONDITION_TYPE[ShippingZoneRate::ZONE_CONDITION_PRICE];
                if (! $isUnconditional && ! $isPriceTier) {
                    continue;
                }

                $tiers[] = [
                    'low' => $isPriceTier && $rate->low_value !== null
                        ? $this->convertShippingAmount((float) $rate->low_value, $storeCurrency) : 0.0,
                    'high' => $isPriceTier && $rate->high_value !== null
                        ? $this->convertShippingAmount((float) $rate->high_value, $storeCurrency) : INF,
                    'price' => $this->convertShippingAmount((float) $rate->price, $storeCurrency),
                ];
            }

            $this->storeShippingCache[$storeId] = [
                'slug' => $storeSlug,
                'is_carrier' => false,
                'is_free' => $hasFreeShipping,
                'free_threshold' => $freeThreshold,
                'lowest_rate' => $lowestPaidRate,
                'has_weight_rate' => $hasWeightRate,
                'tiers' => $tiers,
            ];
        }
    }

    private function convertShippingAmount(float $amount, string $storeCurrency): float
    {
        if ($storeCurrency === $this->targetCurrency) {
            return $amount;
        }

        return $this->currencyService->convert($amount, $storeCurrency, $this->targetCurrency)['convertedPrice'];
    }

    private function preloadPolicies(): void
    {
        $policies = DB::table('policies')->get();

        foreach ($policies as $policy) {
            $this->policyCache[$policy->id] = $policy;

            if ($policy->is_default) {
                $this->storeDefaultPolicyCache[$policy->store_id] = $policy;
            }
        }
    }

    /**
     * Shipping for one item, based on the store's flat-rate tiers for the feed country.
     *
     * Returns null when it can't be calculated (carrier rate, weight-based rate, no tiers,
     * or the item price is in a gap with no free tier above it). Otherwise:
     *  - price: float cost for this item, or null when the item sits in a tier gap
     *  - free_threshold: lowest order value above the item price where shipping becomes free
     *
     * @return array{price: ?float, free_threshold: ?float}|null
     */
    private function resolveItemShipping(array $shipping, float $itemPrice): ?array
    {
        if ($shipping['is_carrier'] || $shipping['has_weight_rate'] || empty($shipping['tiers'])) {
            return null;
        }

        $tiers = collect($shipping['tiers']);

        // Same rule as checkout: low <= value <= high, cheapest matching rate wins.
        $matching = $tiers->filter(fn ($t) => $itemPrice >= $t['low'] && $itemPrice <= $t['high']);

        $freeThreshold = $tiers
            ->filter(fn ($t) => $t['price'] == 0 && $t['low'] > $itemPrice)
            ->min('low');

        if ($matching->isNotEmpty()) {
            $price = (float) $matching->min('price');

            return [
                'price' => $price,
                'free_threshold' => $price > 0 ? $freeThreshold : null,
            ];
        }

        // Item price falls in a gap (no rate defined): show "free over Y" if there is a free tier.
        if ($freeThreshold !== null) {
            return ['price' => null, 'free_threshold' => (float) $freeThreshold];
        }

        return null;
    }

    private function writeShippingAttributes(XMLWriter $xml, int $storeId, float $itemPrice): void
    {
        $shipping = $this->storeShippingCache[$storeId] ?? null;
        $resolved = $shipping ? $this->resolveItemShipping($shipping, $itemPrice) : null;

        // Fixed label set so Merchant Center shipping rules can target each group:
        // free / paid have an exact price in <g:shipping>; unknown (carrier, weight-based
        // or an item in a rate gap) has no price, so a Merchant Center rule must supply one.
        $this->writeGElement($xml, 'shipping_label', match (true) {
            $resolved === null || $resolved['price'] === null => self::SHIPPING_LABEL_UNKNOWN,
            $resolved['price'] > 0 => self::SHIPPING_LABEL_PAID,
            default => self::SHIPPING_LABEL_FREE,
        });

        if ($resolved === null) {
            return;
        }

        $country = $this->countryCode;
        $currency = $this->targetCurrency;

        if ($resolved['price'] !== null) {
            $xml->startElementNS('g', 'shipping', null);
            $this->writeGElement($xml, 'country', $country);
            if ($resolved['price'] > 0) {
                $this->writeGElement($xml, 'service', 'Standard');
            }
            $this->writeGElement($xml, 'price', number_format($resolved['price'], 2, '.', '').' '.$currency);
            $xml->endElement();
        }

        if ($resolved['free_threshold'] !== null) {
            $xml->startElementNS('g', 'free_shipping_threshold', null);
            $this->writeGElement($xml, 'country', $country);
            $this->writeGElement($xml, 'price_threshold', number_format($resolved['free_threshold'], 2, '.', '').' '.$currency);
            $xml->endElement();
        }
    }

    private function writeReturnPolicyLabel(XMLWriter $xml, $item): void
    {
        $policy = null;

        if (! empty($item->policy_id) && isset($this->policyCache[$item->policy_id])) {
            $policy = $this->policyCache[$item->policy_id];
        } elseif (isset($this->storeDefaultPolicyCache[$item->store_id])) {
            $policy = $this->storeDefaultPolicyCache[$item->store_id];
        }

        if ($policy && $policy->is_return && $policy->time) {
            $label = 'returns_'.(int) $policy->time.'_days_buyer';
        } else {
            $label = 'no_returns';
        }

        $this->writeGElement($xml, 'return_policy_label', $label);
    }

    // ──────────────────────────────────────────────
    // Utility helpers
    // ──────────────────────────────────────────────

    private function resolveGtin($item, ?array $variantData): string
    {
        return $variantData && ! empty($variantData['gtin'])
            ? $this->sanitizeGtin($variantData['gtin'])
            : $this->sanitizeGtin($item->gtin ?? null);
    }

    /**
     * Normalize a GTIN for the feeds. Google/Awin accept spaces/dashes but ignore them, and only 8, 12, 13, or 14 digit values are valid (UPC, EAN, JAN, ISBN, ITF-14).
     */
    private function sanitizeGtin(?string $gtin): string
    {
        if ($gtin === null) {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $gtin);

        return in_array(strlen($digits), [8, 12, 13, 14], true) ? $digits : '';
    }

    private function getPriceBucket(float $price): string
    {
        if ($price < 50) {
            return 'price_0_50';
        } elseif ($price < 100) {
            return 'price_50_100';
        }

        return 'price_100_plus';
    }

    /**
     * Marketplace description for feeds: keep the original words.
     * Search snapshots strip stopwords; Shopping copy must not.
     */
    private function isExcludedFromGoogleFeed($item): bool
    {
        static $excludedIds = null;
        if ($excludedIds === null) {
            $excludedIds = array_fill_keys(GoogleFeedExcludedProductIds::IDS, true);
        }

        return isset($excludedIds[$this->countryCode.'-'.$item->uuid]);
    }

    private function cleanFeedDescription(?string $description): string
    {
        if ($description === null || $description === '') {
            return '';
        }

        $text = preg_replace('/<\s*br\s*\/?\s*>/i', ' ', $description) ?? $description;
        $text = preg_replace('/<\/?\s*(p|div|li|h[1-6]|tr)\b[^>]*>/i', ' ', $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        return $this->truncateAtWord($this->normalizeCase($text), self::DESCRIPTION_LIMIT);
    }

    private function truncateAtWord(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit);
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace === false || $lastSpace < 1) {
            return $cut;
        }

        return rtrim(mb_substr($cut, 0, $lastSpace));
    }

    private function normalizeCase(string $text): string
    {
        if ($text === '' || mb_strtoupper($text) !== $text) {
            return $text;
        }

        return mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
    }

    private function writeGElement(XMLWriter $xml, string $tag, string $value): void
    {
        $xml->startElementNS('g', $tag, null);
        $xml->writeCdata($value);
        $xml->endElement();
    }

    private function resolveCategoryLabel(int $categoryId): string
    {
        if (isset($this->categoryLabelCache[$categoryId])) {
            return $this->categoryLabelCache[$categoryId];
        }

        $category = $this->allCategories->get($categoryId);
        if (! $category) {
            return $this->categoryLabelCache[$categoryId] = '';
        }

        $root = $category;
        while ($root->parent_id && $this->allCategories->has($root->parent_id)) {
            $root = $this->allCategories->get($root->parent_id);
        }

        $label = ($root->id === $category->id) ? $category->name : ($root->name.' - '.$category->name);

        return $this->categoryLabelCache[$categoryId] = $label;
    }

    private function prodImageUrl(string $url): string
    {
        $stagingCdn = rtrim(config('services.marketplace_media'), '/');
        $prodCdn = rtrim(config('services.marketplace_images_production'), '/');

        if ($stagingCdn !== $prodCdn) {
            return str_replace($stagingCdn, $prodCdn, $url);
        }

        return $url;
    }

    private function uploadToS3(string $tmpFile, string $contentType = 'application/xml'): void
    {
        $stream = fopen($tmpFile, 'r');

        try {
            Storage::disk('mps3')->put($this->s3Path, $stream, [
                'ContentType' => $contentType,
                'CacheControl' => 'no-cache, max-age=0',
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        Storage::disk('mps3')->setVisibility($this->s3Path, 'public');
    }
}
