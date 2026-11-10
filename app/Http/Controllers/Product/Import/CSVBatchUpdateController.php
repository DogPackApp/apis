<?php

namespace App\Http\Controllers\Product\Import;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Models\Product\ImportProduct;
use App\Models\Product\ImportProductVariant;
use App\Models\Store\Store;
use App\Services\Import\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CSVBatchUpdateController extends Controller
{
    use RespondsWithSellerJson;

    public $tooltips = null;

    public function __invoke(Request $request, ImportService $importService): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_id' => ['required', 'string'],
                'row' => ['required', 'integer', 'min:1'],
                'column' => ['required', 'string'],
                'oldData' => ['nullable'],
                'newData' => ['nullable', 'string'],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors(), 422);
            }

            $importService->loadToolTips();
            $this->tooltips = $importService->tooltip;

            if ($request->column === 'country_of_origin') {
                $product = $this->_shortcutSimpleUpdates();

                return $this->emptySuccessResponse();
            }

            if ($request->column === 'product_media_urls') {
                $product = $this->_shortcutForImages();

                return $this->emptySuccessResponse();
            }

            if ($request->column === 'variant_image') {
                $product = $this->_shortcutForImageVariant();

                return $this->emptySuccessResponse();
            }

            if ($request->column === 'continue_selling_when_out_of_stock') {
                $product = $this->_shortcutForContinueSelling();

                return $this->emptySuccessResponse();
            }

            if ($request->column === 'sku') {
                $product = $this->_shortcutSimpleUpdatesForSKU();

                return $this->emptySuccessResponse();
            }

            if ($request->column === 'product_type') {
                $product = $this->_shortcutSimpleUpdatesForType();

                return $this->emptySuccessResponse();
            }

            if ($request->column === 'product_status') {
                $product = $this->_statusUpdate();

                return $this->emptySuccessResponse();
            }

            if ($request->column === 'product_description_html') {
                $product = $this->_shortcutSimpleUpdatesForDescription();

                return $this->emptySuccessResponse();
            }

            if (in_array($request->column, ['product_title', 'product_description_html', 'product_type', 'product_media_urls', 'country_of_origin'], true)) {
                $product = ImportProduct::where('batch_id', $request->batchId)
                    ->withCount('variants')
                    ->where('product_id', $request->product_id)
                    ->first();
            } else {
                $product = $this->identifyParentOrChild($request->row, $request->batchId);
            }

            if (! $product) {
                $product = ImportProduct::where('batch_id', $request->batchId)
                    ->withCount('variants')
                    ->where('product_id', $request->product_id)
                    ->first();

                if (! $product) {
                    return $this->errorResponse('Product not found', 404);
                }

                if ($product->variants_count > 0) {
                    $productVariant = ImportProductVariant::where('import_product_id', $product->id)
                        ->where('_id', $request->row)
                        ->first();

                    if (array_key_exists($request->column, $productVariant->getAttributes()) && ($product->_id != $request->row || $product->variants_count > 0)) {
                        $product = $productVariant;
                    }
                }

            }

            if ($validator->validated() && $validator->validated()['newData'] !== $validator->validated()['oldData']) {
                $column = $validator->validated()['column'];

                if ($column == 'product_description_html') {
                    $column = 'description';
                }

                if ($column == 'product_title') {
                    $column = 'title';
                }

                if ($validator->validated()['column'] === 'product_status') {
                    $column = 'status';
                }

                $product->update([
                    $column => $request->newData,
                ]);

                if ($column === 'unit' && $product instanceof ImportProductVariant) {
                    $product->loadMissing(['product']);
                    if ($product->product) {
                        $product->product->update([
                            'unit' => $request->newData ?? '',
                        ]);
                    }
                }
            }

            $importService->setStore(resolve(Store::class));

            if ($product instanceof ImportProductVariant) {
                $importService->validateVariants($product->product, $product->_id, true);
            } else {
                $importService->validate($product, $product->_id, true);
            }

            return $this->emptySuccessResponse();

        } catch (\Exception $e) {
            dd($e);

            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    private function identifyParentOrChild($row, $batchId)
    {
        $currentProduct = null;

        ImportProduct::query()
            ->where('batch_id', $batchId)
            ->each(function (ImportProduct $product) use ($row, &$currentProduct) {
                $child = ImportProductVariant::query()
                    ->where('import_product_id', $product->id)
                    ->where('_id', $row)
                    ->first();

                if ($child) {
                    $currentProduct = $child;
                }
            });

        return $currentProduct;
    }

    private function _shortcutSimpleUpdates()
    {
        $product = ImportProduct::where('batch_id', request()->batchId)
            ->withCount('variants')
            ->where('product_id', request()->product_id)
            ->first();

        if (! $product) {
            return false;
        }

        $product->country_of_origin = request()->newData;
        $product->save();

        if ($product->variants_count > 0) {
            $productVariant = ImportProductVariant::where('import_product_id', $product->id)
                ->where('_id', request()->row)
                ->first();

            if (! $productVariant) {
                return false;
            }

            if ($product->country_of_origin === '' || $product->country_of_origin === null) {
                $productVariant->errors = array_merge($productVariant?->errors ?? [], [
                    $productVariant->_id.'_U' => $this->tooltips['country_of_origin']['EMPTY'] ?? '',
                ]);

                $productVariant->save();
            } else {

                $errors = $productVariant->errors;
                if (isset($errors[$product->_id.'_U'])) {

                    unset($errors[$product->_id.'_U']);

                    if (empty($errors)) {
                        $productVariant->errors = null;
                    } else {
                        $productVariant->errors = $errors;
                    }

                    $productVariant->save();
                }
            }
        }

        if ($product->country_of_origin === '' || $product->country_of_origin === null) {
            $err = $product?->errors ?? [];

            $product->errors = array_merge($err ?? [], [
                $product->_id.'_U' => $this->tooltips['country_of_origin']['EMPTY'] ?? '',
            ]);

            $product->save();
        } else {

            $errors = $product->errors;
            if (isset($errors[$product->_id.'_U'])) {

                unset($errors[$product->_id.'_U']);

                if (empty($errors)) {
                    $product->errors = null;
                } else {
                    $product->errors = $errors;
                }

                $product->save();
            }
        }

        return true;
    }

    private function _shortcutSimpleUpdatesForSKU()
    {
        $product = ImportProduct::where('batch_id', request()->batchId)
            ->withCount('variants')
            ->where('product_id', request()->product_id)
            ->first();

        if (! $product) {
            return false;
        }

        $product->sku = request()->newData;
        $product->save();

        return true;
    }

    private function _shortcutForImages()
    {
        $product = ImportProduct::where('batch_id', request()->batchId)
            ->withCount('variants')
            ->where('product_id', request()->product_id)
            ->first();

        $images = explode(',', request()->newData);

        $images = array_map(function ($image) {
            return trim(urldecode($image));
        }, $images);

        $product->media_urls = request()->newData;
        $product->save();
        if ($product->variants_count > 0) {
            $this->_shortcutForImagesVariations($product, $images);

            return;
        }

        if (count($images) > 0) {
            $uuids = [];

            foreach ($images as $image) {
                if (! filter_var(trim($image), FILTER_VALIDATE_URL)) {
                    $product->errors = array_merge($err ?? [], [
                        $product->_id.'_D' => $this->tooltips['product_description_html']['INVALID'] ?? '',
                    ]);
                    $product->save();
                    break;
                    // error out
                } else {
                    $uuids[] = (string) Str::uuid();
                }
            }

            if (count($uuids) > 0) {

                $product->media_uuids = $uuids;
                $product->save();

                $errors = $product->errors;

                if (isset($errors[$product->_id.'_D'])) {

                    unset($errors[$product->_id.'_D']);

                    if (empty($errors)) {
                        $product->errors = null;
                    } else {
                        $product->errors = $errors;
                    }

                    $product->save();

                    return true;
                }

            }
        }

        $err = $product?->errors ?? [];

        $product->errors = array_merge($err ?? [], [
            $product->_id.'_D' => $this->tooltips['product_description_html']['INVALID'] ?? '',
        ]);
        $product->save();

        return true;
    }

    private function _shortcutForImageVariant()
    {
        $product = ImportProduct::where('batch_id', request()->batchId)
            ->withCount('variants')
            ->where('product_id', request()->product_id)
            ->first();

        $productVariant = ImportProductVariant::where('import_product_id', $product->id)
            ->where('_id', request()->row)
            ->first();

        if (! $productVariant) {
            return false;
        }

        if ($product->variants_count <= 0) {
            return true;
        }

        $productVariant->image = request()->newData;
        $productVariant->save();

        if ($productVariant->image === '' || $productVariant->image === null) {
            $productVariant->errors = array_merge($productVariant?->errors ?? [], [
                $productVariant->_id.'_N' => $this->tooltips['variant_image']['INVALID'] ?? '',
            ]);

            $productVariant->save();

            return true;
        }

        if (! filter_var(request()->newData, FILTER_VALIDATE_URL)) {
            $productVariant->errors = array_merge($productVariant?->errors ?? [], [
                $productVariant->_id.'_N' => $this->tooltips['variant_image']['INVALID'] ?? '',
            ]);

            $productVariant->save();

            return true;
        }

        $errors = $productVariant->errors;

        if (isset($errors[$product->_id.'_N'])) {

            if (isset($errors[$product->_id.'_N'])) {

                unset($errors[$product->_id.'_N']);

                if (empty($errors)) {
                    $productVariant->errors = null;
                } else {
                    $productVariant->errors = $errors;
                }

                $productVariant->save();
            }
        }

        return true;
    }

    private function _shortcutForImagesVariations($product, $images)
    {
        if (count($images) > 0) {
            $uuids = [];

            $productVariant = ImportProductVariant::where('import_product_id', $product->id)
                ->where('_id', request()->row)
                ->first();

            foreach ($images as $image) {
                if (! filter_var(trim($image), FILTER_VALIDATE_URL)) {

                    $productVariant->errors = array_merge($productVariant?->errors ?? [], [
                        $product->_id.'_D' => $this->tooltips['variant_image']['EMPTY'] ?? '',
                    ]);

                    $productVariant->save();

                    return true;
                }
            }

            foreach ($images as $image) {
                $uuids[] = Str::uuid();
            }

            if (count($uuids) > 0) {

                $product->media_uuids = $uuids;
                $product->save();

                $errors = $productVariant->errors;

                if (isset($errors[$product->_id.'_D'])) {
                    unset($errors[$product->_id.'_D']);

                    if (empty($errors)) {
                        $productVariant->errors = null;
                    } else {
                        $productVariant->errors = $errors;
                    }

                    $productVariant->save();
                }

                return true;
            }
        }

        $productVariant = ImportProductVariant::where('import_product_id', $product->id)
            ->where('_id', request()->row)
            ->first();

        $errors = $productVariant->errors;

        if (isset($errors[$product->_id.'_D'])) {

            if (isset($errors[$product->_id.'_D'])) {

                unset($errors[$product->_id.'_D']);

                if (empty($errors)) {
                    $productVariant->errors = null;
                } else {
                    $productVariant->errors = $errors;
                }

                $productVariant->save();
            }
        }

        return true;
    }

    private function _shortcutForContinueSelling()
    {
        $product = ImportProduct::where('batch_id', request()->batchId)
            ->withCount('variants')
            ->where('product_id', request()->product_id)
            ->first();

        if ($product->variants_count > 0) {
            $productVariant = ImportProductVariant::where('import_product_id', $product->id)
                ->where('_id', request()->row)
                ->first();

            $productVariant->continue_selling_when_out_of_stock = request()->newData === 'Yes';
            $productVariant->save();
        } else {
            $product->continue_selling_when_out_of_stock = request()->newData === 'Yes';
            $product->save();
        }
    }

    private function _statusUpdate()
    {
        $product = ImportProduct::where('batch_id', request()->batchId)
            ->withCount('variants')
            ->where('product_id', request()->product_id)
            ->first();

        if (! $product) {
            return false;
        }

        $product->status = request()->newData;
        $product->save();

        if ($product->variants_count > 0) {
            $productVariant = ImportProductVariant::where('import_product_id', $product->id)
                ->where('_id', request()->row)
                ->first();

            if (! $productVariant) {
                return false;
            }

            if ($product->status === '' || $product->status === null) {
                $productVariant->errors = array_merge($productVariant?->errors ?? [], [
                    $productVariant->_id.'_X' => $this->tooltips['product_status']['EMPTY'] ?? '',
                ]);

                $product->save();
            } else {

                $errors = $productVariant->errors;
                if (isset($errors[$product->_id.'_X'])) {

                    unset($errors[$product->_id.'_X']);

                    if (empty($errors)) {
                        $productVariant->errors = null;
                    } else {
                        $productVariant->errors = $errors;
                    }

                    $productVariant->save();
                }
            }
        } else {
            $errors = $product->errors;

            if (isset($errors[$product->_id.'_X'])) {

                if (isset($errors[$product->_id.'_X'])) {

                    unset($errors[$product->_id.'_X']);

                    if (empty($errors)) {
                        $product->errors = null;
                    } else {
                        $product->errors = $errors;
                    }

                    $product->save();
                }
            }
        }

        return true;
    }

    private function _shortcutSimpleUpdatesForType()
    {
        $product = ImportProduct::where('batch_id', request()->batchId)
            ->withCount('variants')
            ->where('product_id', request()->product_id)
            ->first();

        if (! $product) {
            return false;
        }

        $product->type = request()->newData;
        $product->save();

        if ($product->variants_count > 0) {
            $productVariant = ImportProductVariant::where('import_product_id', $product->id)
                ->where('_id', request()->row)
                ->first();

            if (! $productVariant) {
                return false;
            }

            if ($product->type === '' || $product->type === null) {
                $productVariant->errors = array_merge($productVariant?->errors ?? [], [
                    $productVariant->_id.'_C' => $this->tooltips['product_type']['EMPTY'] ?? '',
                ]);

                $productVariant->save();
            } else {
                $errors = $productVariant->errors;
                if (isset($errors[$product->_id.'_C'])) {

                    unset($errors[$product->_id.'_C']);

                    if (empty($errors)) {
                        $productVariant->errors = null;
                    } else {
                        $productVariant->errors = $errors;
                    }

                    $productVariant->save();
                    $product->save();
                }
            }
        }

        return true;
    }

    private function _shortcutSimpleUpdatesForDescription()
    {
        $product = ImportProduct::where('batch_id', request()->batchId)
            ->withCount('variants')
            ->where('product_id', request()->product_id)
            ->first();

        if (! $product) {
            return false;
        }

        $product->description = request()->newData;
        $product->save();

        if ($product->variants_count > 0) {
            $productVariant = ImportProductVariant::where('import_product_id', $product->id)
                ->where('_id', request()->row)
                ->first();

            if (! $productVariant) {
                return false;
            }

            if ($product->description === '' || $product->description === null) {
                $productVariant->errors = array_merge($productVariant?->errors ?? [], [
                    $productVariant->_id.'_E' => $this->tooltips['product_description_html']['EMPTY'] ?? '',
                ]);

                $productVariant->save();
            } else {

                $errors = $productVariant->errors;

                if (isset($errors[$product->_id.'_E'])) {

                    unset($errors[$product->_id.'_E']);

                    if (empty($errors)) {
                        $productVariant->errors = null;
                    } else {
                        $productVariant->errors = $errors;
                    }

                    $productVariant->save();
                    $product->save();
                }
            }
        }

        return true;
    }
}
