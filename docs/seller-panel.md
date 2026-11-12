# Seller panel API

Progress tracker for moving the seller panel from `backend-api` into this app.
Opened 9 Nov 2026. Update the Status column in this file only.

Seller panel means the routes the seller dashboard calls: `auth:marketplace` routes in `backend-api/routes/marketplace/marketplace.php`, plus `routes/seller-panel/seller-panel-routes.php` and the Shopify credential-refresh cron.

Buyer routes, `auth.super` admin tools, the Shopify microservice, one-off migration scripts, and team management stay in `backend-api`.

Status values: `Not started` · `Partial` · `Done` · `Out`

| | Modules | Routes |
|---|---|---|
| Done | 17 | 129 |
| Partial | 0 | 0 |
| Not started | 1 | 12 |
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
| 12 | Reviews | 4 | 4 | Done | Store review list, seller reply, report, and report reasons. Buyer review creation stays in `backend-api`. A reply does not send the social push. |
| 13 | Chat | 7 | 7 | Done | Thread list, conversation, unread count, report, unblock, initiate message, and chat email. The email route is public. |
| 14 | Dashboard and analytics | 5 | 5 | Done | Purchases, sales, reviews, unfulfilled, and total sales. Total sales groups with MySQL `DATE_FORMAT`. |
| 15 | Stripe Connect, webhooks, and seller finance | 9 | 9 | Done | Connect onboarding, account, subscription, and payment-capture webhooks, the Stripe payout login link, and the finance overview, detail, and both CSV exports. Admin transfer and reconcile stay in `backend-api`. The subscription webhook does not hand Boost events to the social app. |
| 16 | Store subscription billing | 16 | 16 | Done | Plans, pricing, current plan, cards, plan changes, estimates, retry, invoice PDF. `clearSubscription` takes `store_id` from the request. Creating a plan, changing a card, and the tax estimates call Stripe. |
| 17 | Subscribe & Save seller setting | 1 | 1 | Done | Allowed percents, the product `subscribe_save` block, saving a product discount, and the product list filter. Customer offers, the availability column, and the internal admin routes stay in `backend-api`. |
| 18 | Shopify connect | 12 | 0 | Not started | OAuth, install, link, resync, category and policy mapping, credential refresh |
| 19 | Master login | 2 | 2 | Done | Staff password and OTP into a seller panel. The token scope is `master-login`. The OTP goes to `SELLER_MASTER_LOGIN_2FA_EMAIL`. |

## Missing features

Checked against the code on 11 Nov 2026. Copied bugs stay in the module notes linked above. Shopify connect is the only module not started.

| Feature | Where | Status | Where it stands |
|---|---|---|---|
| EasyPost | Shipping places and order labels | In the app | Address places, label rates, buy, void, and the webhook use the EasyPost PHP SDK with `EASYPOST_API_KEY`. |
| ConvertTimeZone | Sections, policies, shipping, orders, and the other seller models that use it in `backend-api` | In the app | `created_at` and `updated_at` are converted to the bound store timezone. The same accessors cover `next_payment_attempt`, `current_period_start`, and `current_period_end`. |
| `mysql_reader` | Sections, shipping, products, orders, finance | In the app | Seller reads that `backend-api` sends to `mysql_reader` use that connection. Tests share the SQLite database under the same name. |
| Product payload | Sections, policies | Partial | Product list and show load media, variants, tags, sections, policy, customization, discount, and vary-by. Section and policy item lists use `ProductResource` but only eager-load sections and media, so the other fields stay out. |
| OpenAI | Product description, category recognition, and facet classification | In the app | Description uses `OPEN_AI_PRODUCT_DESCRIPTION_KEY`. Category recognition uses `OPEN_AI_PRODUCT_CATEGORY_KEY`. Facet classification uses `OPEN_AI_KEY_FACET_CLASSIFICATION`. |
| Google Translate | Product facets | In the app | A non-English facet request calls Google Translate with `GOOGLE_TRANSLATE_API_KEY` when `facet_translations` has no row. Category names for another `X-localization` come from `categories_translation`. |
| Shopify Dragon | Product bulk move | Partial | A store with a Shopify connection asks Dragon for product status before activation. `config/shopify.php` reads `SHOPIFY_DRAGON_URL` and `SHOPIFY_DRAGON_TOKEN`. Those keys are not in `.env.example`. The Shopify connect routes are not started. |
| S3 credentials | Gallery upload | In the app | `POST /aws/presigned` calls the `mps3` disk. The S3 package is installed. A live upload needs `MP_AWS_KEY`, `MP_AWS_SECRET`, `MP_AWS_REGION`, `MP_AWS_BUCKET`, and `MP_AWS_PATH`. |
| Redis cache flush | Shipping save | Partial | `forgetAllByPattern()` expects Redis. The test cache is the array store, so the flush fails quietly and the save still succeeds. |
| Support-phone email partial | Order, chat, finance, and Stripe email | In the app | `marketplace.emails.header`, `footer`, `main-title`, and `partials.support-phone` are included by those emails. |
| `product_policies` | Policies | Out | That table is unused by the policy endpoints in `backend-api`. It was not created. Items are linked with `items.policy_id`. |
