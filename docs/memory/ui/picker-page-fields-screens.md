---
name: picker-page-fields-screens
description: Page picker fields outside the page form (#8803): product builder, Edit Offer, short links, menu item, calendar Next Page; what stays a select or a list and why
module: picker
---

## Which fields became page picker fields?
- Product builder: Recurring profile disabled e-mail page, Set Customer's Start Page to, gift card e-mail page, E-mail Additional Page to Customer, Add Comment page (kind `page`; `pg_picker_script()` printed once before `</main>`).
- Edit Offer: Button page of the upsell (`upsell_page_id`, id `pg-if-upsell-page`). The hidden input carries `data-on="upsell-page"` through the field's `value_attributes` option, so `offer_editor.js` reads a pick as it read the select; the render no longer writes the select's value.
- Menu item: Page (`link_page_id`). A typed URL empties it with `pgPicker.clear()`; the Summary shows the field's text.
- Calendar event: Next Page (`next_page_id`).
- Short link form: Page (`page`), Catalog Page (`page_catalog`), Catalog Detail Page (`page_catalog_detail`), each with `invalid` from liveform. Choosing one of the two catalog fields empties the other (`pgPicker.clear()`).
- File Manager short link window: `explorer_short_link_options` sends `page_fields` (page, catalog_page, catalog_detail_page), drawn with the link's page; `pg_short_link_page_slots()` puts a product group link's page in Catalog Page when its type is catalog, as `pg_short_link_form_values()`. A new link opened from a page's panel sends `page_id` and gets that page drawn. The window reads picks by posted name (`shortLinkPicked()`).

## What happens to a stored page the list used to keep?
- The `keep` arguments went (`keep_page_id`, `$keep_next_page_id`, the short link form's `keep.page_id`): the field shows the stored page however it is (archived, binned, a role 3 user's closed folder, gone) and posts it unchanged (`page-form-page-fields.md`, `picker-field.md`). The saves keep their checks (`commerce/short-link-recycle-bin.md`).
- The File Manager window caches the product lists only; a link with a page always asks for its own fields (`shortLinkTargetMissing()`).

## What stays a select or a list?
- The product builder's custom form select and the e-mail campaign profile's two page selects (Body Page lists by view access, the custom form by `pg_form_page_sql()`): PK6c of the plan.
- The list screens' quick edit keeps its own lists (short link pages, the users' start page): its searching cell takes a `source` answering `{results}` (`list-table-quick-edit-search.md`), which picker.php does not answer, and the list is read once per screen, not per row.

## What did pgPicker gain?
- `pgPicker.clear(node)`: empties the picker field at or inside the node and fires `change` when it held a value.

Tests: `tests/picker_page_fields_screens_test.php`.
