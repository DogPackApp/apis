<?php

namespace App\Http\Controllers\Chat;

use App\Models\Chat\Thread;
use App\Models\Seller\Seller;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class InitiateMessageController
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'seller_id' => ['required', 'uuid', 'exists:sellers,uuid'],
                'buyer_id' => ['required', 'uuid', 'dp_users,uuid'],
                'initiated_by' => ['nullable', Rule::in(Thread::INITIATOR)],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $seller = Seller::where('uuid', $request->seller_id)->firstOrFail();
            $buyer = User::where('uuid', $request->buyer_id)->firstOrFail();

            $thread = Thread::query()
                ->where('buyer_id', $buyer->id)
                ->where('seller_id', $seller->id)
                ->first();

            if ($thread) {
                return response()->json([
                    'message' => 'Thread already exists',
                    'thread' => $thread,
                ], 200);
            }

            $thread = Thread::create([
                'buyer_id' => $buyer->id,
                'seller_id' => $seller->id,
                'initiated_by' => $request->initiated_by ?? 'buyer',
            ]);

            return response()->json([
                'message' => 'Thread created successfully',
                'thread' => $thread,
            ], 201);
        } catch (Exception $e) {
            $message = app()->environment() !== 'production'
                ? $e->getMessage()
                : 'Something went wrong';

            return response()->json([
                'errors' => ['message' => $message],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
