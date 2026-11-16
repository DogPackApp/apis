# Shopify connect

The seller Shopify connect routes in `backend-api` are the Shopify v1 integration. They are unused. The code was left in place. Do not move them.

`backend-api` still serves them under `/api/marketplace/shopify-connect`.

Jira: [MKT-2537](https://dogpack-team.atlassian.net/browse/MKT-2537), a subtask of [MKT-1718](https://dogpack-team.atlassian.net/browse/MKT-1718).

## Do not move

| Method | Path | Controller | What it does |
|---|---|---|---|
| GET | `/auth` | `ShopifyConnectController` | Starts the OAuth handshake. Body requires `shop_domain`. Returns the Shopify URL. |
| GET | `/sync-status` | `ShopifySyncStatusController` | Returns the shop sync status. |
| POST | `/link-shop` | `ShopLinkController` | Links a Shopify shop to the store. |
| POST | `/store` | `ShopConnectController` | Saves the connected shop. |
| POST | `/resync` | `ProductResyncController` | Resyncs products from Shopify. |
| POST | `/update-category` | `CategoryMappingController` | Maps Shopify categories. |
| POST | `/update-default-policy` | `ProductPolicyUpdateController` | Maps the default product policy. |
| POST | `/product-field-sync` | `ProductFieldSyncInitiateController` | Starts a product field sync. |
| GET | `/install` | `ShopifyInstallController` | Starts the app install. |
| GET | `/install/callback` | `ShopifyInstallCallBackController` | Install OAuth callback. |
| GET | `/auth/callback` | `ShopifyCallBackController` | Connect OAuth callback. |
| POST | `/refresh-credentials` | `AccessTokenRefreshController` | Refreshes Shopify access tokens for all stores. |

## Started

These three routes are in this app at the same paths Shopify already calls. They use `X-Shopify-DP-Token: Bearer {SHOPIFY_HMAC_TOKEN}` and `X-Shopify-DP-Store: {store uuid}`.

| Method | Path | What it does |
|---|---|---|
| POST | `/api/marketplace/shopify/product/create` | Creates or updates a product from Shopify. A new product is stored only when `status` is `ACTIVE`. |
| POST | `/api/marketplace/shopify/product/delete` | Archives the product when the store has a Shopify connection. Otherwise it marks the legacy product deleted. |
| POST | `/api/marketplace/shopify/order/fulfill` | Marks the matching order fulfilled. |

## Stays in backend-api

The rest of the Shopify microservice and the one-off migration routes are not part of this move.

- `POST /api/marketplace/shopify/connect`
- `PUT /api/marketplace/shopify/update-sync-status`
- `DELETE /api/marketplace/shopify/access-token/{id}`
- `DELETE /api/marketplace/shopify/shop/{shopifyShopId}`
- `GET /api/marketplace/shopify/v2/migration/{token}/{storeDomainName}`
- `GET /api/marketplace/shopify/v2/order/sync/{token}/{orderId}`

## Already in this app

Bulk move to active already calls Dragon when `store_shopify_connections` has a row for the store. `config/shopify.php` reads `SHOPIFY_DRAGON_URL` and `SHOPIFY_DRAGON_TOKEN`. Both keys are blank in `.env.example`.
