---
name: designer-collections-steps
description: Plan (collections half of designer-interactions-collections, steps C1–C7): typed collections over designer forms and their schema
---

## What is the shape?
- A collection is a designer form (`custom_form_pages`) with a flag; its items stay `forms`/`form_data` rows, so RSS, JSON-LD, translation, search, comments and the list/detail widgets keep working. Classic forms and pages are untouched.
- Kinds over the existing `form_fields.type` (never altered): text = text box, rich text = text area + `wysiwyg`, date = date, file = file upload, option set = pick list/radio/check box; new: number, image, reference (collection, product, page), slug.
- A reference value is the target's `reference_code` (several joined by `, `, as multi values are), read with the `form_data.data(100)` index; taxonomy = a reference to a tags/categories collection.
- The slug is `forms.address_name` (pretty URLs), today made from the Title field; a slug field only changes its source.
- The canvas stays the record: kinds live on the control (`props._cf.kind`, `_cf.ref`) and the save derives row columns, like `translate_values`.

## Which steps?
- **C1 — reference resolution in record widgets (no schema). Done, #1981:** the `__ref` binding in `form_list_view`/`form_item_view` (`designer/collection-references.md`).

- **C2 — collection flag and field kinds (Q1, Q2). Done, #1997:** step 11.0.43, the form switch, the Form Field "Kind" (number, image, reference + its form); slug moved to C5 (`designer/collection-fields.md`).

- **C3 — typed inputs. Done, #2601:** number (`type=number`, normalised server check), image (`accept`, extension check), reference pick list filled from the target's records (server accepts only a code of the target), on the site form and Add / Edit Submitted Form (`designer/collection-inputs.md`).

- **C3b — remaining inputs.** Radio button and check box references drawn from the target's records, then held to them by `pg_coll_check_field()`; image preview on the form; product and page pickers (new kinds, VARCHAR needs no migration). `pg_img_responsive_html()` on image output goes with C4.

- **C4 — list/detail by kind.** C1's `ref` defaults from the field; numeric sort for number; taxonomy filter "references" valued from the address (`?category=news` by address name, `form-list-url-filters.md` rules); "records that reference this one" on a detail page; product/page reference tokens (name, link, price, image).

- **C5 — slug field and feeds.** A slug field feeds `update_submitted_form_address_name()`; RSS `category` and JSON-LD print a reference's label, not its code.

- **C6 — templates opt in.** Blog, menu, events, staff templates add the flag and kinds (`design-templates-file-format.md`); a categories collection for blog. Installed sites change nothing.

- **C7 — item revisions (Q3).** `record_revisions` on the `page_revisions` pattern from `custom_form.php` and `edit_submitted_form.php`.

- Item scheduling: templates already do it with a `publish_date` field and a live filter (`design-template-news-publishing.md`); no columns unless the owner wants them (Q4).

## Which schema changes are approved?
- Approved in #2393 (step 11.0.43): Q1 `custom_form_pages.collection` TINYINT UNSIGNED DEFAULT 0 (pickers and lists find collections without loading every design tree); Q2 `form_fields.collection_kind` VARCHAR(32) DEFAULT '' and `form_fields.reference_page_id` INT UNSIGNED DEFAULT 0 (list, feed, filter and API read the kind and target without the tree; VARCHAR, not ENUM, so a new kind needs no migration); Q3 table `record_revisions` at C7 is asked when that step comes.
- Q4 `forms.publish_at` / `unpublish_at`: not added; the templates' `publish_date` field and live filter cover it.

## Where to start?
- C1 needs no schema: start there once this file lands; C2 follows it with Q1 and Q2.
