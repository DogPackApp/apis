<?php

namespace App\Services\Product;

class RemoveStopWordsService
{
    private static array $stopWords = [
        // Articles
        'a', 'an', 'the',
        // Be verbs
        'is', 'am', 'are', 'was', 'were', 'be', 'been', 'being',
        // Auxiliary verbs
        'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'shall', 'can',
        // Prepositions
        'to', 'of', 'in', 'for', 'on', 'with', 'at', 'by', 'from', 'as', 'into', 'through', 'during', 'before', 'after', 'above', 'below', 'between', 'under', 'over',
        // Conjunctions
        'and', 'but', 'or', 'nor', 'so', 'yet', 'both', 'either', 'neither',
        // Pronouns
        'i', 'me', 'my', 'myself', 'we', 'our', 'ours', 'ourselves', 'you', 'your', 'yours', 'yourself', 'yourselves',
        'he', 'him', 'his', 'himself', 'she', 'her', 'hers', 'herself', 'it', 'its', 'itself',
        'they', 'them', 'their', 'theirs', 'themselves', 'what', 'which', 'who', 'whom', 'this', 'that', 'these', 'those',
        // Other common words
        'if', 'then', 'else', 'when', 'where', 'why', 'how', 'all', 'each', 'every', 'any', 'some', 'no', 'not', 'only', 'own', 'same', 'than', 'too', 'very', 'just', 'also',
        // Additional common words
        'about', 'such', 'because', 'while', 'here', 'there', 'more', 'most', 'other', 'up', 'out', 'down', 'off', 'again', 'further', 'once',
        // Marketing filler words
        'new', 'brand', 'high', 'quality', 'premium', 'best', 'great', 'sale', 'hot', 'free', 'shipping', 'fast',
    ];

    public static function removeStopWords(string $query): string
    {
        // Clean up the query - remove punctuation and normalize spaces
        $query = preg_replace('/[^\w\s]/', ' ', $query);
        $query = preg_replace('/\s+/', ' ', trim($query));

        $words = explode(' ', $query);
        $filteredWords = array_filter($words, function ($word) {
            $lowerWord = strtolower($word);

            return ! in_array($lowerWord, self::$stopWords) && strlen($word) > 1;
        });

        return implode(' ', $filteredWords);
    }
}
