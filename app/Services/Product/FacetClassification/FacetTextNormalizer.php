<?php

namespace App\Services\Product\FacetClassification;

class FacetTextNormalizer
{
    /**
     * Conservative matching key: case, surrounding whitespace, repeated
     * whitespace, and harmless punctuation. Hyphens and word breaks are
     * preserved so "x-large" and "x large" stay distinct from "xl".
     */
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = strip_tags($value);
        $value = html_entity_decode(
            $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
