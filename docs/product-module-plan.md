# Product/Item Module Plan

Tier 4 of `docs/api-development-roadmap.md` (Product) is the biggest, most tangled domain in backend-api — `Item` alone touches 15+ related models. This doc breaks it into phases so we can decide what's actually going in before any code gets written, then tracks build status per phase the same way the roadmap tracks routes.

## How to use this doc

Each phase lists the models it needs and what it unlocks. Mark a phase's **Decision** as we go:
- ✅ **In** — building this
- ❌ **Out** — explicitly excluded (with why)
- ⏸ **Deferred** — not excluded, just not now (with what it's waiting on)
- ❓ **Undecided** — not discussed yet

Nothing gets built until its phase says ✅ In.

---

## Phase 1 — Core Item — Decision: ✅ In (built)

The product itself. Nothing else in this doc means anything without this.

| Model | Purpose | Notes |
|---|---|---|
| `Item` | title, description, type (physical/digital), status (active/draft/archived/deleted), `store_id`, `category_id` (nullable), `policy_id` (nullable) | Hub model everything else hangs off |
| `ItemPrice` | sku, domestic_price, global_price, stock_quantity, allow_overselling, weight, unit | 1:1 with Item |
| `Category` | Seeded taxonomy (parent/child hierarchy, name/slug/images) | No seeder in backend-api — same "needs a real data export" situation as Tier 0 countries. **Real data exported and seeded** — 248 rows in `database/seeders/data/categories.json`, via `CategorySeeder`. |

**Unlocks**: `POST/GET/PUT /product`, `GET /product/{uuid}`, `PUT /product/{uuid}/archive` — all built. `PUT /product/bulk/move` (needs Section, Phase 2) and `GET /product/category` (category listing, deliberately deferred) are **not** part of this slice.

Built as: `App\Models\Product\{Item,ItemPrice,Category}`, `App\Repository\Product\ProductRepository` + `App\Services\Product\ProductService`, `App\Http\Controllers\Product\*`, `App\Http\Requests\Product\ProductValidationRules`, `App\Http\Resources\Product\{ProductResource,ProductPriceResource,ProductCategoryResource}`, routes under `/api/seller/product/*`, tests in `tests/Feature/ProductTest.php`.

## Phase 2 — Organization — Decision: ❓

| Model | Purpose | Notes |
|---|---|---|
| `Section` | Store's catalog sections | Already a deferred Tier 3 item (`store/section/*`, 10 routes) — building this phase effectively unblocks that too |
| `Tag` | Seller-defined tags | `Item ↔ Tag` many-to-many via `item_tags` |

## Phase 3 — Media/Gallery — Decision: ❓

| Model | Purpose | Notes |
|---|---|---|
| `Gallery` | Store's media library (images/videos) | CloudFront-prefixed URLs — same pattern as `App\Support\Media` already built for Store images |
| `ItemMedia` | Pivot: Item (and optionally a specific variant) → Gallery, with ordering | |

## Phase 4 — Variants — Decision: ❓

Size/color-style product options.

| Model | Purpose | Notes |
|---|---|---|
| `Attribute` | e.g. "Size" (attribute definitions) | |
| `AttributeOption` | e.g. "Small" (values under an attribute) | |
| `ItemVariant` | A specific SKU combination with its own price/stock | |
| `VaryBy` | Toggles whether price/quantity vary by variant | Very thin/loosely defined in backend-api (`fillable = ['*']`, no real structure) |

## Phase 5 — Discounts & Customization — Decision: ❓

| Model | Purpose | Notes |
|---|---|---|
| `Discount` | value, is_limited, start/end dates | |
| `Customization` | Buyer-fillable custom fields (e.g. engraving text/image) | Capped at 10 media items in backend-api |

## Phase 6 — Reviews — Decision: ❓

| Model | Purpose | Notes |
|---|---|---|
| `ItemReview` | Product reviews | Ties to a website **customer** (`DpUser`) and an **order** (`StoreOrder`) — neither exists in this app (separate domain, Tier 6+) |
| `ItemReviewImage` | Review photos | Depends on ItemReview |

⚠️ Can build the schema now, but it's functionally inert until Orders/Customers exist — nothing can ever create a real review without a purchase.

## Phase 7 — Search/Facets — Decision: ❓

Category-driven filtering (e.g. a "Material" facet with "Cotton"/"Plastic" values under a "Toys" category).

| Model | Purpose | Notes |
|---|---|---|
| `CategoryFacet` | Facet definitions per category | |
| `FacetValue` | Specific facet values | |
| `FacetTranslation` | i18n text for facets | |
| (pivot) `item_facet_mappings` | Item ↔ FacetValue | |

⚠️ `ProductIndexTriggerController` (admin) has no identifiable search engine (Elasticsearch/Algolia/Meilisearch) configured anywhere in backend-api — unclear what it actually indexes into. Needs investigation if this phase is included.

## Phase 8 — Translations (i18n) — Decision: ❓

| Model | Purpose | Notes |
|---|---|---|
| `ItemValueTranslation` | Per-locale title/description overrides | |

## Phase 9 — Shopify + admin moderation — Decision: ❓

| Model | Purpose | Notes |
|---|---|---|
| `Item.origin` / `Item.source_id` | Marks an item as Shopify-imported | Ties into Shopify sync — already out of scope this session |
| `ItemUnverified` | Admin moderation queue (`item_id`, `store_id` only) | No admin domain exists in this app |

## Phase 10 — Bundles — Decision: ❓

| Model | Purpose | Notes |
|---|---|---|
| `Bundle` (`SellerPanel\Bundle`) | Just an `item_id` field, no other structure | Looks unfinished/stub even in backend-api itself |

---

## External paid APIs involved

| API | Used by | Env var (in backend-api) |
|---|---|---|
| OpenAI | `POST /product/generate-description`, `POST /product/recognize-category`, facet classification | `OPEN_AI_PRODUCT_DESCRIPTION_KEY` for descriptions, `OPEN_AI_PRODUCT_CATEGORY_KEY` for category recognition, `OPEN_AI_KEY_FACET_CLASSIFICATION` for facet classification |
| (CSV export) | `POST /product/export-csv` | No external dependency, self-contained |

---

## Route → phase mapping (Tier 4 of the roadmap)

| Route | Needs |
|---|---|
| `POST /product/`, `GET /product/`, `GET /product/{uuid}`, `PUT /product/{uuid}`, `PUT /product/{uuid}/archive` | Phase 1 |
| `PUT /product/bulk/move` | Phase 1 + Phase 2 (Section) |
| `GET /product/category`, `GET /product/category/{uuid}/facets` | Phase 1 (Category) + Phase 7 (facets) |
| `POST /product/generate-description`, `POST /product/recognize-category` | Phase 1 + OpenAI |
| `POST /product/export-csv` | Phase 1 |
| `POST /product/trigger-index` **(admin)** | Phase 7 + unresolved search-engine question |
| `POST /theme/{uuid}` **(admin)** | Phase 1, but needs an admin domain that doesn't exist |

---

## Decisions log

_(fill in as we go — date, phase, decision, why)_

- **2026-09-03 — Phase 1 built.** Validation rules ported verbatim from `ProductCreateController`/`ProductUpdateController` (including two backend-api duplicate-array-key bugs — `about.customizations` and `price_inventory.global_price` are each defined twice in the source rule arrays, and PHP array-literal semantics mean the second silently overwrites the first; replicated as-is, not merged). Validation still only runs when `status == ACTIVE`, exactly like backend-api — any other status stores the raw request body as-is.
- **`ProductFieldOwnership`** (`App\Models\SellerPanel\ProductFieldOwnership` in backend-api) tracks DogPack-vs-Shopify field ownership for AI-generated content (`description`, `tags`). Not built — it's only ever consulted/written on the Shopify sync path (Phase 9, out of scope), so `ProductResource`'s `is_description_edited`/`is_tags_edited` fields are simply omitted here rather than faked.
- **`attachPolicy()` adaptation**: backend-api queries `DB::table('policies')` on every create/update, even when no policy UUID is given. No `policies` table exists yet (Policy/Tier 3 deferred) — this repo always sets `item->policy_id = null` and skips the lookup entirely, rather than replicating a query against a table that doesn't exist.
- **`ProductRepository::find()`/`delete()` not ported.** backend-api's `find()` eager-loads relation names (`about`, `priceInventory`, `categoryTags`) that don't exist anywhere on its own `Item` model — this method looks broken/dead in backend-api itself. The real `ProductShowController`/`ProductUpdateController`/`ProductArchiveToggleController` don't call it; they use route-model binding directly, which is what got ported here too.
- **Category fallback IDs (278 physical / 286 digital)** ported as literal values from `createOrUpdateItem()`, used only when no `category_tags.category` is supplied. Verified against the real export: **id 278 is "Wall Art", id 286 is "Printable Wall Art"** — not generic "Uncategorized" catch-alls, just whatever specific category IDs happened to be hardcoded in backend-api (looks like a leftover dev/test value, not intentional design). Asked the user how to handle it; decision was to **replicate literally** — an uncategorized physical/digital product really does silently land in Wall Art / Printable Wall Art, matching backend-api byte-for-byte. This path is only reachable when `status != ACTIVE` (category is a required field under ACTIVE validation) or on an update to an item that already has no category, so the real-world blast radius is small.
- **Found while building**: `attachPrice()` in backend-api accesses `price_inventory.domestic_price`/`global_price`/`stock_quantity`/`weight` unconditionally, with no null-coalescing — so even on the *unvalidated* (non-ACTIVE) create/update path, omitting any of those keys throws (Laravel converts the PHP "undefined array key" warning into a real `ErrorException`, caught as a 422). In practice this means callers must always send a fully-shaped `price_inventory` object regardless of `status`, even though the ACTIVE-only validation wouldn't have required it. This is inherited, not introduced — same code path exists in backend-api.
- **Found during the category export**: backend-api's `categories.uuid` column has **no unique constraint**, and the real data has 2 duplicate UUIDs among 248 rows. Our migration originally added `->unique()` (an assumption, not verified against real data) — removed it to match backend-api's actual schema once the real export surfaced the duplicates. `Category::query()->where('uuid', ...)->first()` lookups (both here and in backend-api) just pick one of the duplicates arbitrarily, same as backend-api.
- **Real category data export**: done — 248 rows, read-only `SELECT` against backend-api's staging DB, saved to `database/seeders/data/categories.json`, seeded via `CategorySeeder`.
