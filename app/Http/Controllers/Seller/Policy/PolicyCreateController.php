<?php

namespace App\Http\Controllers\Seller\Policy;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\PolicyResource;
use App\Services\OnboardingService;
use App\Services\Store\PolicyService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class PolicyCreateController extends Controller
{
    public function __invoke(Request $request, PolicyService $policyService): JsonResponse
    {
        if ($policyService->policyCountByStore() >= 4) {
            return response()->json(['errors' => 'Cannot Create More than 4 Policies'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255'],
                'is_exchange' => ['nullable', 'boolean'],
                'is_return' => ['nullable', 'boolean'],
                'is_default' => ['nullable', 'boolean'],
                'time' => ['nullable', 'integer', 'required_if:is_exchange,true', 'required_if:is_return,true'],
                'custom_text' => ['nullable', 'string', 'max:1000'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $policyService->create($validator->validated());

            if ($policyService->isFirstPolicy()) {
                (new OnboardingService)->completeStoreSetting(auth()->user());
            }

            DB::commit();

            return PolicyResource::make($policyService->policy)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);

        } catch (Exception $e) {
            DB::rollBack();

            if (app()->environment() !== 'production') {
                $message = $e;
            } else {
                $message = 'Something went wrong';
            }

            return response()->json(['errors' => ['message' => $message]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
