---
name: collection-lists
description: Collection fields in lists and feeds (C4a, C4b2, C5a): numeric sort, reference filters printing the label, records referencing the shown record, feed categories by label, images
---

## How does a number field sort?
- `_render_system_widget_form_list()`'s order resolver reads the field's `collection_kind`; a number field (`pg_coll_field_kind()`) sorts by `pg_coll_number_order_sql()`: empty values last in either direction, then `CAST(data AS DECIMAL(65,15))`. The stored text is plain decimal (`pg_coll_number_value()`, `collection-inputs.md`), so the cast is exact. Other fields keep the text order.

## How does a list filter on a reference?
- `pg_sw_form_list_filter_sql()`: a reference field (kind and `reference_page_id` from `pg_sw_filter_form_fields()`) compared with "is equal to" / "is not equal to" looks the value up with `pg_coll_reference_lookup()`: a complete record of the target form whose address name or reference code is the value byte for byte (address name first). Found: the code is compared; not found: the value as given (matches nothing, so the page is `empty` and noindex).
- Pick list, radio button: one row, compared with the code. Check box, multiple pick list: one row per code, counted as before. Text box (several codes joined by ", "): membership, `pg_coll_reference_holds_sql()` (`FIND_IN_SET`).
- A fixed filter value is looked up too, so a list may name an address name.

## What does the page print for ?category=news?
- `labels` (param => the record's Title, else its code) beside `params`. `pg_sw_url_filter_value()` prints the label for that param, the value for others; the title, `^^__filter_value^^`, the feed's channel title follow it. The canonical keeps `?category=news` (`params`). The label is text from the data, so the rule "only stored text is printed" (`form-list-url-filters.md`) holds.

## How does a detail page list the records that reference its record?
- Dynamic value `shown record` (lists only, C4b2): the filter's value is the code of the record the page shows, `pg_coll_shown_record_code()` (`?r=`, where a pretty record address also puts it; address-value rules). "Category is equal to" shown record lists the posts of the shown category; `reference_code` "is not equal to" lists the other records.
- On a reference field the code must name a complete record of the target form by its code (`pg_coll_reference_lookup()`, not an address name), else the list shows nothing. It does not check the page's record view filters: a draft category given with `?r=` lists its posts, held to the list's own filters.
- Not an address filter: no `params`, label, noindex or canonical change. No shown record: left out (like `url parameter`); `$request` null: counted in `address`, a record view closes. `pg_record_file_filter_sql()` treats it as an address value.
- Editor (#2917): `_swFilterDynamicOptions()` (`style_designer.js`) offers "Shown record" beside "URL parameter" on a list's panel only, saving `dynamic_value: 'shown record'` with an empty attribute; a record view's panel marks a stored one "(incompatible — choose again)". A test compares the offered values with the server's `$dynamic_values`.

## What does a feed print as a reference's category?
- The RSS `<category>` of a reference, product or page field prints the label, not the stored code (C5a, #2910): `pg_sw_feed_category_xml()` in `pg_sw_form_feed_parts()` and in the legacy form list `?rss=true` (`get_page.php`). `pg_sw_feed_categories()` splits each row at `||` and then as `pg_coll_ref_codes()`; labels from `pg_coll_reference_lookup()` (Title, else code) or `pg_coll_kind_options()` (product, page), cached per request. A code that names nothing prints the code; each category once. Other fields print their text. JSON-LD prints no category.

## Image output?
- Every system widget's output passes `pg_img_responsive_html()` (`pg_designer_render_system_widgets`, `designer.php`), so a bound image field gets its `srcset`.

Tests: `tests/collections_lists_test.php`, `tests/collections_feeds_test.php`.
