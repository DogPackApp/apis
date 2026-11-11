<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Models\Chat\Thread;
use App\Models\Store\Store;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class UnBlockThreadController extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke(Request $request, Store $store, string $uuid)
    {
        try {
            $validator = Validator::make(['uuid' => $uuid], [
                'uuid' => ['required', 'uuid'],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors(), Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $thread = Thread::where('uuid', $uuid)
                ->where('seller_id', $store->uuid)
                ->first();

            if (! $thread) {
                return $this->errorResponse(
                    ['message' => 'Thread not found or not assigned to this store.'],
                    Response::HTTP_NOT_FOUND
                );
            }

            if ($thread->is_active) {
                return $this->errorResponse(
                    ['message' => 'Thread is already active.'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $thread->is_active = true;
            $thread->save();

            return $this->successResponse('Thread unblocked successfully.');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
