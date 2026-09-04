<?php

namespace App\Http\Controllers\Misc;

use App\Http\Controllers\Controller;
use App\Http\Resources\Misc\MiscResource;
use App\Models\Misc\Language;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class LanguageListController extends Controller
{
    #[OA\Get(
        path: '/api/misc/language',
        summary: 'List all languages',
        security: [['sellerAuth' => []]],
        tags: ['Reference Data'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Language list',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Misc'))]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function __invoke(): AnonymousResourceCollection
    {
        $languages = Cache::remember('misc.languages', now()->addDay(), function () {
            return Language::query()->select(['id', 'name'])->get();
        });

        return MiscResource::collection($languages);
    }
}
