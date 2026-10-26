---
name: designer-collections-steps
description: Plan (collections half of designer-interactions-collections, steps C1–C7): typed collections over designer forms; schema asks waiting on the owner
---

## What is the shape?
- A collection is a designer form (`custom_form_pages`) with a flag; its items stay `forms`/`form_data` rows, so RSS, JSON-LD, translation, search, comments and the list/detail widgets keep working. Classic forms and pages are untouched.
- Kinds over the existing `form_fields.type` (never altered): text = text box, rich text = text area + `wysiwyg`, date = date, file = file upload, option set = pick list/radio/check box; new: number, image, reference (collection, product, page), slug.
- A reference value is the target's `reference_code` (several joined by `, `, as multi values are), read with the `form_data.data(100)` index; taxonomy = a reference to a tags/categories collection.
- The slug is `forms.address_name` (pretty URLs), today made from the Title field; a slug field only changes its source.
- The canvas stays the record: kinds live on the control (`props._cf.kind`, `_cf.ref`) and the save derives row columns, like `translate_values`.

## Which steps?
- **C1 — reference resolution in record widgets (no schema).** New module `includes/fn/collections.php`. `form_list_view`/`form_item_view` bindings get `_bindParams.<attr>.ref {page, field}`: the value is read as reference codes (or address names) of that form's records and the bound field of the target printed instead (one query per render for all rows). Only complete records, never `office_use_only` fields; translated like own values. Bind Data panel twin, canvas placeholder, tests.
- **C2 — collection flag and field kinds (Q1, Q2).** Migration step, `pg_collections_ready()`; form settings switch "Use as a collection"; Form Field panel "Kind" (number, image, reference + target, slug); `_pg_cf_controls()` / `pg_cf_sync_page_form()` write the columns; `list_form_fields` returns them.
- **C3 — typed inputs.** Number (`type=number`, server numeric check), image (`accept`, preview, `pg_img_responsive_html()` on output), reference picker (select filled at render from the target's records; server accepts only a live code of the target), product/page pickers; `edit_submitted_form.php` shows the same.
- **C4 — list/detail by kind.** C1's `ref` defaults from the field; numeric sort for number; taxonomy filter "references" valued from the address (`?category=news` by address name, `form-list-url-filters.md` rules); "records that reference this one" on a detail page; product/page reference tokens (name, link, price, image).
- **C5 — slug field and feeds.** A slug field feeds `update_submitted_form_address_name()`; RSS `category` and JSON-LD print a reference's label, not its code.
- **C6 — templates opt in.** Blog, menu, events, staff templates add the flag and kinds (`design-templates-file-format.md`); a categories collection for blog. Installed sites change nothing.
- **C7 — item revisions (Q3).** `record_revisions` on the `page_revisions` pattern from `custom_form.php` and `edit_submitted_form.php`.
- Item scheduling: templates already do it with a `publish_date` field and a live filter (`design-template-news-publishing.md`); no columns unless the owner wants them (Q4).

## Which schema changes wait on the owner?
- Q1 `custom_form_pages.collection` TINYINT UNSIGNED DEFAULT 0: pickers and lists must find collections without loading every design tree.
- Q2 `form_fields.collection_kind` VARCHAR(32) DEFAULT '' and `form_fields.reference_page_id` INT UNSIGNED DEFAULT 0: list, feed, filter and API read the kind and target without the form page's tree; VARCHAR, not ENUM, so a new kind needs no migration.
- Q3 table `record_revisions` (C7).
- Q4 `forms.publish_at` / `unpublish_at`: recommended no.

## Where to start?
- C1 needs no schema: start there once this file lands. C2 waits for Q1/Q2. `designer-interactions-collections.md` should point here once #1922 (which edits it) lands.
