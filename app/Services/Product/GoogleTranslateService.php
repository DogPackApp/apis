<?php

namespace App\Services\Product;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleTranslateService
{
    public const CACHE_TTL = 2592000;

    public const API_ENDPOINT = 'https://translation.googleapis.com/language/translate/v2';

    protected function getApiKey(): ?string
    {
        return config('services.google_translate.api_key');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logError(string $message, array $context = []): void
    {
        Log::error($message, $context);
    }

    protected function getCacheKey(string $text, string $toLanguage, ?string $fromLanguage = null): string
    {
        $textHash = md5(trim($text));
        $langKey = $fromLanguage ? "{$fromLanguage}_{$toLanguage}" : "auto_{$toLanguage}";

        return "google_translate_{$langKey}_{$textHash}";
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, string>
     */
    public function translateBatch(array $texts, string $toLanguage, ?string $fromLanguage = null): array
    {
        if ($toLanguage === $fromLanguage) {
            return $texts;
        }

        if ($texts === []) {
            return [];
        }

        $decodedTexts = [];
        foreach ($texts as $index => $text) {
            $trimmed = trim($text);
            if ($trimmed !== '') {
                $decodedTexts[$index] = html_entity_decode($trimmed, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        if ($decodedTexts === []) {
            return [];
        }

        $results = [];
        $textsToTranslate = [];
        $cacheKeys = [];
        $textIndices = [];

        foreach ($decodedTexts as $index => $decodedText) {
            $cacheKey = $this->getCacheKey($decodedText, $toLanguage, $fromLanguage);
            $cachedTranslation = Cache::get($cacheKey);

            if ($cachedTranslation !== null) {
                $results[$index] = html_entity_decode($cachedTranslation, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } else {
                $textsToTranslate[$index] = $decodedText;
                $cacheKeys[$index] = $cacheKey;
                $textIndices[] = $index;
            }
        }

        if ($textsToTranslate === []) {
            ksort($results);

            return array_values($results);
        }

        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            $this->logError('Google Translate API key is not configured', [
                'texts_count' => count($textsToTranslate),
                'to_language' => $toLanguage,
                'from_language' => $fromLanguage,
            ]);

            foreach ($textIndices as $originalIndex) {
                $results[$originalIndex] = $textsToTranslate[$originalIndex];
            }
            ksort($results);

            return array_values($results);
        }

        try {
            $baseParams = [
                'key' => $apiKey,
                'target' => $toLanguage,
            ];

            if ($fromLanguage !== null) {
                $baseParams['source'] = $fromLanguage;
            }

            $queryParts = [];
            foreach ($baseParams as $key => $value) {
                $queryParts[] = urlencode($key).'='.urlencode((string) $value);
            }

            foreach ($textsToTranslate as $text) {
                $queryParts[] = 'q='.urlencode($text);
            }

            $url = self::API_ENDPOINT.'?'.implode('&', $queryParts);

            $response = Http::timeout(10)->connectTimeout(3)->get($url);
            $responseData = $response->json();

            if (! $response->successful() || ! isset($responseData['data']['translations'])) {
                $errorMessage = $responseData['error']['message'] ?? 'Unknown error occurred';

                $this->logError('Batch translation API error: '.$errorMessage, [
                    'response' => $responseData,
                    'texts_count' => count($textsToTranslate),
                    'to_language' => $toLanguage,
                    'from_language' => $fromLanguage,
                    'status_code' => $response->status(),
                ]);

                foreach ($textIndices as $originalIndex) {
                    $results[$originalIndex] = $textsToTranslate[$originalIndex];
                }
                ksort($results);

                return array_values($results);
            }

            $translations = $responseData['data']['translations'];
            foreach ($textIndices as $i => $originalIndex) {
                $translatedText = $translations[$i]['translatedText'] ?? '';

                if ($translatedText === '') {
                    $decodedTranslatedText = $textsToTranslate[$originalIndex];
                } else {
                    $decodedTranslatedText = html_entity_decode($translatedText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    Cache::put($cacheKeys[$originalIndex], $decodedTranslatedText, self::CACHE_TTL);
                }

                $results[$originalIndex] = $decodedTranslatedText;
            }

            ksort($results);

            return array_values($results);
        } catch (Throwable $e) {
            $this->logError('Batch translation failed: '.$e->getMessage(), [
                'exception' => $e::class,
                'texts_count' => count($textsToTranslate),
                'to_language' => $toLanguage,
                'from_language' => $fromLanguage,
            ]);

            foreach ($textIndices as $originalIndex) {
                $results[$originalIndex] = $textsToTranslate[$originalIndex];
            }
            ksort($results);

            return array_values($results);
        }
    }
}
