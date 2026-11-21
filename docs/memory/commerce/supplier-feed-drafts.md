---
name: supplier-feed-drafts
description: Supplier feed drafts (SF4, F8): records no product matched become switched-off products with pictures, description, brand and group; one model code is one variant set; the spool files; Drafts go into
module: supplier_feed_drafts
---

## When is a draft made?
- In a pull's last phase, `drafts`, after close (`supplier-feed-sync.md`): a record whose item points at no product and is not `dismissed`. Every pull, the first included; a test pull counts them as Added and makes none.
- A draft a person deleted: the next pull finds its product gone, the item gets `dismissed` 1 and product 0, and no draft is made from it again. A draft in the Recycle Bin keeps its item, so none is made either.

## How do records of one model meet?
- During apply, each record with a model code, and each record that matched no product, is a JSON line in one of 16 spool files `data/temp/supplier_feed_<id>_drafts_<n>.dat`, chosen by the model code (else the key), so one model's records share a file wherever they sit in the feed.
- The drafts phase works one file a step (`state.bucket`); a file cut by the deadline is worked again, and a record already drafted is skipped by its item. The files go at the pull's end, a brake, a drop, a new pull and a deleted feed.

## What becomes what?
- `pg_supplier_feed_draft_groups()` (pure): by model, case-blind. A model with a product already in a variant set (a 'select' group): the new records join that set. Two or more new records of a model: a new variant set (`pg_catalog_variant_set_create()`). Anything else: a single product (`pg_pb_create_product()`).
- A set is named by the words its records' names all start with (`pg_supplier_feed_set_name()`), its group code is the model code; each variant's own name tells it apart in the set's select, no attribute is made (the feed maps none).

## What does a draft hold?
- `products.name` the supplier code (so the next pull matches it), short description the record's name, `enabled` 0, stock tracked at the record's stock, `brand`, the description cleaned as the editor's (`pg_sanitize_rich_text()`, 'staff'), the barcode (`pg_assign_product_barcode()`).
- Price: the feed's rule for its category or brand on the buy price (net of the record's VAT when prices include it), 0 without a saved rule, rate or buy price; first price history row source `supplier`.
- The item then points at it, `active` 1, with buy price and stock; the run counts it `added`.

## Which group?
- A browse group whose label or name is the category, or its last step ("Ev > Mutfak" finds "Mutfak"); none is made. Else "Drafts go into", a select under More fields on the Fields card, kept as `draft_group` in `supplier_feeds.mapping` (a read of the feed keeps it). Else no group; a new set then sits under the catalogue's top.

## How are pictures fetched?
- The mapped picture field and its repeats (`key[2]`…), a value split before each address, 10 at most (`pg_supplier_feed_record_images()`). Each through `pg_curl_run()` with `check_address` (no inside address, pinned), the upload cap, only an image answer, stored by `pg_file_store_upload()` in the root folder, owned by the feed's maker. The import's pure checks are reused (`includes/import/media.php`).
- One address shared by a model's records is fetched once. A picture that fails is a line in the run's detail, not a failed record.
