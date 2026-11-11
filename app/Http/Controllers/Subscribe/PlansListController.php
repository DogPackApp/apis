<?php

namespace App\Http\Controllers\Subscribe;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Services\Subscribe\PlanService;
use Illuminate\Http\Request;

class PlansListController extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke(Request $request)
    {
        try {
            $plans = (new PlanService)->getAllPlans();
            if (! isset($plans) || empty($plans)) {
                return $this->errorResponse('No plans found', 404);
            }

            return $this->successResponseWithData(array_values($plans), 'Plans fetched successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(config('app.env') === 'Production' ? 'Something went wrong.' : $e->getMessage(), 500);
        }
    }
}
