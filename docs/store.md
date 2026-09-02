# Store Model

`App\Models\Store\Store` — `app/Models/Store/Store.php`

Represents a seller's marketplace storefront. Each seller has at most one store (`Seller::store()`), created as the first onboarding step.

## Table: `stores`

Migration: `database/migrations/2026_08_06_210300_create_stores_table.php`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint, PK | |
| `uuid` | uuid, unique | Public identifier; auto-generated on create. |
| `seller_id` | FK → `sellers.id`, unique | One store per seller, enforced at the DB level. Cascade-deletes with the seller. |
| `name` | string | Required. Must be globally unique (enforced by `StoreCreateRequest`/`StoreUpdateRequest`). |
| `slug` | string, unique | Auto-generated from `name` on create (see below). Never set directly. |
| `description` | text, nullable | |
| `image` | string, nullable | Stored as a raw path/key; resolved to a full URL on read (see [Media accessors](#media-accessors)). |
| `cover_image` | string, nullable | Same as `image`. |
| `social_links` | json, nullable | Cast to `array`. |
| `status` | tinyint, default `0` | Set to `1` on creation via `StoreCreateController`. |
| `states` | string, default `PENDING` | One of `Store::STATES_PENDING` / `STATES_ACTIVE` / `STATES_INACTIVE`. Set to `ACTIVE` on creation. `INACTIVE` is reserved for blocking a seller's store (not currently set anywhere — a future admin action). |
| `timezone` | string, nullable, default `UTC` | IANA timezone identifier (e.g. `America/Toronto`). Validated against `timezone_identifiers_list()` on write. |
| `created_at` / `updated_at` | timestamps | |

## Class contract

- Extends `Illuminate\Database\Eloquent\Model`.
- Trait: `Illuminate\Database\Eloquent\Factories\HasFactory`.

### Constants

```php
Store::STATES_PENDING  // 'PENDING'
Store::STATES_ACTIVE   // 'ACTIVE'
Store::STATES_INACTIVE // 'INACTIVE'
```

### Fillable

`uuid, seller_id, name, slug, description, image, cover_image, social_links, status, states, timezone`

### Casts

```php
'status' => 'integer',
'social_links' => 'array',
```

## Behavior

### UUID & slug generation

On `creating`:
- `uuid` is populated with `Str::uuid()` if not already set.
- `slug` is derived from `name` via `Str::slug()` if not already set, with a numeric suffix (`-1`, `-2`, ...) appended until unique (`uniqueSlug()`). Both are internal — never pass `slug` in from a request.

### Media accessors

`image` and `cover_image` are Eloquent attribute accessors (`Illuminate\Database\Eloquent\Casts\Attribute`) backed by `App\Support\Media::url()`:

- A value already starting with `http://`/`https://` is returned unchanged.
- Anything else is prefixed with `config('services.marketplace_media')` (env `MARKETPLACE_CLOUDFRONT_URL`) — i.e. the DB stores a relative key, reads return a full CDN URL.

```php
$store->image; // full URL, or null if unset
```

### `seller(): BelongsTo`

```php
$store->seller; // App\Models\Seller\Seller
```

Inverse of `Seller::store()` — see [seller.md](./seller.md).

## Timezone display

`Store` does not convert its own timestamps — `created_at`/`updated_at` are stored and read as plain UTC Carbon instances. Timezone-aware formatting is applied only where it's presented, in `App\Http\Resources\Store\StoreResource`, via `App\Support\Timezone::convert($value, $store->timezone)`. This is deliberate: earlier implementations resolved a "current store" through a container-bound singleton and applied conversion globally to every timestamped model, which broke under queued jobs and couldn't support more than one store per request. The `Store` model itself stays timezone-agnostic; only the resource layer knows about display formatting.

## HTTP surface (`routes/api.php`, prefix `/api/seller`, all `auth:marketplace`)

| Method | Path | Controller | Notes |
|---|---|---|---|
| GET | `/store` | `StoreShowController` | `404` if the authenticated seller has no store yet. |
| POST | `/store` | `StoreCreateController` | `422` if the seller already has a store. On success: creates the store, marks the `store_setting` onboarding step complete (`OnboardingStep::StoreSetting`), sends `App\Mail\SellerWelcomeEmail`. |
| PUT | `/store` | `StoreUpdateController` | `404` if no store exists. Partial update (`sometimes` validation on every field). |

Request validation: `App\Http\Requests\Store\StoreCreateRequest` (create) and `StoreUpdateRequest` (update) — both validate `timezone` with `Rule::in(timezone_identifiers_list())` and `name` uniqueness (the update request ignores the seller's own current store row).

### Response shape (`StoreResource`)

```json
{
  "uuid": "...",
  "name": "...",
  "slug": "...",
  "description": "...",
  "image": "https://.../path.jpg",
  "cover_image": "https://.../path.jpg",
  "social_links": { "...": "..." },
  "status": 1,
  "states": "ACTIVE",
  "timezone": "America/Toronto",
  "updated_at": "2026-09-01 12:00:00",
  "created_at": "2026-09-01 12:00:00"
}
```

`created_at`/`updated_at` are formatted in the store's own `timezone` (falling back to UTC if unset).

The store is also embedded in the seller's own profile response (`GET /api/seller/me`), which eager-loads the relation: `"data": { ..., "store": { ...StoreResource fields... } | null }`.

## Onboarding relationship

Creating a store is the `store_setting` onboarding step. `StoreCreateController` calls:

```php
$onboardingService->complete($seller, OnboardingStep::StoreSetting);
```

which flips `OnboardingStatus.is_store_setting` to `1` for that seller. See `App\Services\OnboardingService` and [seller.md](./seller.md#related-models) for the other steps (`product`, `shipping`, `finance`, `subscribe`) — those are not yet backed by real modules and are out of scope for the current build.

## Factory

`Database\Factories\StoreFactory` — `database/factories/StoreFactory.php`

Defaults: `seller_id` via `Seller::factory()`, a unique fake company `name`, `status=1`, `states=Store::STATES_ACTIVE`, `timezone='UTC'`.

## See also

- [seller.md](./seller.md) — the owning seller.
