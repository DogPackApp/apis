<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Models\Chat\SellerChatReport;
use App\Models\Chat\Thread;
use App\Models\Store\Store;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ReportThreadController extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke(Request $request, Store $store, string $uuid)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => ['required', 'string'],
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

            DB::beginTransaction();

            SellerChatReport::create([
                'thread_uuid' => $thread->uuid,
                'reason' => $validator->validated()['reason'],
            ]);

            $thread->is_active = false;
            $thread->save();

            DB::commit();

            return $this->successResponse('Thread reported successfully.', Response::HTTP_CREATED);
        } catch (Exception $e) {
            DB::rollBack();

            return $this->handleException($e);
        }
    }
}
