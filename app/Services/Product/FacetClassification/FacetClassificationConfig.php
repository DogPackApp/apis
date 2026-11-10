<?php

namespace App\Services\Product\FacetClassification;

final class FacetClassificationConfig
{
    public const CONFIDENCE_THRESHOLD = 0.75;

    public const CONFIDENCE_EXACT = 1.0;

    public const CONFIDENCE_ALIAS = 0.99;

    public const CONFIDENCE_LLM = 0.96;

    /*
     * Free-text exact hits are deterministic like variant exact matches, but
     * lack the seller's explicit attribute framing, so they sit just under it.
     */
    public const CONFIDENCE_TEXT_EXACT = 0.98;

    /*
     * Single- and two-character labels are too short to scan safely in prose
     * ("or", "no", "xl"), so free-text matching ignores them.
     */
    public const MIN_TEXT_MATCH_LENGTH = 3;

    public const MAX_EVIDENCE_LENGTH = 120;

    public const MAX_FACETS_PER_CHUNK = 8;

    public const MAX_VALUES_PER_CHUNK = 100;

    public const MAX_TITLE_LENGTH = 500;

    public const MAX_DESCRIPTION_LENGTH = 3000;

    public const MAX_ORIGIN_LENGTH = 300;

    public const MODEL = 'gpt-4o-mini';

    public const MAX_OUTPUT_TOKENS = 2000;

    public const MAX_CONSTRAINED_OUTPUT_TOKENS = 800;

    public const ALIAS_CACHE_PREFIX = 'facet_alias:';

    public const ALIAS_CACHE_TTL_SECONDS = 60 * 60 * 24 * 30;

    /*
     * USD per million tokens for MODEL, as published in August 2026.
     * Cost reporting is an estimate only: revisit these alongside the model.
     */
    public const PRICE_PER_MILLION_TOKENS = [
        'input' => 0.15,
        'cached_input' => 0.075,
        'output' => 0.60,
    ];
}
