<?php

namespace App\Http\Controllers\Misc;

use App\Http\Controllers\Controller;
use App\Http\Resources\Misc\MiscResource;
use App\Models\Misc\Currency;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class CurrencyListController extends Controller
{
    #[OA\Get(
        path: '/api/misc/currency',
        summary: 'List all currencies',
        security: [['sellerAuth' => []]],
        tags: ['Reference Data'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Currency list',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Misc'))]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function __invoke(): AnonymousResourceCollection
    {
        $currencies = Cache::remember('misc.currencies', now()->addDay(), function () {
            return Currency::query()->select(['id', 'name'])->get();
        });

        return MiscResource::collection($currencies);
    }
}
