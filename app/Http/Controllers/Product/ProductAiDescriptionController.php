<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class ProductAiDescriptionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_name' => 'nullable|string|max:255',
                'description' => 'required|string|max:255',
                'keywords' => 'nullable|string',
                'tone' => 'nullable|string',
            ], [
                'product_name.max' => 'The product name cannot be more than 255 characters.',
                'description.required' => 'Brief product description is required.',
                'description.max' => 'The description cannot exceed 255 characters.',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $prompt = "You are a professional product copywriter.\n\n";

            $prompt .= 'Write a compelling, SEO-optimized product description';

            if ($request->product_name) {
                $prompt .= " for a product called \"{$request->product_name}\"";
            }

            $prompt .= ".\n\n";

            $prompt .= "Product Details:\n";
            $prompt .= "{$request->description}\n\n";

            if ($request->keywords) {
                $prompt .= "Incorporate the following keywords naturally into the text: {$request->keywords}.\n";
            }

            $tone = $request->tone ?: 'engaging, persuasive, and clear';

            $prompt .= "Tone of voice: {$tone}.\n";
            $prompt .= "Avoid generic phrasing. Emphasize unique selling points. Use vivid language.\n\n";
            $prompt .= 'Output only the description without any intro or notes.';

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.config('services.open_ai.ai_product_description_key'),
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 120,
            ]);

            if ($response->successful()) {
                return response()->json([
                    'description' => $response->json()['choices'][0]['message']['content'],
                ]);
            }

            return response()->json([
                'errors' => 'Failed to generate description',
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }
}
