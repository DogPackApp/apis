<?php

namespace App\Jobs\Product;

use App\Events\Product\ImportCSVErrorsEvent;
use App\Models\Product\ImportProduct;
use App\Models\Product\ImportProductVariant;
use App\Models\Product\Item;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use App\Services\Import\ImportService;
use App\Services\Product\ProductService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ImportValidateProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $batchId;

    protected Store $store;

    protected Seller $seller;

    public ImportService $importService;

    public function __construct(string $batchId, Store $store)
    {
        $this->batchId = $batchId;
        $this->store = $store;
        $this->importService = new ImportService(resolve(ProductService::class));
    }

    public function handle(): void
    {
        $this->importService->setStore($this->store);
        $this->importService->loadToolTips();

        $products = ImportProduct::query()
            ->withCount('variants')
            ->with('variants')
            ->where('batch_id', $this->batchId)
            ->get();

        foreach ($products as $index => $product) {
            if ($product->variants->count() > 0) {
                $this->importService->validateVariants($product, $product->_id, false);
                $product->fresh();
                $product->loadMissing('variants');
                $errors = [];

                foreach ($product->variants as $variant) {

                    $variant->refresh();

                    if (! empty($variant->errors)) {
                        $errors = array_merge($errors, $variant->errors);
                    }
                }

                if (! empty($errors)) {
                    // Log::info('Errors:', $errors);
                    event(new ImportCSVErrorsEvent(
                        $this->importService->seller->uuid,
                        $this->batchId,
                        $errors
                    ));
                }

            } else {
                $this->validate($product, $product->_id);
                $product->refresh();

                if ($product->errors && count($product?->errors) > 0) {
                    event(new ImportCSVErrorsEvent(
                        $this->importService->seller->uuid,
                        $this->batchId,
                        $product->errors           // unchanged associative array
                    ));
                }
            }
        }
    }

    public function validateVariants(ImportProduct $product, int $index, bool $updateErrors): void
    {
        $variants = $product->variants;

        if ($updateErrors) {
            ImportProductVariant::where('import_product_id', $product->id)->update(['errors' => null]);
        }

        $sections = $this->importService->__buildSections($product->sections ?? '');
        $tags = $this->importService->__buildTags($product->tags ?? '');
        $images = $this->importService->__buildImages($product->media_urls ?? '');

        $default = $product?->status ?? 'ACTIVE';
        $status = $default == 'Archive' ? Item::STATUS_ARCHIVED : strtoupper($product->status);

        $product->media_uuids = $images;
        $product->save();

        if ($this->importService->isVariantExists($product, 'option1_name')) {
            $this->importService->buildAttributes($product, 'option1_name', $index, 'H');
            $this->importService->validateAttributesValues($product, 'option1_value', $index, 'I');
        }

        if ($this->importService->isVariantExists($product, 'option2_name')) {
            $this->importService->buildAttributes($product, 'option2_name', $index, 'K');
            $this->importService->validateAttributesValues($product, 'option2_value', $index, 'K');
        }

        if ($this->importService->isVariantExists($product, 'option3_name')) {
            $this->importService->buildAttributes($product, 'option3_name', $index, 'L');
            $this->importService->validateAttributesValues($product, 'option3_value', $index, 'M');
        }

        $rows = [];

        foreach ($variants as $key => $variant) {

            if ($variant->image && $variant->image != '') {
                $imageUUID = $this->importService->__buildImages($variant->image ?? '');
                if (count($imageUUID) > 0) {
                    $imageUUID = $imageUUID[0];
                } else {
                    $imageUUID = null;
                }
            }

            $combinations = [];

            $combinations[] = [
                'type' => $variant->option1_name,
                'value' => $variant->option1_value,
            ];

            if ($variant->option2_name && $variant->option2_name != '') {
                $combinations[] = [
                    'type' => $variant->option2_name,
                    'value' => $variant->option2_value,
                ];
            }
            $rows[] = [
                'variants' => $combinations,
                'is_visible' => $variant->hide_variant == 'Yes' ? 0 : 1,
                'image' => $imageUUID ?? 'nul',
                'sku' => $variant->sku ?? null,
                'domestic_price' => $variant->price ?? null,
                'weight' => $variant->weight ?? null,
                'quantity' => $variant->quantity ?? null,
                'allow_overselling' => $variant->continue_selling_when_out_of_stock === 'Yes' ? 1 : 0,
            ];
        }

        $data = [
            'status' => $status,
            'about' => [
                'title' => $product->title,
                'type' => strtoupper($product->type),
                'description' => $product->description,
                'origin' => $product->country_of_origin,
                // need to work on this to upload this images to AWS and use those UUIDs here
                'images' => $images,
                'customizations' => null,
            ],
            'price_inventory' => [
                'is_global_pricing' => 0,
                'domestic_price' => $product->price,
                'global_price' => 0,
                'weight' => $product->weight,
                'unit' => strtoupper($product->unit),
                'sku' => $product->sku ?? null,
                'stock_quantity' => $product->quantity ?? null,
                'allow_overselling' => $product->continue_selling_when_out_of_stock ? 1 : 0,
            ],
            'category_tags' => [
                'category' => 'b62e39ea-5f48-43b1-8c7e-c54ad45c19ab',
                'tags' => $tags ?? [],
            ],
            'section' => $sections ?? [],
            'discount' => null,
            'policy' => $product->policy_id,
            'item_variations' => [
                'vary_by' => null,
                'variants' => null,
                'combination' => $rows,
            ],
        ];

        $validator = $this->importService->__validateCombination($data);

        if ($validator->fails()) {

            foreach ($validator->errors()->toArray() as $key => $value) {
                if (strpos($key, 'item_variations.combination.') !== false) {

                    if (strpos($key, 'item_variations.combination.') !== false && Str::contains($key, 'variants')) {
                        //
                    } else {

                        if (strpos($key, '0') !== false) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif (strpos($key, '1') !== false) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 1);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif (strpos($key, '2') !== false) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 2);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif (strpos($key, '3') !== false) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 3);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif (strpos($key, '4') !== false) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 4);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif (strpos($key, '5') !== false) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 5);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif (strpos($key, '6') !== false) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 6);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } else {
                            $err = $this->__mapErrors([$key => $value], $product, $index);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        }
                    }
                } elseif (strpos($key, 'item_variations.variants.') !== false) {

                } else {
                    $err = $this->__mapVariantErrors([
                        $key => $value,
                    ], $product, $index);

                    $this->__updateErrorInVariant($product, $index, $err);
                }
            }
        } else {
            $product->errors = null;
        }

        $product->is_validated = 1;
        $product->save();
    }

    public function __mapVariantErrors(array $errors, ImportProduct $product, int $productNumber): array
    {
        $mapped = [];
        // map all the errors
        foreach ($errors as $key => $value) {
            if (in_array($key, ['about.title', 'about.description', 'about.type', 'about.images', 'about.images.*', 'about.origin', 'about.origin'], true)) {
                $variant = ImportProductVariant::where('import_product_id', $product->id)
                    ->where('_id', $product->_id)
                    ->first();

                if ($variant) {
                    $cell = 'A';
                    if ($key === 'about.title') {
                        $cell = 'B';
                    }
                    if ($key === 'about.description') {
                        $cell = 'E';
                    }
                    if ($key === 'about.type') {
                        $cell = 'C';
                    }
                    if ($key === 'about.images') {
                        $cell = 'D';
                    }
                    if ($key === 'about.origin') {
                        $cell = 'U';
                    }

                    $variant->errors = array_merge($variant?->errors ?? [], [
                        $product->_id.'_'.$cell => $value[0],
                    ]);
                    $variant->save();
                }
            } else {
                $mapped[$productNumber.'_'.$product->mapping()[$key]] = $value[0];
            }

        }

        return $mapped;
    }

    public function validate($product, int $index): void
    {
        $variant = $product->variants->count();

        $productNumber = $index;
        if ($variant > 0) {
            ImportProductVariant::where('import_product_id', $product->id)
                ->where('_id', $index)
                ->update([
                    'errors' => null,
                ]);

            $this->validateVariants($product, $index, true);

            // dump('product has variants, skipping validation');
            return;
        }

        $sections = $this->importService->__buildSections($product->sections ?? '');
        $tags = $this->importService->__buildTags($product->tags ?? '');
        $images = $this->importService->__buildImages($product->media_urls ?? '');

        $default = $product?->status ?? 'ACTIVE';
        $status = $default == 'Archive' ? Item::STATUS_ARCHIVED : strtoupper($product->status);

        $product->media_uuids = $images;
        $product->save();

        // create a array here like below comment
        $data = [
            'status' => $status,
            'about' => [
                'title' => $product->title,
                'type' => strtoupper($product->type),
                'description' => $product->description,
                'origin' => $product->country_of_origin,
                // need to work on this to upload this images to AWS and use those UUIDs here
                'images' => $images,
                'customizations' => null,
            ],
            'price_inventory' => [
                'is_global_pricing' => 0,
                'domestic_price' => $product->price,
                'global_price' => 0,
                'weight' => $product->weight,
                'unit' => strtoupper($product->unit),
                'sku' => $product->sku ?? null,
                'stock_quantity' => $product->quantity ?? null,
                'allow_overselling' => $product->continue_selling_when_out_of_stock ? 1 : 0,
            ],
            'category_tags' => [
                'category' => 'b62e39ea-5f48-43b1-8c7e-c54ad45c19ab',
                'tags' => $tags ?? [],
            ],
            'section' => $sections ?? [],
            'discount' => null,
            'policy' => $product->policy_id,
            'item_variations' => null,
        ];

        $validator = $this->__validate($data);

        if ($validator->fails()) {
            $product->errors = $this->__mapErrors($validator->errors()->toArray(), $product, $productNumber);
        } else {
            $product->errors = null;
        }

        $product->is_validated = 1;
        $product->save();
    }

    public function __mapErrors(array $errors, ImportProduct $product, int $productNumber): array
    {
        $mapped = [];
        // map all the errors
        foreach ($errors as $key => $value) {
            $mapped[$productNumber.'_'.$product->mapping()[$key]] = $value[0];
        }

        return $mapped;
    }

    public function __validate(array $data)
    {
        return Validator::make($data, [
            'status' => ['required', Rule::in(array_keys(Item::STATUS))],
            // About section rules
            'about.title' => ['required', 'string'],
            'about.type' => ['required', 'string', Rule::in(array_keys(Item::TYPES))],
            'about.description' => ['required', 'string'],

            // Images: Accept an array with up to 15 items, each file max 10MB (10MB = 10240 KB)
            // changing it to 250 as Shopify Supports 250
            'about.images' => ['array', 'min:1', 'max:250', 'required'],
            // this just needs to be an uuid, check for __buildImages for more details:
            'about.images.*' => ['uuid'],
            // origin
            'about.origin' => ['required', 'string'],
            // Price and inventory section
            'price_inventory.allow_overselling' => ['sometimes', 'boolean'],
            'price_inventory.is_global_pricing' => ['required', 'boolean'],
            'price_inventory.domestic_price' => ['required', 'numeric', 'gt:0'],

            'price_inventory.weight' => ['required', 'numeric', 'gt:0'],
            'price_inventory.unit' => ['required', 'string', Rule::in(array_keys(Item::ITEM_WEIGHT))],

            'price_inventory.stock_quantity' => [
                'nullable',
                'numeric',
                'required_if:price_inventory.allow_overselling,0',
            ],

            // Category and tags
            'category_tags.category' => ['required', 'uuid', 'exists:categories,uuid'],
            'category_tags.tags' => ['nullable', 'array'],
            'category_tags.tags.*' => ['string'],

            // Section
            'section' => ['nullable', 'array'],
            'section.*' => ['uuid'],

            'item_variations' => ['nullable', 'array'],             // Variants Validation
            'item_variations.variants' => ['nullable', 'array'],
            'item_variations.variants.*.name' => ['required_with:item_variations.variants', 'string'],
            'item_variations.variants.*.options' => ['required_with:item_variations.variants.*.name', 'array', 'min:1'],
            'item_variations.variants.*.options.*' => ['required_with:item_variations.variants.*.options', 'string'],

            // Combination Validation
            'item_variations.combination' => ['nullable', 'array'],
            'item_variations.combination.*.variants' => ['required_with:item_variations.combination', 'array'],
            'item_variations.combination.*.variants.*.type' => ['required_with:item_variations.combination.*.variants', 'string'],
            'item_variations.combination.*.variants.*.value' => ['required_with:item_variations.combination.*.variants.*.type', 'string'],

            'item_variations.combination.*.domestic_price' => ['nullable', 'numeric', 'min:0'],
            'item_variations.combination.*.global_price' => ['nullable', 'numeric', 'min:0'],
            'item_variations.combination.*.sku' => ['nullable', 'string'],
            'item_variations.combination.*.quantity' => ['required', 'integer', Rule::when(
                fn ($input) => ! ($input['allow_overselling'] ?? false),
                ['gt:0'],            // overselling OFF → must be > 0
                []                   // overselling ON → allow negative
            )],
            'item_variations.combination.*.weight' => ['nullable', 'numeric', 'min:0'],
            'item_variations.combination.*.is_visible' => ['required', 'bool'],
            'item_variations.combination.*.allow_overselling' => ['sometimes', 'boolean'],

            'item_variations.combination.*.image' => ['nullable', 'uuid'],
        ], [
            'about.description.required' => $this->importService->tooltip['product_description_html']['EMPTY'] ?? '',
            'about.description.string' => $this->importService->tooltip['product_description_html']['INVALID'] ?? '',

            'about.title.required' => $this->importService->tooltip['product_title']['EMPTY'] ?? '',
            'about.title.string' => $this->importService->tooltip['product_title']['INVALID'] ?? '',

            'about.type.required' => $this->importService->tooltip['product_type']['EMPTY'] ?? '',
            'about.type.string' => $this->importService->tooltip['product_type']['INVALID'] ?? '',
            'about.type.in' => $this->importService->tooltip['product_type']['INVALID'] ?? '',

            'status.required' => $this->importService->tooltip['product_status']['EMPTY'] ?? '',
            'status.string' => $this->importService->tooltip['product_status']['INVALID'] ?? '',
            'status.in' => $this->importService->tooltip['product_status']['INVALID'] ?? '',

            'about.images.required' => $this->importService->tooltip['product_media_urls']['EMPTY'] ?? '',
            'about.images.min' => $this->importService->tooltip['product_media_urls']['INVALID'] ?? '',
            'about.images.*' => $this->importService->tooltip['product_media_urls']['INVALID'] ?? '',

            'about.origin.required' => $this->importService->tooltip['country_of_origin']['EMPTY'] ?? '',
            'about.origin.string' => $this->importService->tooltip['country_of_origin']['INVALID'] ?? '',

            // section will never hit an error so not adding it.
            // tags will never hit an error so not adding it.
            'price_inventory.domestic_price.required' => $this->importService->tooltip['price']['EMPTY'],
            'price_inventory.domestic_price.numeric' => $this->importService->tooltip['price']['INVALID'],
            'price_inventory.domestic_price.gt' => $this->importService->tooltip['price']['INVALID'],
            //
            'price_inventory.stock_quantity.numeric' => $this->importService->tooltip['quantity']['INVALID'],
            'price_inventory.stock_quantity.required_if' => $this->importService->tooltip['quantity']['EMPTY'],
            //
            'price_inventory.allow_overselling.boolean' => $this->importService->tooltip['continue_selling_when_out_of_stock']['INVALID'],
            // sku will never hit an error so not adding it.
            'price_inventory.weight.required' => $this->importService->tooltip['weight']['EMPTY'],
            'price_inventory.weight.numeric' => $this->importService->tooltip['weight']['INVALID'],
            'price_inventory.weight.gt' => $this->importService->tooltip['weight']['INVALID'],

            'price_inventory.unit.required' => $this->importService->tooltip['unit']['EMPTY'],
            'price_inventory.unit.in' => $this->importService->tooltip['unit']['INVALID'],
            'price_inventory.unit.string' => $this->importService->tooltip['unit']['INVALID'],
        ]);
    }

    public function __updateErrorInVariant(ImportProduct $product, int $index, array $err): void
    {
        $variant = ImportProductVariant::where('import_product_id', $product->id)
            ->where('_id', $index)
            ->first();

        if (! $variant) {
            return;
        }

        $variant->errors = array_merge($variant?->errors ?? [], $err);
        $variant->save();
    }
}
