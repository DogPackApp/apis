<?php

namespace App\Http\Controllers\Seller\Policy;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\PolicyResource;
use App\Models\Store\Policy;
use App\Services\Store\PolicyService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class PolicyUpdateController extends Controller
{
    public function __invoke(Request $request, Policy $policy, PolicyService $policyService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255'],
            'is_exchange' => ['nullable', 'boolean'],
            'is_return' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'time' => ['nullable', 'integer', 'required_if:is_exchange,true', 'required_if:is_return,true'],
            'custom_text' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            if (isset($validator->validated()['is_default'])
                && $validator->validated()['is_default'] === false
                && $policy->is_default === 1) {
                return response()->json(
                    ['errors' => 'You must have at least one default policy. Please set another default policy before removing this one.'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $policyService->update($validator->validated(), $policy);

            DB::commit();

            return PolicyResource::make($policyService->policy)
                ->response()
                ->setStatusCode(Response::HTTP_OK);

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
