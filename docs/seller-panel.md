# Seller panel API

Progress tracker for moving the seller panel from `backend-api` into this app.
Opened 9 Nov 2026. Update the Status column in this file only.

Seller panel means the routes the seller dashboard calls: `auth:marketplace` routes in `backend-api/routes/marketplace/marketplace.php`, plus `routes/seller-panel/seller-panel-routes.php` and the Shopify credential-refresh cron.

Buyer routes, `auth.super` admin tools, the Shopify microservice, one-off migration scripts, and team management stay in `backend-api`.

Status values: `Not started` · `Partial` · `Done` · `Out`

| | Modules | Routes |
|---|---|---|
| Done | 10 | 86 |
| Partial | 1 | — |
| Not started | 7 | — |
| **In scope** | **18** | **141** |

Routes already in this app live under `/api/seller/*` and `/api/misc/*`. `backend-api` serves the same seller panel under `/api/marketplace/*`.

## Modules

| # | Module | Routes | Done | Status | What it covers |
|---|---|---|---|---|---|
| 1 | Reference data | 4 | 4 | Done | Country, state, currency, and language |
| 2 | Seller account | 11 | 11 | Done | Register, login, OTP, Google, forgot/reset password, me, logout, verify, onboarding, profile update |
| 3 | Seller security | 5 | 5 | Done | Current password, change password, 2FA send / enable / disable |
| 4 | Store | 4 | 4 | Done | Show, create, update, and store address |
| 5 | ~~Teams~~ | 5 | — | Out | Not moving. List, add, remove, accept invite, and invite status stay in `backend-api`. |
| 6 | Catalog sections | 10 | 10 | Done | Create, list, update, delete, move, reorder, section items. Follow-ups are in `docs/catalog-sections-todo.md`. |
| 7 | Policies | 8 | 8 | Done | Create, list, show, update, delete, default flag, and policy items. Follow-ups are in `docs/policies-todo.md`. |
| 8 | Shipping and packaging | 8 | 8 | Done | Rates, packaging, custom methods, address autocomplete and places. Follow-ups are in `docs/shipping-packaging-todo.md`. |
| 9 | Products | 11 | 11 | Done | Create, list, show, update, archive, bulk move, categories, facets, AI description, category recognition, and CSV export. Follow-ups are in `docs/products-todo.md`. |
| 10 | Import, bulk edit, and media | 8 | 8 | Done | Gallery list, S3 presigned upload, CSV import, and bulk product list/update. Subscribe & Save is not on the bulk payload. Follow-ups are in `docs/gallery-todo.md`. |
| 11 | Orders and EasyPost labels | 16 | 16 | Done | List, show, cancel, partial refund, manual fulfill, packing slip, weight update, CSV export, timeline, and email render. EasyPost rates, buy, void, and webhook. |
| 12 | Reviews | 4 | 0 | Not started | Store review list, seller reply, report, report list |
| 13 | Chat | 7 | 0 | Not started | Thread list, conversation, unread count, report, unblock, initiate message, chat email |
| 14 | Dashboard and analytics | 5 | 0 | Not started | Purchases, sales, reviews, unfulfilled, total sales |
| 15 | Stripe Connect, webhooks, and seller finance | 9 | 1 | Partial | `POST /seller/stripe/connect` is in. Account webhook, subscription webhook, payment-capture webhook, payouts, and the seller finance overview, detail, and both CSV exports are not. |
| 16 | Store subscription billing | 16 | 0 | Not started | Plans, pricing, current plan, cards, plan changes, estimates, retry, invoice PDF |
| 17 | Subscribe & Save seller setting | 1 | 0 | Not started | Seller allowed percents |
| 18 | Shopify connect | 12 | 0 | Not started | OAuth, install, link, resync, category and policy mapping, credential refresh |
| 19 | Master login | 2 | 0 | Not started | Staff login into a seller panel, and its OTP |

## Missing features

These are pieces of `backend-api` that the ported routes still do not have. Copied bugs stay in the module notes linked above.

| Feature | Where | What's missing |
|---|---|---|
| EasyPost | Shipping places and order labels | Address places still posts to the EasyPost HTTP API. Label rates, buy, void, and the webhook use the EasyPost PHP SDK with `MARKETPLACE_EASYPOST_API`. |
| ConvertTimeZone | Sections, policies, shipping | Responses do not include the converted timestamps from that trait. |
| `mysql_reader` | Sections, shipping | This app has no reader connection. Those queries use the default database connection. |
| Product payload | Sections, policies | Seller product list and show include media, variants, tags, sections, policy, customization, discount, and vary-by. Section and policy item lists still use that same resource. |
| OpenAI | Product description, category recognition, and facet classification | Description uses `OPEN_AI_PRODUCT_DESCRIPTION_KEY`. Category recognition uses `OPEN_AI_PRODUCT_CATEGORY_KEY`. Facet classification uses `OPEN_AI_KEY_FACET_CLASSIFICATION`. |
| Google Translate | Product facets | A non-English facet request calls Google Translate with `GOOGLE_TRANSLATE_API_KEY` when `facet_translations` has no row. Category names for another `X-localization` come from `categories_translation`. |
| Shopify Dragon | Product bulk move | A store with a Shopify connection asks Dragon for product status before activation. `config/shopify.php` has no URL or token yet. |
| S3 credentials | Gallery upload | `POST /aws/presigned` calls the `mps3` disk. The S3 package is installed. Set `MP_AWS_KEY`, `MP_AWS_SECRET`, `MP_AWS_REGION`, `MP_AWS_BUCKET`, and `MP_AWS_PATH`. |
| Redis cache flush | Shipping save | `forgetAllByPattern()` expects Redis. Only that method was ported, and the test cache is the array store. |
| Support-phone email partial | Shipping places | The invalid-address email does not include `marketplace.emails.partials.support-phone`. |
| `product_policies` | Policies | That table is unused by the policy endpoints in `backend-api`. It was not created. Items are linked with `items.policy_id`. |
