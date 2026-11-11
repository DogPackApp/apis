<?php

namespace App\Services\Review;

use App\Models\Product\Item;
use App\Models\Review\ItemReview;
use App\Models\Store\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    public function allReviewsBase($modelId, $module): Builder
    {
        return ItemReview::query()
            ->with(['images'])
            ->where(function (Builder $q) use ($modelId, $module) {
                switch ($module) {
                    case ItemReview::MODULE_STORE:
                        $q->where('store_id', $modelId);
                        break;
                    case ItemReview::MODULE_ITEM:
                        $q->where('item_id', $modelId);
                        break;
                    case ItemReview::MODULE_USER:
                        $q->where('dp_user_id', $modelId);
                        break;
                }
            })
            ->where('is_visible', 1);
    }

    public function overviewOf(?int $moduleId = null, ?string $module = ItemReview::MODULE_STORE): array
    {
        $modelId = null;

        if ($module === ItemReview::MODULE_ITEM) {
            $modelId = Item::query()->where('id', $moduleId)->first()->id;
        } else {
            $modelId = $moduleId ?? resolve(Store::class)->id;
        }

        $base = $this->allReviewsBase($modelId, $module);

        $totalReviews = (clone $base)->count();
        $totalAvg = (float) round((clone $base)->avg('rating'), 1);
        $groupedRating = (clone $base)->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->orderBy('rating')
            ->get();

        $ratings = collect(range(1, 5))->map(function ($rate) use ($groupedRating) {
            $match = $groupedRating->firstWhere('rating', $rate);

            return [
                'rating' => $rate,
                'total' => $match ? $match->total : 0,
            ];
        })->toArray();

        return [
            'totalReviews' => $totalReviews ?? 0,
            'totalAvg' => $totalAvg ?? 0,
            'groupedRating' => $ratings ?? [],
        ];
    }

    public function allReviewsBy(array $filters, ?int $moduleId = null, ?string $module = ItemReview::MODULE_STORE): ?Builder
    {
        $modelId = null;

        switch ($module) {
            case ItemReview::MODULE_ITEM:
                $modelId = Item::query()->where('id', $moduleId)->first()?->id;
                break;
            case ItemReview::MODULE_STORE:
                $modelId = $moduleId ?? resolve(Store::class)->id;
                break;
            case ItemReview::MODULE_USER:
                $modelId = $moduleId ?? Auth::user()?->id;
                break;
            default:
                return null;
        }

        if (! $modelId) {
            return null;
        }

        $query = $this->allReviewsBase($modelId, $module)
            ->select([
                'id',
                'uuid',
                'rating',
                'title',
                'comment',
                'response',
                'created_at',
                'dp_user_id',
                'username',
                'updated_at',
                'item_id',
                DB::raw('(store_order_id IS NOT NULL OR is_editable = 1) as is_purchased'),
            ])
            ->with([
                'item:id,uuid,title,media_url,slug,status',
                'user:id,user_uuid,username,profile_picture,profile_thumb',
            ])
            ->when($filters['type'] ?? 'all', function ($type) use ($filters) {
                if ($filters['type'] === 'verified') {
                    return $type->where(function ($query) {
                        $query->whereNotNull('store_order_id')
                            ->orWhere('is_editable', 1);
                    });
                }

                return $type;
            })
            ->when($filters['rating'] ?? 'all', function ($rating) use ($filters) {
                if ($filters['rating'] !== 'all') {
                    return $rating->where('rating', $filters['rating']);
                }

                return $rating;
            })
            ->when(array_key_exists('media', $filters), function ($rating) use ($filters) {
                $mediaFilter = (string) ($filters['media'] ?? 'all');
                if ($mediaFilter === '1') {
                    return $rating->whereHas('images', function ($q) {
                        $q->whereNotNull('id');
                    });
                } elseif ($mediaFilter === '0') {
                    return $rating->doesntHave('images');
                }

                return $rating;
            })
            ->when($filters['orderBy'] ?? 'recent', function ($orderBy) use ($filters) {
                if ($filters['orderBy'] === 'recent') {
                    return $orderBy->orderBy('created_at', 'desc');
                }

                return $orderBy->orderBy('rating', 'desc');
            })
            ->where('is_visible', 1);

        return $query;
    }
}
