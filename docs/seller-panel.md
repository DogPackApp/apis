# Seller panel API

Progress tracker for moving the seller panel from `backend-api` into this app.
Opened 9 Nov 2026. Update the Status column in this file only.

Seller panel means the routes the seller dashboard calls: `auth:marketplace` routes in `backend-api/routes/marketplace/marketplace.php`, plus `routes/seller-panel/seller-panel-routes.php` and the Shopify credential-refresh cron.

Buyer routes, `auth.super` admin tools, the Shopify microservice, one-off migration scripts, and team management stay in `backend-api`.

Status values: `Not started` · `Partial` · `Done` · `Out`

| | Modules | Routes |
|---|---|---|
| Done | 5 | 40 |
| Partial | 2 | — |
| Not started | 11 | — |
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
| 7 | Policies | 8 | 0 | Not started | Return/shipping policy CRUD, default flag, policy items |
| 8 | Shipping and packaging | 8 | 0 | Not started | Rates, packaging, custom methods, address autocomplete and places |
| 9 | Products | 11 | 5 | Partial | Create, list, show, update, and archive are in. Bulk move, categories, facets, AI description, category recognition, and CSV export are not. |
| 10 | Import, bulk edit, and media | 8 | 0 | Not started | CSV import (4), bulk list/update (2), store gallery (1), S3 presigned upload (1) |
| 11 | Orders and EasyPost labels | 16 | 0 | Not started | Seller orders, cancel, partial refund, packing slip, timeline and email render (12). EasyPost rates, buy, void, webhook (4) |
| 12 | Reviews | 4 | 0 | Not started | Store review list, seller reply, report, report list |
| 13 | Chat | 7 | 0 | Not started | Thread list, conversation, unread count, report, unblock, initiate message, chat email |
| 14 | Dashboard and analytics | 5 | 0 | Not started | Purchases, sales, reviews, unfulfilled, total sales |
| 15 | Stripe Connect, webhooks, and seller finance | 9 | 1 | Partial | `POST /seller/stripe/connect` is in. Account webhook, subscription webhook, payment-capture webhook, payouts, and the seller finance overview, detail, and both CSV exports are not. |
| 16 | Store subscription billing | 16 | 0 | Not started | Plans, pricing, current plan, cards, plan changes, estimates, retry, invoice PDF |
| 17 | Subscribe & Save seller setting | 1 | 0 | Not started | Seller allowed percents |
| 18 | Shopify connect | 12 | 0 | Not started | OAuth, install, link, resync, category and policy mapping, credential refresh |
| 19 | Master login | 2 | 0 | Not started | Staff login into a seller panel, and its OTP |
