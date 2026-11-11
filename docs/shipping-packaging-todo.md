# Shipping and packaging — not implemented

The seller shipping routes are a 1:1 port of `backend-api`. The items below are real problems in that code. They are recorded here so they are not "fixed" during the port.

## Response and URL

- Paths live under `/api/seller/shipping`, not `/api/marketplace/shipping`. The route list and payloads match.
- Country rows inside a zone use this app's `CountryResource`, which adds the same flag URL the country list already returns.
- `ConvertTimeZone` is not ported. These responses do not include converted timestamps.
- `EasyPostService::createAddress()` uses the EasyPost PHP SDK. `ShippingPlacesController` catches the vendor `EasyPost\Exception\Api\InvalidRequestException`. Label buying stays with the orders module.
- `CacheService` here only has `forgetAllByPattern()`. It catches `Throwable`. `backend-api` catches `Exception`, and Laravel 13's array cache throws an `Error` from `getRedis()`, which would skip that catch and return 500 after the rows were written. The call still returns false and the request continues. `StoreFreeShippingService` only carries the two cache-key prefixes. The buyer shipping calculator is not part of this module.
- The invalid-address email does not include `marketplace.emails.partials.support-phone`. That partial is not in this app.

## Copied behavior

- Country ids are checked against `countries`. `backend-api` checks `countries_new`.
- `deleteShipping()` and excluded-state lookup read the default connection. `backend-api` reads `mysql_reader`, which this app does not have.
- `GET /shipping/rates` uses `where(store_id)->orWhereNull(store_id)` with no group. A later `where` on that query would leak other stores' rates.
- Zone `uuid` is not fillable. Create always assigns a new uuid in `creating`, so the uuid sent by the client is ignored. Zone rates do the same.
- An existing carrier rate must be sent as `shipping_rates.value`. A plain uuid string is read as an array and errors. The `custom` branch then `updateOrCreate`s with that whole value as the uuid.
- `shipping_type` 2 is treated as the carrier rate and saves no `shipping_rate_id`. The column does not allow null, so that save fails. The inline comment in `backend-api` says type 1 is the carrier.
- Zone rates always store `currency_id` as null. The create loop still evaluates `$currency->id` after assigning `$currency = null`, and the saved currency stays null. `GET /shipping/shipping` eager-loads that relation. `get()` never returns null, so the "Shipping zone not found." response is unreachable. An empty store returns `{ "data": [] }`.
- The packaging index is named `idx_packaging_store_default`. `backend-api` names it `idx_store_default`, which SQLite rejects because the policies table already uses that index name.
- Saving shipping deletes the store's zones, then inserts the new ones. Packaging is upserted by uuid and is not deleted first. The first package becomes the default when none is marked.
- Replacing an address soft-deletes the previous row. This app's `ShippingAddress` model uses soft deletes. `backend-api`'s model does not, so its delete is permanent. The places response includes `easypost_id` and `province`. `GET /seller/store/address` still omits both.
- A seller with no store does not get the store routes' 404. Resolving `Store` returns null and the action errors.
- Teams and `getAdminUser()` are not used. The store is the authenticated seller's own store.
- Foreign keys use `constrained()`. `backend-api` adds them later as `ON DELETE NO ACTION`. `country_id` is an unsigned integer so it can reference `countries.id`.
