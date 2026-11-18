<?php

namespace App\Services\Product;

use App\Jobs\Product\TranslationMemoryJob;
use App\Models\Product\TranslationMemory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleTranslateService
{
    /**
     * Whether the last translateBatch call fell back to the original text because Google
     * could not translate it. Callers that save translations permanently must check this
     * first, otherwise English gets stored as the "translation".
     */
    protected bool $lastCallFailed = false;

    public function lastCallFailed(): bool
    {
        return $this->lastCallFailed;
    }

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
     * Language value stored in translation_memory ('auto' when no source language was given)
     */
    protected function memoryFromLanguage(?string $fromLanguage): string
    {
        return $fromLanguage ?: 'auto';
    }

    /**
     * Only plain language codes (en, pt-pt, zh-CN) are stored; anything else would be truncated
     * by the varchar(10) columns and is not a real Google target anyway.
     */
    protected function isStorableLanguage(?string $language): bool
    {
        return $language !== null
            && strlen($language) <= 10
            && preg_match('/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})?$/', $language) === 1;
    }

    /**
     * Look up already-paid translations in the database (second level behind Redis).
     *
     * @param  array<int|string, string>  $decodedTexts  Texts keyed by caller index, already trimmed and decoded
     * @return array<int|string, string> Translations keyed by the same indexes (misses are absent)
     */
    protected function findInMemory(array $decodedTexts, string $toLanguage, ?string $fromLanguage): array
    {
        if (empty($decodedTexts) || ! $this->isStorableLanguage($toLanguage)) {
            return [];
        }

        try {
            $hashes = [];
            foreach ($decodedTexts as $index => $text) {
                $hashes[$index] = md5(trim($text));
            }

            $found = [];
            foreach (array_chunk(array_values(array_unique($hashes)), 500) as $chunk) {
                $rows = TranslationMemory::whereIn('source_hash', $chunk)
                    ->where('from_language', $this->memoryFromLanguage($fromLanguage))
                    ->where('to_language', $toLanguage)
                    ->get(['source_hash', 'translated_text']);

                foreach ($rows as $row) {
                    $found[$row->source_hash] = $row->translated_text;
                }
            }

            $results = [];
            foreach ($hashes as $index => $hash) {
                if (isset($found[$hash])) {
                    $results[$index] = $found[$hash];
                }
            }

            return $results;
        } catch (Throwable $e) {
            Log::error('Translation memory lookup failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Queue the successful translations for permanent storage. The insert runs in
     * TranslationMemoryJob so the request never waits on it.
     *
     * @param  array<int, array{0:string, 1:string}>  $pairs  [decodedSourceText, decodedTranslation] pairs
     */
    protected function rememberTranslations(array $pairs, string $toLanguage, ?string $fromLanguage): void
    {
        if (empty($pairs)
            || ! $this->isStorableLanguage($toLanguage)
            || ($fromLanguage !== null && ! $this->isStorableLanguage($fromLanguage))) {
            return;
        }

        try {
            $createdAt = now()->toDateTimeString();
            $entries = [];

            foreach ($pairs as [$source, $translated]) {
                if (trim($source) === '' || trim($translated) === '') {
                    continue;
                }

                $hash = md5(trim($source));
                $entries[$hash] = [
                    'source_hash' => $hash,
                    'from_language' => $this->memoryFromLanguage($fromLanguage),
                    'to_language' => $toLanguage,
                    'translated_text' => $translated,
                    'created_at' => $createdAt,
                ];
            }

            if (! empty($entries)) {
                TranslationMemoryJob::dispatch(array_values($entries));
            }
        } catch (Throwable $e) {
            Log::error('Failed to queue translation memory write: '.$e->getMessage());
        }
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, string>
     */
    public function translateBatch(array $texts, string $toLanguage, ?string $fromLanguage = null): array
    {
        $this->lastCallFailed = false;
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

        if ($textsToTranslate !== []) {
            $remembered = $this->findInMemory($textsToTranslate, $toLanguage, $fromLanguage);
            foreach ($remembered as $index => $rememberedText) {
                $rememberedTranslation = html_entity_decode($rememberedText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $results[$index] = $rememberedTranslation;
                Cache::put($cacheKeys[$index], $rememberedTranslation, self::CACHE_TTL);
                unset($textsToTranslate[$index], $cacheKeys[$index]);
            }
            if ($remembered !== []) {
                $textIndices = array_values(array_diff($textIndices, array_keys($remembered)));
            }
        }

        if ($textsToTranslate === []) {
            ksort($results);

            return array_values($results);
        }

        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            $this->lastCallFailed = true;
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
                $this->lastCallFailed = true;
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
            $memoryPairs = [];
            foreach ($textIndices as $i => $originalIndex) {
                $translatedText = $translations[$i]['translatedText'] ?? '';

                if ($translatedText === '') {
                    $this->lastCallFailed = true;
                    $decodedTranslatedText = $textsToTranslate[$originalIndex];
                } else {
                    $decodedTranslatedText = html_entity_decode($translatedText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    Cache::put($cacheKeys[$originalIndex], $decodedTranslatedText, self::CACHE_TTL);
                    $memoryPairs[] = [$textsToTranslate[$originalIndex], $decodedTranslatedText];
                }

                $results[$originalIndex] = $decodedTranslatedText;
            }

            $this->rememberTranslations($memoryPairs, $toLanguage, $fromLanguage);

            ksort($results);

            return array_values($results);
        } catch (Throwable $e) {
            $this->lastCallFailed = true;
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
