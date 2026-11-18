<?php

namespace App\Http\Controllers\Seller\Policy;

use App\Http\Controllers\Controller;
use App\Models\Store\Policy;
use App\Services\Store\PolicyService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PolicyToggleDefaultController extends Controller
{
    public function __invoke(Request $request, Policy $policy, PolicyService $policyService): JsonResponse
    {
        DB::beginTransaction();

        try {
            $policyService->setDefault($policy);

            DB::commit();

            return response()->json([], Response::HTTP_OK);
        } catch (Exception $e) {
            DB::rollBack();

            if (app()->environment() !== 'production') {
                $message = $e->getMessage();
            } else {
                $message = 'Something went wrong';
            }

            return response()->json(['errors' => ['message' => $message]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
