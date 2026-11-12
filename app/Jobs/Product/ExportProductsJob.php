<?php

namespace App\Jobs\Product;

use App\Http\Resources\Product\ProductResource;
use App\Mail\Product\ProductsExportEmail;
use App\Models\Product\ExportProduct;
use App\Models\Product\Item;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $batchID;

    protected $status;

    public $store;

    /**
     * @var list<string>
     */
    protected array $columnsToExport = ['product_id', 'product_title', 'product_type', 'product_media_urls', 'product_description_html', 'variations', 'hide_variant', 'option1_name', 'option1_value', 'option2_name', 'option2_value', 'option3_name', 'option3_value', 'variant_image', 'price', 'quantity', 'continue_selling_when_out_of_stock', 'sku', 'weight', 'unit', 'country_of_origin', 'tags', 'sections', 'product_status'];

    /**
     * @var array<string, string>
     */
    protected array $mapColumnsToExport = [
        'product_id' => 'slug',
        'product_title' => 'title',
        'product_type' => 'type',
        'product_media_urls' => 'medias',
        'product_description_html' => 'description',
        'hide_variant' => 'hide_variant',
        'option1_name' => 'option1_name',
        'option1_value' => 'option1_value',
        'option2_name' => 'option2_name',
        'option2_value' => 'option2_value',
        'option3_name' => 'option3_name',
        'option3_value' => 'option3_value',
        'variant_image' => 'variant_image',
        'price' => 'prices.domestic_price',
        'quantity' => 'prices.stock_quantity',
        'continue_selling_when_out_of_stock' => 'prices.allow_overselling',
        'sku' => 'prices.sku',
        'weight' => 'prices.weight',
        'unit' => 'prices.unit',
        'country_of_origin' => 'origin',
        'tags' => 'tags',
        'sections' => 'sections',
        'product_status' => 'status',
    ];

    public function __construct(array $data)
    {
        $this->batchID = $data['batch_id'];
        $this->status = $data['status'] ?? null;
        $this->store = $data['store_id']
            ? Store::on('mysql_reader')->where('id', $data['store_id'])->first()
            : null;
    }

    public function handle(): void
    {
        $exportProducts = ExportProduct::where('batch_id', $this->batchID)
            ->pluck('product_uuid')
            ->filter()
            ->values()
            ->toArray();

        $exportProducts = ! empty($exportProducts) ? $exportProducts : null;

        $items = Item::query()
            ->with([
                'media',
                'medias',
                'category',
                'sections',
                'policy',
                'tags',
                'price',
                'varyBy',
                'itemVariants.media',
                'itemVariants.attributesOption.attribute',
            ])
            ->where('store_id', $this->store->id)
            ->when($this->status ?? null, function ($query, $status) {
                $query->where('status', Item::STATUS[$status]);
            })->when($exportProducts ?? null, function ($query, $products) {
                $query->whereIn('uuid', $products);
            })->get();

        $seller = Seller::on('mysql_reader')->select('id', 'email')->where('id', $this->store->seller_id)->first();

        $filename = 'products-export-'.time().'.csv';
        $path = "exports/csv/{$this->store->slug}/{$filename}";

        $products = ProductResource::collection($items);
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, fields: $this->columnsToExport);

        foreach ($products as $product) {
            $hasVariants = count($product->itemVariants) > 0;
            if ($hasVariants) {
                foreach ($product->itemVariants as $index2 => $variant) {
                    $product->variantData = [
                        'variant_number' => $index2,
                        'variant' => $variant,
                    ];
                    fputcsv($handle, array_map(fn ($column) => $this->getRawData($product, $column, $index2), $this->columnsToExport));
                }
            } else {
                fputcsv($handle, array_map(fn ($column) => $this->getRawData($product, $column, null, false), $this->columnsToExport));
            }
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('mps3')->put($path, $content);
        Storage::disk('mps3')->setVisibility($path, 'public');

        $url = config('services.marketplace_media').$path;

        Mail::to(users: $seller->email)->send(new ProductsExportEmail(
            $this->store->name,
            $filename,
            count($products),
            now()->format('Y-m-d H:i:s'),
            $url,
        ));
    }

    private function getMediaUrls(ProductResource $product): string
    {
        $mediaUrls = collect($product->medias)->pluck('url');

        return count($mediaUrls) > 0 ? $mediaUrls->implode(',') : '';
    }

    private function getRawData(mixed $product, string $column, ?int $index2 = null, bool $isVariant = true): mixed
    {
        switch ($column) {
            case 'product_id':
                return Str::slug($product->title) ?? '';
            case 'product_media_urls':
                if ($index2 !== null && $index2 > 0) {
                    return '';
                }

                return $this->getMediaUrls($product) ?? '';
            case 'product_type':
                if ($index2 !== null && $index2 > 0) {
                    return '';
                }

                return array_search($product->type, Item::TYPES) ?? '';
            case 'product_title':
                if ($index2 !== null && $index2 > 0) {
                    return '';
                }

                return $product->title ?? '';
            case 'country_of_origin':
                if ($index2 !== null && $index2 > 0) {
                    return '';
                }

                return $product->origin ?? '';
            case 'product_description_html':
                if ($index2 !== null && $index2 > 0) {
                    return '';
                }

                return $product->description ?? '';
            case 'variations':
                return $isVariant ? count($product->itemVariants) > 0 ? 'Yes' : 'No' : 'No';
            case 'tags':
                if ($index2 !== null && $index2 > 0) {
                    return '';
                }

                return $product->tags->map(function ($tag) {
                    return $tag->name;
                })->implode(', ') ?? '';
            case 'sections':
                if ($index2 !== null && $index2 > 0) {
                    return '';
                }

                return $product->sections->map(function ($section) {
                    if ($section->name == 'All') {
                        return '';
                    }

                    return $section->name;
                })->filter()->implode(', ') ?? '';
            case 'product_status':
                if ($index2 !== null && $index2 > 0) {
                    return '';
                }

                return array_search($product->status, Item::STATUS) ?? '';
            case 'hide_variant':
                if ($isVariant) {
                    return ($product->variantData['variant']?->is_visible ? 'No' : 'Yes') ?? '';
                }

                return '';
            case 'price':
                if ($isVariant && $product->varyBy?->price) {
                    return $product->variantData['variant']?->domestic_price / 100 ?? '';
                }

                return $product->price->domestic_price / 100 ?? '';
            case 'quantity':
                if ($isVariant && $product->varyBy?->quantity) {
                    return $product->variantData['variant']?->stock_quantity ?? '';
                }

                return $product->price->stock_quantity ?? '';
            case 'continue_selling_when_out_of_stock':
                if ($isVariant && $product->varyBy?->quantity) {
                    return ($product->variantData['variant']?->allow_overselling ? 'Yes' : 'No') ?? '';
                }

                return $product->price->allow_overselling ? 'Yes' : 'No' ?? '';
            case 'sku':
                if ($isVariant && $product->varyBy?->sku) {
                    return $product->variantData['variant']?->sku ?? '';
                }

                return $product->price->sku ?? '';
            case 'weight':
                if ($product->type == Item::TYPE_DIGITAL) {
                    return '';
                }
                if ($isVariant && $product->varyBy?->weight) {
                    return $product->variantData['variant']?->weight ?? '';
                }

                return $product->price->weight ?? '';
            case 'unit':
                if ($index2 !== null && $index2 > 0) {
                    return '';
                }

                return array_search($product->price->unit, Item::ITEM_WEIGHT) ?? '';
            case 'variant_image':
                if ($isVariant) {
                    return $product->variantData['variant']?->media?->url ?? '';
                }

                return '';
            default:
                if ($isVariant) {
                    if (preg_match('/^option(\d)_(name|value)$/', $column, $matches)) {
                        $optionIndex = (int) $matches[1] - 1;
                        $attribute = $product->variantData['variant']?->attributesOption->get($optionIndex);

                        return $matches[2] === 'name'
                            ? ($attribute?->attribute?->name ?? '')
                            : ($attribute?->attribute_value ?? '');
                    }
                }

                return $product->variantData[$column] ?? $product[$this->mapColumnsToExport[$column]] ?? '';
        }
    }
}
