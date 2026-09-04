# backend-api → apis Parity Report

Source: `backend-api/routes/marketplace/marketplace.php` (421 lines, ~30 route groups covering the *entire* marketplace API — not just seller/store). This repo's scope has only ever been the **Seller identity + Store identity** domain (per the original rebuild plan and every follow-up session since). This report inventories every module in that file and marks status against what actually exists in `apis` today, so scope is explicit rather than assumed.

## Legend

- ✅ **Done** — ported, tested, documented in Swagger
- 🟡 **Partial** — some routes in the module done, others not
- ❌ **Not started** — no equivalent exists in `apis`
- 🚫 **Out of scope** — different domain entirely (admin panel, ops/migration scripts, dev-only test routes); not part of a seller-facing API rebuild regardless of timeline

---

## In this rebuild's actual scope

### 1. Seller Auth & Identity — ✅ Done
`routes/marketplace/marketplace.php:200-215`

| Route | Status |
|---|---|
| `POST /seller/register` | ✅ |
| `POST /seller/login` | ✅ (+ inactive-store block added, backend-api didn't have this on Google login either — matched) |
| `POST /seller/login/otp` | ✅ |
| `POST /seller/password/forgot` | ✅ |
| `POST /seller/password/reset` | ✅ |
| `POST /seller/google/login` | ✅ |
| `GET /seller/me` | ✅ |
| `POST /seller/logout` | ✅ |
| `POST /seller/verify` | ✅ |
| `GET /seller/onboarding/status` | ✅ |
| `PUT /seller/{seller:uuid}` | ✅ (null-out bug fixed) |

### 2. Seller Security (`store/settings/security/*`) — ✅ Done
`marketplace.php:219-227`

| Route | Status |
|---|---|
| `POST .../security/current-password` | ✅ as `POST /seller/password/current` |
| `POST .../security/new-password` | ✅ as `POST /seller/password/change` |
| `POST .../security/2fa-verification` | ✅ as `POST /seller/2fa/otp` |
| `POST .../security/2fa-verification/enable` | ✅ as `POST /seller/2fa/enable` |
| `POST .../security/2fa-verification/disable` | ✅ as `POST /seller/2fa/disable` |

### 3. Store Core — 🟡 Partial
`marketplace.php:257-260`

| Route | Status |
|---|---|
| `GET /store` | ✅ as `GET /seller/store` |
| `POST /store` (create) | ✅ as `POST /seller/store` |
| `POST /store/{uuid}` (update) | ✅ as `PUT /seller/store` (collapsed backend-api's two-step create→activate flow into one, deliberate — see `docs/store.md`) |
| `GET /store/address` | ❌ depends on an `Address` model under the Shipping/Packaging domain, which doesn't exist here |

### 4. Store Admin Revoke — 🚫 Out of scope
`marketplace.php:185-187`

`POST /store/invalidate/{seller:uuid}` (revoke all a seller's tokens) runs on `auth.super` — an internal admin guard that doesn't exist in this app at all. Not a seller-API gap; it's a missing admin panel.

### 5. Teams — 🚫 Out of scope (explicitly excluded, discussed earlier this session)
`marketplace.php:229-239` — 5 routes (list/add/remove/accept-invite/invite-status). Multi-user store access; excluded from the original rebuild plan alongside admin master-login.

---

## Everything else in marketplace.php — never in scope

These are separate product domains bundled into the same backend-api Laravel app. None were part of any Seller/Store rebuild plan; listed here purely so nothing is silently assumed to be "missing."

| Module | Routes | Lines | Notes |
|---|---|---|---|
| **Store Sections** (catalog organization) | 10 | 242-253 | Product/Catalog domain |
| **Store Gallery** | 1 | 254-256 | Product/Catalog domain |
| **Store Reviews list** | 1 | 261 | Product/Catalog domain |
| **Product** (CRUD, AI description, CSV export, category facets) | 10 | 294-306 | |
| **Orders** (list/show/fulfill/cancel/refund/timeline/CSV) | ~13 | 308-333 | |
| **Policy** (return/shipping policies) | 7 | 345-354 | |
| **Reviews** (reply/report/report-list) | 3 | 339-343 | |
| **Chat** (list/conversation/unread/report/unblock) | 5 | 356-362 | |
| **Chat Email** | 1 | 404 | |
| **Message** (initiate) | 1 | 335-337 | |
| **Dashboard** (purchases/sales/reviews/unfulfilled blocks) | 4 | 364-369 | |
| **Subscribe/Billing** (Stripe subscription plans, cards, pricing) | 15 | 371-387 | |
| **Stripe** (connect, webhooks, payouts) | 5 | 163-169 | |
| **Finance/Payouts** (overview, export, super-admin payout tools) | 8 | 178-198 | Split across seller-facing + `auth.super` groups |
| **Wallets** (admin point adjustment) | 1 | 189-191 | 🚫 admin (`auth.super`) |
| **Shipping/Packaging** (rates, custom methods, address autocomplete) | 8 | 276-285 | |
| **EasyPost** (label rates, buy, void, webhooks) | 4 | 171-176 | |
| **Import** (CSV product import) | 4 | 264-269 | |
| **Bulk** (bulk product list/update) | 2 | 271-274 | |
| **Shopify** (product sync/delete, order fulfill, connect) | 4 | 287-292 | Separate `auth.shopify` guard |
| **Shopify v2 migration** (public, hardcoded-token URLs) | 2 | 151-153 | 🚫 one-off migration utility |
| **Misc lists** (country/currency/language) | 3 | 389-393 | |
| **Analytics** (total sales) | 1 | 395-397 | |
| **Currency convert** | 1 | 400-402 | |
| **AWS** (S3 presigned URLs) | 1 | 159-161 | |
| **Theme** (sync items for theme) | 1 | 406-408 | 🚫 admin (`auth.super`) |
| **Cache** (forget cache entry) | 1 | 410-412 | 🚫 admin (`auth.super`) |
| **Product index trigger** | 1 | 414-416 | 🚫 admin (`auth.super`) |
| **Migration utilities** (strip S3 URLs, product image migration) | 3 | 155, 418-421 | 🚫 one-off ops scripts |
| **Slack webhook test route** (public, hardcoded URL) | 1 | 157 | 🚫 dev/test utility |

---

## Bottom line

Everything backend-api has under **Seller identity, Seller security, and core Store identity** (create/read/update — the three things a seller needs to exist and manage their own account/store) is now ported, tested, and documented — 20 of 21 routes in scope, the one exception (`GET /store/address`) blocked on a Shipping/Address domain that doesn't exist here. Team management and the admin-only store-revoke endpoint were reviewed and confirmed intentionally excluded, not missed.

Every other module listed above (Product, Orders, Billing, Shipping, Chat, Reviews, Policies, Shopify, Finance, Analytics, Dashboard, and all `auth.super` admin tooling) is a distinct product domain that has never been part of this rebuild's scope. If any of those need to be planned next, that's a set of new, separate efforts — not gaps in the Seller/Store work done so far.
