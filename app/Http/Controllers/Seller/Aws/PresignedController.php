<?php

namespace App\Http\Controllers\Seller\Aws;

use App\Http\Controllers\Controller;
use App\Models\Product\Gallery;
use App\Models\Store\Store;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PresignedController extends Controller
{
    public ?Store $store;

    public function __construct(?Store $store = null)
    {
        $this->store = $store;
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'count' => ['required', 'integer', 'min:1', 'max:20'],
                'module' => ['sometimes', 'string', 'in:store,product'],
                'type' => ['required_if:module,store', 'in:cover,profile'],
                'data' => ['sometimes', 'array'],
                'uuid' => ['uuid'],
            ]);

            $disk = Storage::disk('mps3');
            $uploadUrls = [];

            if ($request->input('module') == 'store') {
                $this->store = $this->store ?? Store::query()->where('uuid', $request->input('uuid'))->first();

                $fileName = "uploads/store/{$this->store->uuid}/".uniqid();

                $presignedUrl = $disk->temporaryUploadUrl(
                    $fileName,
                    now()->addMinutes(30),
                    [
                        'ACL' => 'private',
                    ]
                );

                $uploadUrls[] = $presignedUrl['url'];

                if ($request->input('type') == 'profile') {
                    $this->store->image = config('filesystems.disks.mps3.path').$fileName;
                } else {
                    $this->store->cover_image = config('filesystems.disks.mps3.path').$fileName;
                }

                $this->store->save();
            } else {
                $galleries = [];

                foreach ($request->input('data') as $index => $gallery) {
                    $fileName = "uploads/products/{$this->store->uuid}/".uniqid().$index;

                    $presignedUrl = $disk->temporaryUploadUrl(
                        $fileName,
                        now()->addMinutes(30),
                        [
                            'ACL' => 'private',
                        ]
                    );

                    $uploadUrls[] = $presignedUrl['url'];

                    $galleries[] = [
                        'url' => config('filesystems.disks.mps3.path').$fileName,
                        'store_id' => $this->store->id,
                        'type' => $gallery['type'] ?? Gallery::TYPE_IMAGE,
                        'name' => $gallery['name'] ?? null,
                        'uuid' => (string) Str::uuid(),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ];
                }

                Gallery::insert($galleries);
            }

            return response()->json(['urls' => $uploadUrls]);
        } catch (Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }
}
