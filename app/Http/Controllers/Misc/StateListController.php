<?php

namespace App\Http\Controllers\Misc;

use App\Http\Controllers\Controller;
use App\Http\Requests\Misc\StateListRequest;
use App\Http\Resources\Misc\StateResource;
use App\Models\Misc\State;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class StateListController extends Controller
{
    #[OA\Get(
        path: '/api/misc/state',
        summary: 'List states for a country',
        security: [['sellerAuth' => []]],
        tags: ['Reference Data'],
        parameters: [
            new OA\Parameter(
                name: 'country_id',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 38)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'States for the given country, ordered by name',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/State'))]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'country_id is missing or not an integer'),
        ]
    )]
    public function __invoke(StateListRequest $request): AnonymousResourceCollection
    {
        $countryId = (int) $request->validated('country_id');

        $states = Cache::remember("misc.states.{$countryId}", now()->addDay(), function () use ($countryId) {
            return State::query()
                ->where('country_id', $countryId)
                ->orderBy('name')
                ->get(['id', 'name', 'short_name'])
                ->toArray();
        });

        return StateResource::collection(collect($states)->map(fn (array $state) => (object) $state));
    }
}
