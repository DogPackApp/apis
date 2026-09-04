<?php

namespace App\Http\Controllers\Misc;

use App\Http\Controllers\Controller;
use App\Http\Resources\Misc\CountryResource;
use App\Models\Misc\Country;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class CountryListController extends Controller
{
    #[OA\Get(
        path: '/api/misc/country',
        summary: 'List all countries',
        security: [['sellerAuth' => []]],
        tags: ['Reference Data'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Country list, ordered by display order then name',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Country'))]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function __invoke(): AnonymousResourceCollection
    {
        $countries = Cache::remember('misc.countries', now()->addDay(), function () {
            return Country::query()
                ->orderBy('order')
                ->orderBy('name')
                ->get();
        });

        return CountryResource::collection($countries);
    }
}
