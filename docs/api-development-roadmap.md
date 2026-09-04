# Marketplace API Development Roadmap

Every route in `backend-api/routes/marketplace/marketplace.php`, reordered into the sequence they can actually be built in — each tier only depends on models/domains from earlier tiers. This is the source of truth for what to build next and in what order; update the checkboxes as work lands here in `apis`.

**How to read a tier**: "depends on" lists which earlier tier(s) must exist first because of a real foreign key or business rule (e.g. an Item can't exist without a Store to belong to). Routes within a tier can generally be built in any order relative to each other.

Legend: `[x]` done in this repo · `[ ]` not started · **(admin)** = `auth.super` guard, needs an admin-panel domain that doesn't exist here at all · **(ops)** = one-off script/dev utility, not a real product feature

---

## Tier 0 — Reference data (no dependencies)

Static lookup tables. Nothing else can meaningfully reference a country/currency/language until these exist.

- [x] `GET /misc/country` — country list
- [x] `GET /misc/currency` — currency list
- [x] `GET /misc/language` — language list
- [ ] `GET /currency/convert` — hardcoded dev-test stub in backend-api, not a real feature; explicitly skipped

**Measured response times** (real HTTP requests, `apis` running locally via `php artisan serve`, local SQLite, real Passport token — not synthetic; backend-api not benchmarked, by explicit choice, to avoid touching its live staging DB):

| Endpoint | Cold (true cache-miss, warm process) | Warm avg | Warm median | Warm min–max |
|---|---|---|---|---|
| `/misc/country` (243 rows) | ~12ms | 6.4ms | 6.0ms | 5.6–12.5ms |
| `/misc/currency` (5 rows) | ~6ms | 5.0ms | 5.0ms | 4.5–5.5ms |
| `/misc/language` (14 rows) | ~6ms | 5.0ms | 4.9ms | 4.7–5.5ms |

Cache TTL is 24h (`Cache::remember`). All three are dominated by Laravel's own per-request bootstrap, not the query — a 243-row SQLite scan is trivial either way.

This benchmark is also what surfaced a real bug, since fixed (commit `4147561`): `Cache::remember()` was storing raw Eloquent Collections, and the `database` cache driver's `unserialize()` hit a class-autoload-timing issue on every request after the first, 500'ing almost every real cached request. Never caught by the test suite because `phpunit.xml` forces the `array` cache driver there. Fixed by caching plain arrays and rehydrating as `stdClass` — the same pattern backend-api's own `CountryListController` already uses, for the same underlying reason.

## Tier 1 — Seller (root identity, no dependencies)

Everything downstream needs an authenticated seller.

- [x] `POST /seller/register`
- [x] `POST /seller/login`
- [x] `POST /seller/login/otp`
- [x] `POST /seller/password/forgot`
- [x] `POST /seller/password/reset`
- [x] `POST /seller/google/login`
- [x] `GET /seller/me`
- [x] `POST /seller/logout`
- [x] `POST /seller/verify`
- [x] `GET /seller/onboarding/status`
- [x] `PUT /seller/{seller:uuid}`
- [x] `POST /store/settings/security/current-password` → `POST /seller/password/current`
- [x] `POST /store/settings/security/new-password` → `POST /seller/password/change`
- [x] `POST /store/settings/security/2fa-verification` → `POST /seller/2fa/otp`
- [x] `POST /store/settings/security/2fa-verification/enable` → `POST /seller/2fa/enable`
- [x] `POST /store/settings/security/2fa-verification/disable` → `POST /seller/2fa/disable`

Tier 1 is complete. `POST /aws/presigned` was originally listed here but was miscategorized — see the note under Tier 4 (Product) below.

## Tier 2 — Store (depends on: Seller)

One store per seller. Everything from here on is store-scoped.

- [x] `GET /store` → `GET /seller/store`
- [x] `POST /store` (create) → `POST /seller/store`
- [x] `POST /store/{uuid}` (update) → `PUT /seller/store`
- [ ] `POST /store/settings/teams/` — add team member **(depends on Tier 1 Seller, invites a second seller onto the store)**
- [ ] `GET /store/settings/teams/` — list team members
- [ ] `DELETE /store/settings/teams/{seller:uuid}` — remove team member
- [ ] `POST /store/settings/teams/accept/invite/{seller:uuid}` — accept invite
- [ ] `POST /store/settings/teams/invite/status/{uuid}` — invite status
- [ ] `POST /store/invalidate/{seller:uuid}` — revoke a seller's tokens **(admin)**
- [ ] `POST /stripe/connect` — connect a Stripe account to the store
- [ ] `POST /wallets/adjust` — adjust store wallet points **(admin)**

## Tier 3 — Store composition (depends on: Store, Tier 0 reference data)

The pieces a store configures before it can list anything for sale.

- [ ] `GET /store/address` — depends on the Address model below
- [ ] `POST /shipping/address/autocomplete`
- [ ] `POST /shipping/address/places`
- [ ] `GET /shipping/shipping`
- [ ] `GET /shipping/packaging`
- [ ] `GET /shipping/rates`
- [ ] `POST /shipping/` — create packaging
- [ ] `POST /shipping/custom` — custom shipping method
- [ ] `PUT /shipping/custom/{shippingRateUuid}` — update custom shipping method
- [ ] `POST /policy/` — create return/shipping policy
- [ ] `GET /policy/`
- [ ] `GET /policy/{policy:uuid}`
- [ ] `PUT /policy/{policy:uuid}`
- [ ] `PUT /policy/{policy:uuid}/default`
- [ ] `DELETE /policy/{policy:uuid}`
- [ ] `GET /policy/{policy:uuid}/items`
- [ ] `POST /policy/{policy:uuid}/items`
- [ ] `POST /store/section/` — create catalog section
- [ ] `GET /store/section/`
- [ ] `GET /store/section/{section:uuid}/items`
- [ ] `POST /store/section/{section:uuid}/items` — add item to section
- [ ] `DELETE /store/section/{section:uuid}/items`
- [ ] `PUT /store/section/{section:uuid}`
- [ ] `PUT /store/section/{section:uuid}/move`
- [ ] `PUT /store/section/reorder`
- [ ] `PUT /store/section/{section:uuid}/items/reorder`
- [ ] `DELETE /store/section/{section:uuid}`
- [ ] `POST /store/gallery/` — store image gallery
- [ ] `POST /subscribe/plan` — subscribe store to a billing plan
- [ ] `GET /subscribe/plans`
- [ ] `GET /subscribe/pricings`
- [ ] `GET /subscribe/currentPlan`
- [ ] `POST /subscribe/list`
- [ ] `POST /subscribe/getCards`
- [ ] `POST /subscribe/addCard`
- [ ] `POST /subscribe/updateDefault`
- [ ] `POST /subscribe/deleteCard`
- [ ] `POST /subscribe/changeSubscriptionPlan`
- [ ] `POST /subscribe/clearSubscription`
- [ ] `GET /subscribe/calculateNextBilling`
- [ ] `POST /subscribe/approximateAmountDifference`
- [ ] `POST /subscribe/estimateNewPlanCost`
- [ ] `PUT /subscribe/retryPayment`
- [ ] `POST /stripe/webhook/subscription`
- [ ] `POST /stripe/webhook/paymentcapture`

## Tier 4 — Product (depends on: Store, Sections, Policy, Tier 0 category/reference data)

An Item can't exist without a store to belong to, a section to sit in, and a policy governing returns/shipping.

- [ ] `POST /product/` — create
- [ ] `GET /product/` — list
- [ ] `GET /product/{item:uuid}` — show
- [ ] `PUT /product/{item:uuid}` — update
- [ ] `PUT /product/{item:uuid}/archive`
- [ ] `PUT /product/bulk/move`
- [ ] `GET /product/category`
- [ ] `GET /product/category/{category:uuid}/facets`
- [ ] `POST /product/generate-description`
- [ ] `POST /product/recognize-category`
- [ ] `POST /product/export-csv`
- [ ] `POST /product/trigger-index` **(admin)**
- [ ] `POST /theme/{theme:uuid}` — sync items for theme **(admin)**
- [ ] `POST /aws/presigned` — presigned S3 upload URLs. Moved here from Tier 1: it's actually two unrelated things bolted into one controller — a store-image branch (skipped by decision; duplicates the direct-multipart upload already built in `StoreCreateController`/`StoreUpdateController`, and backend-api's version has a real race condition, writing the future S3 key to `Store.image` *before* the client has uploaded anything) and a product-gallery branch (`Gallery::insert(...)`, genuinely needs Product/Gallery to exist). Needs `league/flysystem-aws-s3-v3` + real AWS credentials either way — neither exists in this app yet. Revisit only the gallery half here; the store half stays out entirely.

## Tier 5 — Bulk/Import & Shopify sync (depends on: Product)

Operations that act on products that must already exist (or be created via an external sync).

- [ ] `POST /import/csv`
- [ ] `GET /import/csv/{batchId}`
- [ ] `POST /import/csv/{batchId}`
- [ ] `POST /import/csv/process/{batchId}`
- [ ] `POST /bulk/` — bulk list
- [ ] `PUT /bulk/` — bulk update
- [ ] `POST /shopify/product/create` — separate `auth.shopify` guard
- [ ] `POST /shopify/product/delete`
- [ ] `POST /shopify/connect`
- [ ] `POST /shopify/order/fulfill` — depends on Tier 6 Orders too

## Tier 6 — Orders (depends on: Product, Store, **and a buyer/customer domain not covered anywhere in marketplace.php or this repo**)

⚠️ Every route here also depends on a **Customer/buyer account model** — the party placing the order. That domain doesn't exist in `backend-api`'s marketplace module (it's presumably the separate website/customer-facing app) and has never been part of this repo's scope at all. Treat this as a real blocking dependency, not just "Product must exist first."

- [ ] `GET /orders/` — list
- [ ] `GET /orders/{order:uuid}` — show
- [ ] `POST /orders/{order:uuid}/manual/fulfill`
- [ ] `GET /orders/{order:uuid}/packing/{size}`
- [ ] `POST /orders/fulfill/weight/{order:uuid}`
- [ ] `POST /orders/cancel/{order:uuid}/`
- [ ] `POST /orders/refund/{order:uuid}/partial`
- [ ] `POST /orders/export-csv`
- [ ] `GET /orders/timeline/`
- [ ] `GET /orders/timeline/{order:uuid}`
- [ ] `GET /orders/timeline/{order:uuid}/email/{event}`
- [ ] `GET /orders/timeline/{order:uuid}/email/{event}/{timeline:uuid}`
- [ ] `POST /message/initiate`
- [ ] `POST /easypost/{store_orders:uuid}/rates`
- [ ] `POST /easypost/{store_orders:uuid}/rates/buy/{rate}`
- [ ] `POST /easypost/void/{easy_posts:uuid}`
- [ ] `POST /easypost/webhooks`

## Tier 7 — Post-purchase (depends on: Orders)

- [ ] `POST /reviews/{itemReview:uuid}/reply/`
- [ ] `POST /reviews/{itemReview:uuid}/report/`
- [ ] `GET /reviews/report/`
- [ ] `GET /store/reviews` — public review list for the store
- [ ] `GET /chat/list`
- [ ] `GET /chat/list/{uuid}`
- [ ] `GET /chat/unreadCount`
- [ ] `POST /chat/report/{uuid}`
- [ ] `PUT /chat/unblock/{uuid}`
- [ ] `POST /seller/chat/email`

## Tier 8 — Money settlement (depends on: Orders, Stripe Connect)

- [ ] `POST /stripe/webhook`
- [ ] `POST /stripe/payouts`
- [ ] `GET /finance/` — overview list
- [ ] `POST /finance/export`
- [ ] `POST /finance/export-details`
- [ ] `POST /finance/{payout:uuid}` — detail
- [ ] `POST /finance/transfer/{payout:uuid}` **(admin)**
- [ ] `POST /finance/reconcile/{payout:uuid}` **(admin)**
- [ ] `POST /finance/payouts/{store:uuid}/detail/{payout:uuid}` **(admin)**
- [ ] `POST /finance/payouts/{store:uuid}/overview` **(admin)**

## Tier 9 — Aggregation (depends on: everything above having real data)

- [ ] `GET /analytics/totalSales`
- [ ] `GET /dashboard/purchases`
- [ ] `GET /dashboard/sales`
- [ ] `GET /dashboard/reviews`
- [ ] `GET /dashboard/unfulfilled`

## Not part of the dependency chain — ops/dev utilities

No build-order relevance; these aren't product features.

- [ ] `DELETE /cache/{type}/{countryId}` **(admin, ops)**
- [ ] `GET /shopify/v2/migration/.../{storeDomainName}` **(ops)** — one-off Shopify migration
- [ ] `GET /shopify/v2/order/sync/.../{orderId}` **(ops)**
- [ ] `POST /product-image-migration` **(ops)**
- [ ] `PATCH /product-mark-valid` **(ops)**
- [ ] `GET /slack/.../webhook/` **(ops)** — dev-only Slack test route
- [ ] `GET /migration/strip-s3-urls/preview` **(ops)**
- [ ] `POST /migration/strip-s3-urls` **(ops)**

---

## Progress summary

| Tier | Done | Total |
|---|---|---|
| 0 — Reference data | 3 | 4 |
| 1 — Seller | 16 | 16 |
| 2 — Store | 3 | 11 |
| 3 — Store composition | 0 | 45 |
| 4 — Product | 0 | 14 |
| 5 — Bulk/Import/Shopify | 0 | 10 |
| 6 — Orders | 0 | 17 |
| 7 — Post-purchase | 0 | 10 |
| 8 — Money settlement | 0 | 10 |
| 9 — Aggregation | 0 | 5 |
| Ops/dev utilities | 0 | 8 |
| **Total** | **22** | **150** |

Tier 1 (Seller) is complete except the infra-only `/aws/presigned` route. Tier 2 (Store) has its three core CRUD routes done; Teams, Stripe Connect, and admin revoke are the remaining open items before Tier 3 can meaningfully start.
