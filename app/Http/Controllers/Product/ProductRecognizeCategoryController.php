<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Category;
use App\Models\Product\Item;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class ProductRecognizeCategoryController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_name' => 'required|string|max:255',
                'description' => 'required|string',
                'product_type' => 'required|string|in:'.Item::TYPE_PHYSICAL.','.Item::TYPE_DIGITAL,
                'keywords' => 'nullable|string',
            ], [
                'product_name.max' => 'The product name cannot be more than 255 characters.',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $isPhysicalProduct = ($request->product_type == Item::TYPE_PHYSICAL);

            $categoriesFilePath = $isPhysicalProduct
                ? resource_path('marketplace/physicalCategoriesPrompt.json')
                : resource_path('marketplace/digitalCategoriesPrompt.json');

            if (! is_file($categoriesFilePath)) {
                return response()->json(['errors' => 'Categories is missing.'], 422);
            }
            $json = file_get_contents($categoriesFilePath);
            $categories = json_decode($json, true);

            if (! is_array($categories) || empty($categories)) {
                return response()->json(['errors' => 'Categories are invalid.'], 422);
            }

            $system = implode(' ', [
                'You are an extremely strict and literal classifier.',
                'You must select exactly ONE object from the provided categories array that most accurately matches the product, based solely on the given information.',
                'Return ONLY the exact object as it appears in the array, with no modifications, additions, or omissions. Return the chosen object verbatim (same id/uuid/full_path).',
                'Do NOT invent, infer, or fabricate any values, keys, or objects. Do NOT create new categories or alter existing ones.',
                'Use the full_path property to determine the most semantically appropriate match, strictly following the provided hierarchy.',
                'If more than one category could match, always select the HIGHEST-LEVEL (most general) parent category among the valid options.',
                'Your response MUST be a single, valid JSON object matching the schema. Do not include any explanation, commentary, or extra text.',
            ]);

            $userBlocks = [
                [
                    'type' => 'input_text',
                    'text' => "CATEGORIES JSON:\n".json_encode($categories, JSON_UNESCAPED_SLASHES),
                ],
                [
                    'type' => 'input_text',
                    'text' => "PRODUCT NAME: {$request->product_name}\n".
                        'DESCRIPTION: '.substr($request->description, 0, 2000)."\n".
                        (! empty($request->keywords) ? "KEYWORDS: {$request->keywords}\n" : '').
                        'Return JSON only.',
                ],
            ];

            $schema = [
                'name' => 'category_selection',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'full_path' => ['type' => 'string'],
                    ],
                    'required' => ['id', 'full_path'],
                    'additionalProperties' => false,
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.config('services.open_ai.api_key'),
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/responses', [
                'model' => 'gpt-4o-mini',
                'input' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $userBlocks],
                ],
                'text' => ['format' => [
                    'type' => 'json_schema',
                    ...$schema,
                ]],
                'max_output_tokens' => 100,
            ]);

            if (! $response->successful()) {
                return response()->json(['errors' => 'Failed to classify product'], 422);
            }

            $data = $response->json();

            $parsed = null;
            $content0 = $data['output'][0]['content'][0] ?? null;

            if (is_array($content0)) {
                if (($content0['type'] ?? null) === 'output_text' && isset($content0['text'])) {
                    $parsed = json_decode($content0['text'], true);
                } elseif (isset($content0['text'])) {
                    $parsed = json_decode($content0['text'], true);
                }
            }

            if (! is_array($parsed)) {
                return response()->json(['errors' => 'Not able to assign category to the product.'], 422);
            }

            $found_category = Category::query()->where('id', $parsed['id'])->where('is_active', 1)->first();

            if (! isset($found_category)) {
                return response()->json(['errors' => 'Category could not be suggested based on the current product name and description.'], 404);
            }

            unset($parsed['id']);

            $parsed['uuid'] = $found_category->uuid;

            return response()->json([
                'message' => 'Category recognized successfully',
                'data' => $parsed,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'errors' => 'An error occurred: '.config('app.env') === 'Production' ? 'Something went wrong' : $e->getMessage(),
            ], 500);
        }
    }
}
