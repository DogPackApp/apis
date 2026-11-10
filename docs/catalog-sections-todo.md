# Catalog sections — not implemented

The seller section routes are a 1:1 port of `backend-api`. The items below are real problems in that code. They are recorded here so they are not "fixed" during the port.

## Response and URL

- Paths live under `/api/seller/store/section`, not `/api/marketplace/store/section`. The route list and payloads match.
- `GET /store/section/{uuid}/items` still eager-loads `items.sections` and `items.media`, but the product JSON is this app's `ProductResource`. It does not return media, sections, variants, tags, or policy. `backend-api`'s product resource does.
- `Gallery` and `item_medias` exist only so that eager load can run. The gallery API and the CloudFront `url` accessor are not ported.
- `ConvertTimeZone` is not ported. These section responses do not include those converted timestamps.
- `SectionService::fetchSections()` reads the default connection. `backend-api` reads `mysql_reader`, which this app does not have. The seller list endpoint does not call `fetchSections()`.

## Authorization

- A section is loaded by `uuid` only. The seller is not checked. Anyone with a seller token can rename, delete, or change items on another store's section if they know the uuid.
- Reorder writes `store_id` from the authenticated seller onto whatever section uuid is sent. That can move another store's section onto this seller.
- `backend-api` resolves the store through `getAdminUser()` so a team member acts as the store owner. Teams are not moving, so this app resolves the authenticated seller's own store.

## Reorder

- Item reorder calls `increment('order')` / `decrement('order')` on the item relation. The SQL updates `items.order`. `items` has no `order` column, so the request returns 422. The pivot column is `item_section.order`. `updateExistingPivot()` is never reached.
- The same controller only expects one item per request. Bulk reorder is still the original TODO in that file.
- Section reorder uses `updateOrCreate()` and mass-assigns `order`. `order` is not in `Section::$fillable`, so the saved order does not change. A uuid that does not exist tries to insert a section with no name.
- `sections.order` is a `tinyInteger`. Values above 127 do not fit.

## Other copied behavior

- Delete detaches every item, then deletes the section. `SectionService::moveToDefault()` exists and is not called. `SectionDeleteController` injects `SectionService` and does not use it.
- Create sets the next `order` from the latest row by `created_at`, not from `max(order)`.
- Slugs are not unique per store. Two sections with the same name get the same slug.
- The section list is cached forever as Eloquent models (`Cache::rememberForever`). The country list already broke on the database cache driver for that reason and now caches plain arrays.
- Validation stays inline. Failures return `{ "errors": { ... } }` with no top-level `message`, matching `backend-api`.
- A seller with no store does not get the store routes' 404. Resolving `Store` returns null and the section action errors.
