# Products — not implemented

The seller product routes are ported from `backend-api`. The items below were not copied as-is. They are recorded here so they are not treated as finished.

## Repository

- `ProductRepository::create()` and `update()` save the item, price, GTIN, policy, tags, catalog sections, variants, vary-by, customization, discount, and product images. They do not sync bundles or sizing charts. `backend-api`'s repository does.
- Product images in `about.images` replace the non-variant `item_medias` rows and set `items.media_url` to the first gallery whose type starts with `image`. With no image, the cover becomes `uploads/default-images/empty_product.webp`. Gallery lookup reads `mysql_reader`. Shopify still does not upload images into the gallery.
- Customization is saved when `about.customizations.enabled` is present. The check uses `isset`, so a present `enabled: false` still saves a row. Description and limit are stored only when `description_enabled` is true. The update rule rejects `limit` when it is greater than 0 and the description is enabled. A missing customization key deletes the row.
- Discount is `discount.value` times 100. A limited discount clears its dates. A missing `discount` key deletes the row. Create validation does not include `discount`, so an ACTIVE create cannot store one.
- Variants come from `item_variations`. Create writes attributes, options, combinations, and `vary_by`. Update replaces missing attribute names and options, then matches combinations by uuid. The item's domestic price becomes the lowest variant price when variants are sent.
- Variant images are linked from an existing gallery uuid. Shopify does not upload variant images here, and option lookups use the default database connection. `backend-api` reads those options from `mysql`.
- `createVaryBy()` returns only when both `item_variations` and `vary_by` are missing. Sending variations without `vary_by` still reads that key.
- Every saved product is attached to the store's `all` section, plus any section uuids in `section`. `order` is set on that `all` section directly because it is not fillable and the column has no default.
- Tags come from `category_tags.tags`. Missing tags detach every tag. New tag rows include a uuid so the column can be stored. `backend-api`'s bulk insert omits uuid, and it matches tag names with `BINARY`, which this app uses only on MySQL.
- Shopify field ownership is not applied. A seller update always replaces tags and sections.
- A selected `policy` uuid is now looked up on `policies` for the seller's store, matching `attachPolicy()`. A missing `policy` key is treated as null. `backend-api` reads `$data['policy']` directly.
- Shopify field ownership, `getMatchingCategory()`, and `createProductFromShopify()` are not in this repository. Those belong to the Shopify sync, which is still not started.

## Other gaps

- `GET /product/category` for a non-English `X-localization` joins `categories_translation` and falls back to the English name. Category lists are cached for 24 hours as Eloquent models. The non-English query also reads `categories.tag`.
- Facet order uses MySQL `FIELD()` only when the driver is MySQL. SQLite tests order by `display_order`. A non-English facet request uses a stored `facet_translations` row, and otherwise calls Google Translate and queues the result. The seller route reads `X-localization`. `backend-api`'s seller route does not, so it stays in English. Translate errors are written to the Laravel log. `backend-api` writes them to marketplace error tables. The key is `GOOGLE_TRANSLATE_API_KEY`.
- Bulk move to active still requires `is_valid = 1`. Shopify products are left in place unless the store has a row in `store_shopify_connections`, and then the request calls the Dragon status API.
- `POST /product/export-csv` writes `export_products` and queues `ExportProductsJob` on `mkt-medium`. The job writes the CSV to the `mps3` disk and emails the link. The email uses this app's seller layout. `product_id` is the slug of the title. A digital product still exports weight because the type check compares the stored number with the string `DIGITAL`.
- Category recognition reads `resources/marketplace/*CategoriesPrompt.json`. `backend-api` reads those files from the public storage disk.
- List and show return media, variants, tags, sections, policy, customization, discount, attributes, vary-by, active item facets, and `subscribe_save`. They do not return Shopify field-ownership flags. Saving `subscribe_save` does not write `items.subscribe_save_available`. Saving an item queues `ClassifyItemToFacetsJob` on `mkt-medium`, which writes `item_facet_mappings` and `item_facet_classification_logs`, then chains `SyncProductToIndexJob` so Elasticsearch gets the new facet mappings. A title or description change deletes the stored translations of that field from `item_value_translations`. `marketplace:classify-item-facets` runs the same classification. Facet reads and category recognition use `mysql_reader`. The classification log model does not convert timestamps. Classification uses `OPEN_AI_KEY_FACET_CLASSIFICATION`. The index uses `ELASTICSEARCH_CLOUD_ID` and `ELASTICSEARCH_API_KEY`.
