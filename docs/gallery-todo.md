# Gallery and media upload — not implemented

The seller gallery list and the S3 upload URL are in this app. The items below were not copied as-is.

- `POST /api/seller/store/gallery` lists the authenticated seller's gallery. `POST /api/seller/aws/presigned` creates the upload URLs.
- Product uploads insert `galleries` rows before the file is uploaded. The product create request can then send those uuids in `about.images`.
- `league/flysystem-aws-s3-v3` is installed. A real upload URL uses `MP_AWS_KEY`, `MP_AWS_SECRET`, `MP_AWS_REGION`, `MP_AWS_BUCKET`, and `MP_AWS_PATH`. Tests stand in for the disk.
- A missing `data` array on a product upload throws before the `Exception` catch, because `foreach` on null is an `Error`.
- `count` is required and is not used to decide how many product files are created. The `data` array does that.
- Gallery `url` prepends `services.marketplace_media` when that value is set and the stored path is not already a full URL. An empty base is left off so the stored path is returned as-is.
- CSV import stores the file on `mps3`, writes `import_products`, and queues validation on `mkt-high`. Processing creates or updates products from rows that already passed validation. A failed cell edit still calls `dd()` in the catch, which stops the request. The completion email uses this app's seller layout and does not convert the store timezone.
- Bulk list and bulk update edit title, price, variants, tags, sections, images, customization, and discount. Subscribe & Save is not included. A product id from a CSV row is stored on `item_csvs`. Category matching from that id is not run.
