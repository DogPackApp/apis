<?php

namespace App\Http\Resources\Store;

use App\Http\Resources\Misc\MiscResource;
use App\Http\Resources\Subscribe\SubscriptionResource;
use App\Services\SubscribeSave\SubscribeSaveEligibilityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Store',
    properties: [
        new OA\Property(property: 'uuid', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'slug', type: 'string'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'image', type: 'string', nullable: true),
        new OA\Property(property: 'cover_image', type: 'string', nullable: true),
        new OA\Property(property: 'social_links', type: 'object', nullable: true),
        new OA\Property(property: 'status', type: 'integer'),
        new OA\Property(property: 'states', type: 'string'),
        new OA\Property(property: 'stripe_account_id', type: 'string', nullable: true),
        new OA\Property(property: 'stripe_customer_id', type: 'string', nullable: true),
        new OA\Property(property: 'pm_type', type: 'string', nullable: true),
        new OA\Property(property: 'pm_last_four', type: 'string', nullable: true),
        new OA\Property(property: 'stripe_status', type: 'string', nullable: true),
        new OA\Property(property: 'timezone', type: 'string', nullable: true),
        new OA\Property(property: 'is_shopify_connected', type: 'boolean'),
        new OA\Property(property: 'subscribe_save_allowed_percents', type: 'array', items: new OA\Items(type: 'integer')),
    ]
)]
class StoreResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'cover_image' => $this->cover_image,
            'social_links' => $this->social_links,
            'status' => $this->status,
            'states' => $this->states ?? 'PENDING',
            'stripe_account_id' => $this->stripe_account_id,
            'stripe_customer_id' => $this->stripe_customer_id,
            'pm_type' => $this->pm_type,
            'pm_last_four' => $this->pm_last_four,
            'stripe_status' => $this->stripe_status,
            'currency' => MiscResource::make($this->whenLoaded('currency')),
            'language' => MiscResource::make($this->whenLoaded('language')),
            'timezone' => $this->timezone ?? null,
            'country' => MiscResource::make($this->whenLoaded('country')),
            'currentplan' => SubscriptionResource::make($this->whenLoaded('storesubscription')),
            'is_shopify_connected' => $this->is_shopify_connected,
            'vat_number' => $this->when(
                (int) $this->country_id === 230 && $this->relationLoaded('storeInfo'),
                fn () => $this->storeInfo?->vat_number
            ),
            'subscribe_save_discount_percent' => $this->when(
                $this->relationLoaded('settings'),
                fn () => $this->settings?->subscribe_save_discount_percent
            ),
            'subscribe_save_disabled' => $this->when(
                $this->relationLoaded('settings'),
                fn () => (bool) ($this->settings?->subscribe_save_disabled)
            ),
            'subscribe_save_allowed_percents' => app(SubscribeSaveEligibilityService::class)->allowedSellerPercents(),
        ];
    }
}
