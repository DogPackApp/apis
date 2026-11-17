<?php

namespace App\Services\Import;

use App\Events\Product\ImportCSVProgressEvent;
use App\Models\Product\Gallery;
use App\Models\Product\ImportProduct;
use App\Models\Product\ImportProductVariant;
use App\Models\Product\Item;
use App\Models\Product\ItemCsv;
use App\Models\Seller\Seller;
use App\Models\Store\Policy;
use App\Models\Store\Section;
use App\Models\Store\Store;
use App\Services\Product\ProductService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ImportService
{
    /** Pusher payload limit is 10240 bytes; leave headroom for encoding overhead. */
    private const BROADCAST_PAYLOAD_MAX_BYTES = 9500;

    private const BROADCAST_DESCRIPTION_MAX_LENGTH = 1000;

    public Collection $products;

    public Store $store;

    public Seller $seller;

    protected ProductService $productService;

    public $tooltip = null;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function parseCSV($file): ImportService
    {
        try {
            $path = $file;

            $data = collect([]);

            if (($handle = fopen($path, 'r')) !== false) {
                // Read the header row
                $header = fgetcsv($handle, 10000, ',');
            }

            // Read the rest of the rows
            while (($row = fgetcsv($handle, 10000, ',')) !== false) {
                $data->push(array_combine($header, $row));
            }

            fclose($handle);

            $this->products = ($data);

            return $this;
        } catch (\Exception $e) {
            throw new \Exception('Something went wrong, please contact support.');

            return $this;
        }

    }

    public function import($batchId, $store): ImportService
    {
        $this->setStore($store);

        $this->autofillOptions('option1_name');
        $this->autofillOptions('option2_name');
        $this->autofillOptions('option3_name');
        $this->autofillOptions('price');
        $this->autofillOptions('quantity');
        $this->autofillOptions('weight');
        $this->updateQuantity();

        $products = $this->products->groupBy('product_id');

        $start = 2;
        foreach ($products as $row => $product) {
            $insertVariants = false;
            $importProduct = new ImportProduct;
            $importProduct->batch_id = $batchId;
            $productData = [];
            $broadcasting = [];

            foreach ($product as $index => $item) {
                if ($index == 0) {
                    $this->mapProductData($importProduct, $item, $start);
                    $broadcasting['product'] = $importProduct->toArray();

                    $importProduct->save();
                    if ($product->count() > 1) {
                        $productData[] = $this->buildVariantData($importProduct->id, $item, $start);
                        $broadcasting['variants'] = $productData;
                    }

                    $start++;
                } else {
                    if ($importProduct->id ?? null) {
                        $productData[] = $this->buildVariantData($importProduct->id, $item, $start);
                        $broadcasting['variants'] = $productData;
                        $start++;
                        $insertVariants = true;
                    }
                }
            }

            $this->broadcastImportProgress($this->seller->uuid, $batchId, $broadcasting);

            if ($insertVariants && ! empty($productData)) {
                ImportProductVariant::insert($productData);
            }
        }

        return $this;
    }

    private function mapProductData(ImportProduct $importProduct, array $item, $id): void
    {
        $importProduct->uuid = Str::uuid();
        $importProduct->_id = $id;
        $importProduct->product_id = $item['product_id'];
        $importProduct->title = $item['product_title'];
        $importProduct->type = $item['product_type'];
        $importProduct->description = $item['product_description_html'];
        $importProduct->media_urls = $item['product_media_urls'];
        $importProduct->tags = $item['tags'];
        $importProduct->sections = $item['sections'];
        $importProduct->status = $item['product_status'];
        $importProduct->country_of_origin = $item['country_of_origin'];
        $importProduct->price = $item['price'];
        $importProduct->quantity = $item['quantity'];
        $importProduct->sku = $item['sku'];
        $importProduct->weight = $item['weight'];
        $importProduct->unit = $item['unit'];
        $importProduct->continue_selling_when_out_of_stock = $item['continue_selling_when_out_of_stock'];
    }

    private function buildVariantData(int $importProductId, array $item, $id): array
    {
        return [
            'uuid' => Str::uuid(),
            '_id' => $id,
            'import_product_id' => $importProductId,
            'option1_name' => $item['option1_name'],
            'option1_value' => $item['option1_value'],
            'option2_name' => $item['option2_name'],
            'option2_value' => $item['option2_value'],
            'option3_name' => $item['option3_name'],
            'option3_value' => $item['option3_value'],
            'price' => $item['price'],
            'quantity' => $item['quantity'],
            'sku' => $item['sku'],
            'weight' => $item['weight'],
            'unit' => $item['unit'],
            'continue_selling_when_out_of_stock' => $item['continue_selling_when_out_of_stock'] === 'Yes' ? 1 : 0,
            'hide_variant' => $item['hide_variant'],
            'image' => $item['variant_image'],
        ];
    }

    private function broadcastImportProgress(string $sellerUuid, $batchId, array $broadcasting): void
    {
        $variants = $broadcasting['variants'] ?? [];

        if (! is_array($variants) || $variants === []) {
            $this->dispatchImportProgressEvent($sellerUuid, $batchId, $this->fitBroadcastMessage($broadcasting, $sellerUuid, $batchId));

            return;
        }

        foreach ([2, 1] as $chunkSize) {
            $chunks = array_chunk($variants, $chunkSize);
            $messages = $this->buildVariantChunkMessages($broadcasting, $chunks);

            if ($this->variantChunkMessagesFitLimit($sellerUuid, $batchId, $messages)) {
                foreach ($messages as $message) {
                    $this->dispatchImportProgressEvent(
                        $sellerUuid,
                        $batchId,
                        $this->fitBroadcastMessage($message, $sellerUuid, $batchId)
                    );
                }

                return;
            }
        }

        foreach ($this->buildVariantChunkMessages($broadcasting, array_chunk($variants, 1)) as $message) {
            $this->slimBroadcastPayload($message);
            $this->dispatchImportProgressEvent($sellerUuid, $batchId, $message);
        }
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $chunks
     * @return array<int, array<string, mixed>>
     */
    private function buildVariantChunkMessages(array $broadcasting, array $chunks): array
    {
        $product = $broadcasting['product'] ?? [];
        $messages = [];

        foreach ($chunks as $index => $variantChunk) {
            $messages[] = [
                'product' => $product,
                'variants' => $variantChunk,
                'variant_chunk' => $index + 1,
                'variant_chunk_total' => count($chunks),
            ];
        }

        return $messages;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    private function variantChunkMessagesFitLimit(string $sellerUuid, $batchId, array $messages): bool
    {
        foreach ($messages as $message) {
            if ($this->estimateBroadcastPayloadBytes($sellerUuid, $batchId, $this->fitBroadcastMessage($message, $sellerUuid, $batchId))
                > self::BROADCAST_PAYLOAD_MAX_BYTES) {
                return false;
            }
        }

        return true;
    }

    private function fitBroadcastMessage(array $message, string $sellerUuid, $batchId): array
    {
        if ($this->estimateBroadcastPayloadBytes($sellerUuid, $batchId, $message) <= self::BROADCAST_PAYLOAD_MAX_BYTES) {
            return $message;
        }

        $this->slimBroadcastPayload($message);

        return $message;
    }

    private function dispatchImportProgressEvent(string $sellerUuid, $batchId, array $message): void
    {
        event(new ImportCSVProgressEvent($sellerUuid, $message, $batchId));
    }

    private function estimateBroadcastPayloadBytes(string $sellerUuid, $batchId, array $message): int
    {
        return strlen(json_encode([
            'batch_id' => $batchId,
            'seller_id' => $sellerUuid,
            'message' => $message,
            'timestamp' => now()->toDateTimeString(),
        ], JSON_UNESCAPED_UNICODE));
    }

    private function slimBroadcastPayload(array &$payload): void
    {
        if (isset($payload['product']) && is_array($payload['product'])) {
            $this->slimProductForBroadcast($payload['product']);
        }
    }

    private function slimProductForBroadcast(array &$product): void
    {
        if (isset($product['description']) && is_string($product['description'])
            && strlen($product['description']) > self::BROADCAST_DESCRIPTION_MAX_LENGTH) {
            $product['description'] = substr($product['description'], 0, self::BROADCAST_DESCRIPTION_MAX_LENGTH).'...';
        }

        if (isset($product['uuid'])) {
            $product['uuid'] = (string) $product['uuid'];
        }

        if (isset($product['batch_id'])) {
            $product['batch_id'] = (string) $product['batch_id'];
        }
    }

    private function updateQuantity(): void
    {
        $this->products = $this->products->transform(function ($product) {
            if (empty($product['quantity']) || $product['quantity'] <= 0) {
                $product['continue_selling_when_out_of_stock'] = 'Yes';
            }

            return $product;
        });
    }

    private function autofillOptions($key): void
    {
        $products = $this->products->groupBy('product_id');

        $normalizedOptions = [];

        foreach ($products as $productId => $product) {
            if ($product->count() <= 1) {
                continue;
            }

            $unique = $product
                ->pluck($key)
                ->filter(fn ($v) => $v && trim($v) !== '')
                ->unique();

            if ($unique->count() !== 1) {
                continue;
            }

            $normalizedOptions[$productId] = $unique->first();
        }

        if (empty($normalizedOptions)) {
            return;
        }

        $this->products->transform(function ($item) use ($normalizedOptions, $key) {
            if (isset($normalizedOptions[$item['product_id']])) {
                $item[$key] = $normalizedOptions[$item['product_id']];
            }

            return $item;
        });
    }

    public function setStore(Store $store): void
    {
        $this->store = $store;

        $this->seller = Seller::query()
            ->select(['id', 'uuid'])
            ->where('id', $this->store->seller_id)
            ->first();
    }

    /**
     * @return bool
     *
     * To Validate Attributes
     */
    public function buildAttributes($product, $attributeKey, $index, $cell): bool
    {
        $message = [];

        if ($attributeKey === 'option1_name') {
            $message['OPTION_INVALID'] = $this->tooltip['option1_name']['INVALID'] ?? '';
            $message['OPTION_EMPTY'] = $this->tooltip['option1_name']['EMPTY'] ?? '';
        } elseif ($attributeKey === 'option2_name') {
            $message['OPTION_INVALID'] = $this->tooltip['option2_name']['INVALID'] ?? '';
            $message['OPTION_EMPTY'] = $this->tooltip['option2_name']['EMPTY'] ?? '';
        } elseif ($attributeKey === 'option3_name') {
            $message['OPTION_INVALID'] = $this->tooltip['option3_name']['INVALID'] ?? '';
            $message['OPTION_EMPTY'] = $this->tooltip['option3_name']['EMPTY'] ?? '';
        }

        $variants = $product->variants;

        $options = array_filter($variants->pluck($attributeKey)->toArray());

        if (count($options) === 0) {
            for ($i = 0; $i < count($variants); $i++) {

                $iindex = $index + $i;

                $this->__updateErrorInVariant($product, $iindex, [
                    $iindex.'_'.$cell => $message['OPTION_EMPTY'],
                ]);
            }
        }

        if (count(array_unique($variants->pluck($attributeKey)->toArray())) > 1) {
            for ($i = 0; $i < count($variants); $i++) {

                $iindex = $product->_id + $i;

                $this->__updateErrorInVariant($product, $iindex, [
                    $iindex.'_'.$cell => $message['OPTION_INVALID'],
                ]);
            }
        }

        return true;
    }

    /**
     * @return bool
     *
     * To Validate Attribute Values
     */
    public function validateAttributesValues($product, $attributeKey, $index, $cell): bool
    {
        $message = [];

        if ($attributeKey === 'option1_value') {
            $message['OPTION_INVALID'] = $this->tooltip['option1_value']['INVALID'] ?? '';
            $message['OPTION_EMPTY'] = $this->tooltip['option1_value']['EMPTY'] ?? '';

        } elseif ($attributeKey === 'option2_value') {
            $message['OPTION_INVALID'] = $this->tooltip['option2_value']['INVALID'] ?? '';
            $message['OPTION_EMPTY'] = $this->tooltip['option2_value']['EMPTY'] ?? '';

        } elseif ($attributeKey === 'option3_value') {
            $message['OPTION_INVALID'] = $this->tooltip['option3_value']['INVALID'] ?? '';
            $message['OPTION_EMPTY'] = $this->tooltip['option3_value']['EMPTY'] ?? '';
        }

        try {
            $variants = $product->variants;

            foreach ($variants as $variant) {
                $currenVariantIndex = $variant->_id;

                if ($variant->{$attributeKey} === '') {
                    $this->__updateErrorInVariant($product, $currenVariantIndex, [
                        $currenVariantIndex.'_'.$cell => $message['OPTION_EMPTY'],
                    ]);
                }
            }

            $values = array_filter($variants->pluck($attributeKey)->toArray());
            if (count($values) === 0) {
                foreach ($variants as $variant) {
                    $currenVariantIndex = $variant->_id;

                    $this->__updateErrorInVariant($product, $currenVariantIndex, [
                        $currenVariantIndex.'_'.$cell => $message['OPTION_INVALID'],
                    ]);

                }
            }

        } catch (\Exception $e) {
            dd($e);
        }

        return true;
    }

    /**
     * @return bool
     *
     * To Determine the existence of Variant
     */
    public function isVariantExists($product, $attributeKey)
    {
        $variants = $product->variants;

        $attributeKeys = explode('_', $attributeKey);

        // $attributeKye you have passed here is option1_name, option2_name, option3_name
        // pluck all the options and values for the given attribute key
        // if both the options and values are completely empty then return true
        $options = array_filter($variants->pluck($attributeKeys[0].'_name')->toArray());
        $values = array_filter($variants->pluck($attributeKeys[0].'_value')->toArray());

        if (count($options) === 0 && count($values) === 0) {
            return false;
        }

        return true;
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

    /**
     * Validate the combinations of the variant
     */
    // validator_job
    public function validateVariants(ImportProduct $product, int $index, bool $updateErrors): void
    {
        $product->refresh();
        $variants = $product->variants;

        if ($updateErrors) {
            ImportProductVariant::where('import_product_id', $product->id)
//                ->where('_id', $index)
                ->update(['errors' => null]);
        }

        $sections = $this->__buildSections($product->sections ?? '');
        $tags = $this->__buildTags($product->tags ?? '');
        $images = $this->__buildImages($product->media_urls ?? '');

        $default = $product?->status ?? 'ACTIVE';
        $status = $default == 'Archive' ? Item::STATUS_ARCHIVED : strtoupper($product->status);

        if ($this->isVariantExists($product, 'option1_name')) {
            $this->buildAttributes($product, 'option1_name', $index, 'H');
            $this->validateAttributesValues($product, 'option1_value', $index, 'I');
        }

        if ($this->isVariantExists($product, 'option2_name')) {
            $this->buildAttributes($product, 'option2_name', $index, 'J');
            $this->validateAttributesValues($product, 'option2_value', $index, 'K');
        }

        if ($this->isVariantExists($product, 'option3_name')) {
            $this->buildAttributes($product, 'option3_name', $index, 'L');
            $this->validateAttributesValues($product, 'option3_value', $index, 'M');
        }

        $rows = [];

        foreach ($variants as $key => $variant) {

            if ($variant->image && $variant->image != '') {
                $imageUUID = $this->__buildImages($variant->image);
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

            if ($variant?->option3_name && $variant?->option3_name != '') {
                $combinations[] = [
                    'type' => $variant->option3_name,
                    'value' => $variant->option3_value,
                ];
            }

            $allowOverselling = 0;
            if ($variant->continue_selling_when_out_of_stock) {
                $allowOverselling = 1;
            }

            //            dump("**********************************************************************************************");
            $rows[] = [
                'variants' => $combinations,
                'is_visible' => $variant->hide_variant == 'Yes' ? 0 : 1,
                'image' => $imageUUID ?? null,
                'sku' => $variant->sku ?? null,
                'domestic_price' => $variant->price ?? null,
                'weight' => $variant->weight ?? null,
                'quantity' => $variant->quantity ?? null,
                'allow_overselling' => $allowOverselling,
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

        $validator = $this->__validateCombination($data);

        if ($validator->fails()) {

            foreach ($validator->errors()->toArray() as $key => $value) {
                if (strpos($key, 'item_variations.combination.') !== false) {

                    if (strpos($key, 'item_variations.combination.') !== false && Str::contains($key, 'variants')) {
                        //
                    } else {
                        $parts = explode('.', $key);
                        $index = (int) $parts[2] ?? 0;   // this gives "15"
                        if ($index === 0) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif ($index === 1) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 1);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif ($index === 2) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 2);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif ($index === 3) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 3);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif ($index === 4) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 4);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif ($index === 5) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 5);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif ($index === 6) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 6);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif ($index === 7) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 7);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif ($index === 8) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 8);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif ($index === 9) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 9);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif ($index === 10) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 10);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif ($index === 11) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 11);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif ($index === 12) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 12);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif ($index === 13) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 13);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif ($index === 14) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 14);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif ($index === 15) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 15);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif ($index === 16) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 16);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif ($index === 17) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 17);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif ($index === 18) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 18);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif ($index === 19) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 19);
                            $this->__updateErrorInVariant($product, $product->_id, $err);
                        } elseif ($index === 20) {
                            $err = $this->__mapErrors([$key => $value], $product, $product->_id + 20);
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

    public function __validateCombination(array $data): \Illuminate\Validation\Validator
    {
        //        dd($data);
        return Validator::make($data, [
            'status' => ['required', Rule::in(array_keys(Item::STATUS))],
            // About section rules
            'about.title' => ['required', 'string', 'min:1'],
            'about.type' => ['required', 'string', Rule::in(array_keys(Item::TYPES))],
            'about.description' => ['required', 'string'],

            // Images: Accept an array with up to 15 items, each file max 10MB (10MB = 10240 KB)
            // changing it to 250 as Shopify Supports 250
            'about.images' => ['required', 'array'],
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

            'item_variations.combination.*.domestic_price' => ['required', 'numeric', 'gt:0'],
            'item_variations.combination.*.global_price' => ['nullable', 'numeric', 'gt:0'],
            'item_variations.combination.*.sku' => ['nullable', 'string'],
            'item_variations.combination.*.weight' => ['required', 'numeric', 'gt:0'],
            'item_variations.combination.*.is_visible' => ['required', 'bool'],
            'item_variations.combination.*.quantity' => ['nullable', 'integer', function ($attribute, $value, $fail) use ($data) {

                $index = explode('.', $attribute)[2];

                $allowOverselling = $data['item_variations']['combination'][$index]['allow_overselling'] ?? 0;

                //                dump($allowOverselling, $value, $index);
                //                dump("******");
                // if overselling is OFF → quantity must be > 0
                if (! $allowOverselling && $value <= 0) {
                    $fail('The quantity must be greater than 0 when overselling is disabled.');
                }
            }],
            'item_variations.combination.*.allow_overselling' => ['sometimes', 'bool'],

            'item_variations.combination.*.image' => ['sometimes', 'nullable', 'uuid'],
        ], [
            'about.description.required' => $this->tooltip['product_description_html']['EMPTY'] ?? '',
            'about.description.string' => $this->tooltip['product_description_html']['INVALID'] ?? '',

            'about.title.required' => $this->tooltip['product_title']['EMPTY'] ?? '',
            'about.title.string' => $this->tooltip['product_title']['INVALID'] ?? '',

            'about.type.required' => $this->tooltip['product_type']['EMPTY'] ?? '',
            'about.type.string' => $this->tooltip['product_type']['INVALID'] ?? '',
            'about.type.in' => $this->tooltip['product_type']['INVALID'] ?? '',

            'status.required' => $this->tooltip['product_status']['EMPTY'] ?? '',
            'status.string' => $this->tooltip['product_status']['INVALID'] ?? '',
            'status.in' => $this->tooltip['product_status']['INVALID'] ?? '',

            'about.images.required' => $this->tooltip['product_media_urls']['EMPTY'] ?? '',
            'about.images.min' => $this->tooltip['product_media_urls']['INVALID'] ?? '',
            'about.images.*' => $this->tooltip['product_media_urls']['INVALID'] ?? '',

            'about.origin.required' => $this->tooltip['country_of_origin']['EMPTY'] ?? '',
            'about.origin.string' => $this->tooltip['country_of_origin']['INVALID'] ?? '',

            'price_inventory.domestic_price.required' => $this->tooltip['price']['EMPTY'],
            'price_inventory.domestic_price.numeric' => $this->tooltip['price']['INVALID'],
            'price_inventory.domestic_price.gt' => $this->tooltip['price']['INVALID'],

            'item_variations.combination.*.domestic_price.required' => $this->tooltip['price']['EMPTY'] ?? '',
            'item_variations.combination.*.domestic_price.numeric' => $this->tooltip['price']['INVALID'] ?? '',
            'item_variations.combination.*.domestic_price.gt' => $this->tooltip['price']['INVALID'] ?? '',
            'item_variations.combination.*.domestic_price' => $this->tooltip['price']['INVALID'] ?? '',

            //
            'price_inventory.stock_quantity.numeric' => $this->tooltip['quantity']['INVALID'],
            'price_inventory.stock_quantity.required_if' => $this->tooltip['quantity']['EMPTY'],
            //
            'price_inventory.allow_overselling.boolean' => $this->tooltip['continue_selling_when_out_of_stock']['INVALID'],
            // sku will never hit an error so not adding it.
            'price_inventory.weight.required' => $this->tooltip['weight']['EMPTY'],
            'price_inventory.weight.numeric' => $this->tooltip['weight']['INVALID'],
            'price_inventory.weight.gt' => $this->tooltip['weight']['INVALID'],

            'item_variations.combination.*.weight.required' => $this->tooltip['weight']['EMPTY'] ?? '',
            'item_variations.combination.*.weight.numeric' => $this->tooltip['weight']['INVALID'] ?? '',
            'item_variations.combination.*.weight.gt' => $this->tooltip['weight']['INVALID'] ?? '',
            'item_variations.combination.*.weight' => $this->tooltip['weight']['INVALID'] ?? '',

            'price_inventory.unit.required' => $this->tooltip['unit']['EMPTY'],
            'price_inventory.unit.in' => $this->tooltip['unit']['INVALID'],
            'price_inventory.unit.string' => $this->tooltip['unit']['INVALID'],
        ]);
    }

    public function validate($product, int $row, $updateErrors = false): void
    {
        // only_during_update
        if ($product instanceof ImportProductVariant) {
            $this->validateVariants($product->product, $row, $updateErrors);

            return;
        }

        if ($product instanceof ImportProduct && $updateErrors) {
            $product->errors = null;

            $child = ImportProductVariant::where('import_product_id', $product->id)
                ->where('_id', $product->_id)
                ->first();
        }

        $sections = $this->__buildSections($product->sections ?? '');
        $tags = $this->__buildTags($product->tags ?? '');
        $images = $product->media_uuids ?? $this->__buildImages($product->media_urls ?? '');

        $status = $product->status == 'Archive' ? Item::STATUS_ARCHIVED : strtoupper($product->status);
        $allowOverselling = $product->continue_selling_when_out_of_stock === 'Yes' ? 1 : 0;

        $product->media_uuids = $images;

        $product->save();

        // create a array here like below comment
        $data = [
            'status' => $status,
            'about' => [
                'title' => $product->title,
                'type' => strtoupper($product->type),
                'images' => $images,
                'description' => $product->description,
                'origin' => $product->country_of_origin,
                // need to work on this to upload this images to AWS and use those UUIDs here
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
                'allow_overselling' => $allowOverselling,
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

            if (isset($child) && $child) {

                $parentRows = [
                    $row.'_A',
                    $row.'_B',
                    $row.'_C',
                    $row.'_D',
                    $row.'_U',
                ];

                $parentValidationError = $this->__mapErrors($validator->errors()->toArray(), $product, $row);

                $childRowErrors = $child->errors;
                if (! empty($childRowErrors) && $updateErrors) {
                    foreach ($parentRows as $parentRow) {
                        if (isset($child->errors[$parentRow])) {
                            unset($childRowErrors[$parentRow]);
                        }
                    }

                    $child->errors = array_merge($childRowErrors, $parentValidationError);
                } else {
                    $child->errors = $this->__mapErrors($validator->errors()->toArray(), $product, $row);
                }

                $child->save();
            } else {
                $product->errors = $this->__mapErrors($validator->errors()->toArray(), $product, $row);
            }

            $product->is_validated = 1;
            $product->save();

            // dump('validation failed for product: ' . $product->product_id);
        } else {
            $product->errors = null;
            $product->is_validated = 1;
            $product->save();

            // dump('validation successful for product: ' . $product->product_id);
        }
    }

    /**
     * Build sections from the sections string
     *
     * @return string[]
     */
    public function __buildSections(string $sections): array
    {
        $sections = explode(',', $sections);
        $allSections = [];
        // Create or update sections for this store
        foreach ($sections as $sectionName) {
            $sectionName = trim($sectionName);

            if (empty($sectionName)) {
                continue;
            }

            // Check if section exists, if not create it
            $currentSection = Section::firstOrCreate(
                attributes: [
                    'store_id' => $this->store->id,
                    'name' => $sectionName,
                ],
                values: [
                    'order' => 0,
                    'is_visible' => true,
                    'slug' => Str::slug($sectionName),
                ]
            );

            $allSections[] = (string) $currentSection->uuid;
        }

        return $allSections;
    }

    /**
     * Build tags from the tags string
     *
     * @return string[]
     */
    public function __buildTags(string $tags): array
    {
        $allTags = [];

        $tags = explode(',', string: $tags);
        foreach ($tags as $tag) {
            $tag = trim($tag);

            if (empty($tag)) {
                continue;
            }

            $allTags[] = $tag;
        }

        return $allTags;
    }

    /**
     * Build images from the images string
     */
    public function __buildImages(string $image): array
    {
        $images = explode(',', $image);

        $availableImages = true;

        foreach ($images as $imageUrl) {
            if (! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $availableImages = false;
                break;
            }
        }

        if (! $availableImages) {
            return [];
        }

        // returning random string here so not to change the validator rules
        // at this point of time
        return [
            (string) Str::uuid(),
        ];
    }

    public function __validateImage($image): ?string
    {
        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return true;
        }

        return null;
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

    // validate_job
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
            'about.images' => ['required', 'array'],
            'about.images.*' => ['uuid', 'required'],
            // origin
            'about.origin' => ['required', 'string'],
            // Price and inventory section
            'price_inventory.allow_overselling' => ['sometimes', Rule::in(1, 0)],
            'price_inventory.is_global_pricing' => ['required', 'boolean'],
            'price_inventory.domestic_price' => ['required', 'numeric', 'gt:0'],

            'price_inventory.weight' => ['required', 'numeric', 'gt:0'],
            'price_inventory.unit' => ['required', 'string', Rule::in(array_keys(Item::ITEM_WEIGHT))],

            'price_inventory.stock_quantity' => [
                'numeric',
                function ($attribute, $value, $fail) use ($data) {
                    $allowOverselling = data_get($data, 'price_inventory.allow_overselling');
                    // if over selling is 0 that means the stock should always be greater than 1
                    // if over selling is 1 that means the stock should can be anything
                    if ($allowOverselling <= 0) {
                        if (empty($value) || $value <= 0) {
                            $fail('Quantity is required when overselling is disabled.');
                        }
                    }
                },
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
            'item_variations.combination.*.quantity' => ['nullable', 'integer', Rule::when(
                fn ($input) => ! ($input['allow_overselling'] ?? false),
                ['gt:0'],            // overselling OFF → must be > 0
                []                   // overselling ON → allow negative
            )],
            'item_variations.combination.*.weight' => ['nullable', 'numeric', 'min:0'],
            'item_variations.combination.*.is_visible' => ['required', 'bool'],
            'item_variations.combination.*.allow_overselling' => ['sometimes', 'bool'],

            'item_variations.combination.*.image' => ['nullable', 'uuid', 'exists:galleries,uuid'],

            // Vary By Validation
            'item_variations.vary_by' => ['nullable', 'array'],
            'item_variations.vary_by.sku' => ['required_with:item_variations.vary_by', 'boolean'],
            'item_variations.vary_by.quantity' => ['required_with:item_variations.vary_by', 'boolean'],
            'item_variations.vary_by.price' => ['required_with:item_variations.vary_by', 'boolean'],
            'item_variations.vary_by.weight' => ['required_with:item_variations.vary_by', 'boolean'],

        ]);
    }

    public function createProductVariants(ImportProduct $product)
    {
        $sections = $this->__buildSections($product->sections ?? '');
        $tags = $this->__buildTags($product->tags ?? '');
        $status = ($product->status == 'ARCHIVE' || $product->status == 'Archive')
            ? Item::STATUS_ARCHIVED
            : strtoupper($product->status);

        $policy = Policy::query()
            ->where('store_id', $this->store->id)
            ->where('is_default', 1)
            ->first();

        $this->__createProductGallery($product);
        $this->__createProductVariantGallery($product);
        $product->refresh();

        $images = $product->media_uuids;

        $data = [
            'product_id' => $product->product_id ?? null,
            'status' => strtoupper($status),
            'about' => [
                'title' => $product->title,
                'type' => strtoupper($product->type),
                'description' => $product->description,
                'origin' => $product->country_of_origin,
                // need to work on this to upload this images to AWS and use those UUIDs here
                'images' => $images,
                'customizations' => null,
                'is_description_edited' => true,
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
            'policy' => $policy?->uuid ?? null,
            'item_variations' => [
                'vary_by' => [
                    'sku' => 1,
                    'quantity' => 1,
                    'price' => 1,
                    'weight' => 1,
                ],
                'variants' => null,
                'combination' => null,
            ],
        ];

        $productVariantsAndOptions = [];

        if ($this->isVariantExists($product, 'option1_name')) {
            $labelOne = array_unique(array_filter($product->variants->pluck('option1_name')->toArray()));
            $productVariantsAndOptions[] = [
                'name' => $labelOne ? $labelOne[0] : null,
                'options' => $product->variants
                    ->pluck('option1_value')
                    ->filter()                    // remove empty/null
                    ->map(fn ($v) => strtolower($v))
                    ->values()
                    ->toArray(),
            ];
        }

        if ($this->isVariantExists($product, 'option2_name')) {
            $labelTwo = array_unique(array_filter($product->variants->pluck('option2_name')->toArray()));
            $productVariantsAndOptions[] = [
                'name' => $labelTwo ? $labelTwo[0] : null,
                'options' => $product->variants
                    ->pluck('option2_value')
                    ->filter()                    // remove empty/null
                    ->map(fn ($v) => strtolower($v))
                    ->values()
                    ->toArray(),
            ];
        }

        if ($this->isVariantExists($product, 'option3_name')) {
            $labelThree = array_unique(array_filter($product->variants->pluck('option3_name')->toArray()));
            $productVariantsAndOptions[] = [
                'name' => $labelThree ? $labelThree[0] : null,
                'options' => $product->variants
                    ->pluck('option3_value')
                    ->filter()                    // remove empty/null
                    ->map(fn ($v) => strtolower($v))
                    ->values()
                    ->toArray(),
            ];
        }

        $data['item_variations']['variants'] = $productVariantsAndOptions;

        $rows = [];
        $dimensionMap = [];
        $product->load('variants');

        foreach ($product->variants as $variant) {
            if ($variant->image && $variant->image != '') {
                $imageUUID = $variant->image_uuid ?? null;
            }

            $combinations = [];

            $combinations[] = [
                'type' => $variant->option1_name,
                'value' => strtolower($variant->option1_value),
            ];

            $dimensionMap[$variant->option1_name][] = strtolower($variant->option1_value);

            if ($variant->option2_name && $variant->option2_name != '') {
                $combinations[] = [
                    'type' => $variant->option2_name,
                    'value' => strtolower($variant->option2_value),
                ];
                $dimensionMap[$variant->option2_name][] = strtolower($variant->option2_value);
            }

            if ($variant?->option3_name && $variant?->option3_name != '') {
                $combinations[] = [
                    'type' => $variant->option3_name,
                    'value' => strtolower($variant->option3_value),
                ];
                $dimensionMap[$variant->option3_name][] = strtolower($variant->option3_value);
            }

            $rows[] = [
                'variants' => $combinations,
                'is_visible' => $variant->hide_variant == 'Yes' ? 0 : 1,
                'image' => $imageUUID ?? null,
                'sku' => $variant->sku ?? null,
                'domestic_price' => $variant->price ?? null,
                'weight' => $variant->weight ?? null,
                'quantity' => $variant->quantity ?? null,
                'allow_overselling' => in_array($variant->continue_selling_when_out_of_stock, ['Yes', 1]) ? 1 : 0,
            ];
        }

        // Unique variant values
        foreach ($dimensionMap as $name => $values) {
            $dimensionMap[$name] = array_values(array_unique(array_filter($values)));
        }

        $allCombinations = $this->cartesian($dimensionMap);

        $existingKeys = collect($rows)
            ->map(fn ($row) => collect($row['variants'])->pluck('value')->implode('|'))
            ->toArray();

        $missing = [];

        foreach ($allCombinations as $combo) {
            $key = collect($combo)->pluck('value')->implode('|');

            if (! in_array($key, $existingKeys)) {
                $missing[] = [
                    'variants' => $combo,
                    'is_visible' => 0,
                    'image' => null,
                    'sku' => null,
                    'domestic_price' => 0,
                    'weight' => 0,
                    'quantity' => 0,
                    'allow_overselling' => 0,
                ];
            }
        }

        $rows = array_merge($rows, $missing);

        $data['item_variations']['combination'] = $rows;

        $existingProduct = ItemCsv::with('item')
            ->where('product_id', $product?->product_id)
            ->where('store_id', $this->store->id)
            ->whereNotNull('item_id')
            ->first();

        $this->productService->setStore($this->store);

        if ($existingProduct) {
            $this->productService->updateProduct($existingProduct->item, $data);
        } else {
            $this->productService->createProduct($data);
        }

    }

    public function cartesian($input)
    {
        $result = [[]];

        foreach ($input as $type => $values) {
            $new = [];
            foreach ($result as $combo) {
                foreach ($values as $value) {
                    $new[] = array_merge($combo, [
                        ['type' => $type, 'value' => $value],
                    ]);
                }
            }
            $result = $new;
        }

        return $result;
    }

    public function createProduct(ImportProduct $product): void
    {
        $sections = $this->__buildSections($product->sections ?? '');
        $tags = $this->__buildTags($product->tags ?? '');
        $status = ($product->status == 'ARCHIVE' || $product->status == 'Archive')
            ? Item::STATUS_ARCHIVED
            : strtoupper($product->status);

        $policy = Policy::query()
            ->where('store_id', $this->store->id)
            ->where('is_default', 1)
            ->first();

        $this->__createProductGallery($product);
        $product->refresh();

        $data = [
            'product_id' => $product->product_id ?? null,
            'status' => strtoupper($status),
            'about' => [
                'title' => $product->title,
                'type' => strtoupper($product->type),
                'description' => $product->description,
                'origin' => $product->country_of_origin,
                // need to work on this to upload this images to AWS and use those UUIDs here
                'images' => $product->media_uuids,
                'customizations' => null,
                'is_description_edited' => true,
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
            'policy' => $policy?->uuid ?? null,
            'item_variations' => null,
        ];

        $existingProduct = ItemCsv::with('item')
            ->where('product_id', $product?->product_id)
            ->where('store_id', $this->store->id)
            ->whereNotNull('item_id')
            ->first();

        $this->productService->setStore($this->store);

        if ($existingProduct) {
            $this->productService->updateProduct($existingProduct->item, $data);
        } else {
            $this->productService->createProduct($data);
        }
    }

    public function __createProductVariantGallery($product): void
    {
        // fetch the product and its variant
        $variants = ImportProductVariant::where('import_product_id', $product->id)->get();

        $uuids = [];

        foreach ($variants as $variant) {
            if ($variant->image && $variant->image != '') {
                $images = explode(',', $variant->image ?? '');

                $gallery = Gallery::updateOrCreate(
                    [
                        'store_id' => $this->store->id,
                        'url' => $images[0],
                    ],
                    [
                        'type' => 'image',
                    ]
                );

                $variant->image_uuid = $gallery->uuid;
                $variant->save();
            }
        }

    }

    public function __createProductGallery($product, $isVariantImage = false): void
    {
        $images = explode(',', $product->media_urls ?? '');

        $uuids = [];

        foreach ($images as $imageUrl) {
            $gallery = Gallery::updateOrCreate(
                [
                    'store_id' => $this->store->id,
                    'url' => $imageUrl,
                ],
                [
                    'type' => 'image',
                ]
            );

            $uuids[] = $gallery->uuid;
        }

        $product->media_uuids = $uuids;
        $product->save();
    }

    /**
     * Update type to IMAGE for galleries with given UUIDs
     *
     * @param  array  $uuids  Array of gallery UUIDs
     * @return int Number of galleries updated
     */
    public function updateGalleryTypesToImage(array $uuids): int
    {
        if (empty($uuids)) {
            return 0;
        }

        return Gallery::whereIn('uuid', $uuids)
            ->update(['type' => Gallery::TYPE_IMAGE]);
    }

    public function loadToolTips(): ImportService
    {
        $path = app_path('Services/Import/tooltips.json');

        $this->tooltip = json_decode(file_get_contents($path), true);

        return $this;
    }
}
