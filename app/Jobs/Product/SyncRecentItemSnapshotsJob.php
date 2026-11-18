<?php

namespace App\Jobs\Product;

use App\Models\Product\Item;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncRecentItemSnapshotsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $hours;

    public function __construct(int $hours = 2)
    {
        $this->onQueue(config('queue.low'));
        $this->hours = $hours;
        Log::info('SyncRecentItemSnapshotsJob: Constructing job with hours: '.$this->hours);
    }

    public function handle(): void
    {
        try {
            $itemIdsDone = Cache::get('sync_recent_item_snapshots_job_item_ids_done', []);
            $timeOffset = Carbon::now()->subHours($this->hours); // Last 5 days
            Log::info('SyncRecentItemSnapshotsJob: Processing items updated in the last '.$this->hours.' hours');
            $itemsQuery = DB::table('items as i')
                ->leftJoin('stores as s', 's.id', '=', 'i.store_id')
                ->select(
                    'i.id as item_id',
                    'i.title',
                    's.id as store_id',
                    's.name as store_name',
                    's.is_verified as store_is_verified',
                    'i.description',
                    'i.status',
                    'i.updated_at'
                )
                ->where('i.updated_at', '>=', $timeOffset);

            if (! empty($itemIdsDone)) {
                $itemsQuery->whereNotIn('i.id', $itemIdsDone);
            }

            $totalUpserted = 0;
            $totalDeleted = 0;

            $itemsQuery->orderBy('i.id')->chunk(500, function ($items) use (&$itemIdsDone, &$totalUpserted, &$totalDeleted) {
                $upsertData = [];
                $activeItemIds = [];
                $inactiveItemIds = [];
                $totalDeletedFromChunk = 0;

                foreach ($items as $item) {
                    if ($item->status == Item::STATUS[Item::STATUS_ACTIVE]) {
                        $upsertData[] = [
                            'item_id' => $item->item_id,
                            'title' => $item->title,
                            'store_name' => $item->store_name,
                            'description' => self::cleanDescription($item->description),
                            'store_id' => $item->store_id,
                            'store_is_verified' => $item->store_is_verified,
                        ];
                        $activeItemIds[] = $item->item_id;
                    } else {
                        $inactiveItemIds[] = $item->item_id;
                    }
                    $itemIdsDone[] = $item->item_id;
                }

                if (! empty($upsertData)) {
                    DB::table('item_snapshots')->upsert(
                        $upsertData,
                        ['item_id'],
                        ['title', 'store_name', 'description', 'store_id', 'store_is_verified']
                    );
                    $totalUpserted += count($upsertData);
                }

                if (! empty($inactiveItemIds)) {
                    $totalDeletedFromChunk += DB::table('item_snapshots')
                        ->whereIn('item_id', $inactiveItemIds)
                        ->delete();
                    $totalDeleted += $totalDeletedFromChunk;
                }

                if (! empty($activeItemIds)) {
                    $tagsData = DB::table('item_tags')
                        ->join('tags', 'tags.id', '=', 'item_tags.tags_id')
                        ->whereIn('item_tags.item_id', $activeItemIds)
                        ->select('item_tags.item_id', DB::raw("GROUP_CONCAT(tags.name ORDER BY tags.name SEPARATOR ',') as tags"))
                        ->groupBy('item_tags.item_id')
                        ->pluck('tags', 'item_id');

                    if ($tagsData->isNotEmpty()) {
                        $caseStatements = [];
                        $ids = [];
                        foreach ($activeItemIds as $itemId) {
                            $escapedTags = addslashes($tagsData[$itemId] ?? '');
                            $caseStatements[] = "WHEN {$itemId} THEN '{$escapedTags}'";
                            $ids[] = $itemId;
                        }
                        $cases = implode(' ', $caseStatements);
                        $idList = implode(',', $ids);
                        DB::statement("UPDATE item_snapshots SET tags = CASE item_id {$cases} END WHERE item_id IN ({$idList})");
                    }
                }

                Log::info('SyncRecentItemSnapshotsJob: Processed chunk - Upserted: '.count($upsertData).', Deleted: '.$totalDeletedFromChunk);
            });

            Cache::put('sync_recent_item_snapshots_job_item_ids_done', $itemIdsDone, 60 * 60 * $this->hours);
            Log::info("SyncRecentItemSnapshotsJob: Summary - Upserted: {$totalUpserted}, Deleted: {$totalDeleted}");
        } catch (\Exception $e) {
            Log::error('SyncRecentItemSnapshotsJob Error: '.$e->getMessage());
        }
    }

    /**
     * Clean description - strip HTML, normalize spaces, remove stop words
     */
    public static function cleanDescription(?string $description): string
    {
        if (empty($description)) {
            return '';
        }

        // Replace HTML tags with spaces (to prevent words from joining)
        $cleaned = preg_replace('/<[^>]+>/', ' ', $description);

        // Decode HTML entities (e.g., &nbsp; &amp; etc.)
        $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Add space after punctuation if missing (e.g., "pets.Adjustable" -> "pets. Adjustable")
        $cleaned = preg_replace('/([.!?,:;])([A-Z])/', '$1 $2', $cleaned);

        // Add space between lowercase and uppercase (e.g., "backCan" -> "back Can")
        $cleaned = preg_replace('/([a-z])([A-Z])/', '$1 $2', $cleaned);

        // Remove extra whitespace and normalize spaces
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        $cleaned = trim($cleaned);

        // Remove stop words
        return self::removeStopWords($cleaned);
    }

    /**
     * Remove stop words from text
     * Stop words are common words that don't carry significant meaning
     */
    private static function removeStopWords(string $text): string
    {
        $stopWords = [
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
        ];

        // Split text into words
        $words = preg_split('/\s+/', $text);

        // Filter out stop words (case-insensitive)
        $filteredWords = array_filter($words, function ($word) use ($stopWords) {
            return ! in_array(strtolower(trim($word, '.,!?;:\'"')), $stopWords);
        });

        // Rejoin words
        return implode(' ', $filteredWords);
    }
}
