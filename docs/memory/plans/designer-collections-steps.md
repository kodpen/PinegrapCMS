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

- **C3 — typed inputs. Done, #2601:** number, image and reference pick list inputs with their server checks (`designer/collection-inputs.md`).

- **C3b — radio and check box references, image preview. Done, #2694** (`designer/collection-inputs.md`).

- **C3c — product and page pickers. Done, #2814:** new kinds `product` and `page` (VARCHAR needs no migration) in `pg_coll_kinds()` **and** its twin `_CF_KINDS` in `assets/js/style_designer.js` (a test compares them, so the step needs that file free); a pick list of products / pages held to them on the server.

- **C4a — lists by kind. Done, #2709:** numeric sort for number; reference filter by address name or code, printing the record's label (`designer/collection-lists.md`). Image output already gets `pg_img_responsive_html()` (every widget's output).

- **C4b1 — references by kind. Done, #2835:** C1's `ref` defaults from the field's kind and target (needs `style_designer.js`); a `__ref` on a product / page field prints the product's name, link, price, image or the page's name, link.
- **C4b2 — "records that reference this one"** on a detail page (a list filtered by the shown record's code).

- **C5 — slug field and feeds.** A slug field feeds `update_submitted_form_address_name()`; RSS `category` and JSON-LD print a reference's label, not its code.

- **C6 — templates opt in.** Blog, menu, events, staff templates add the flag and kinds (`design-templates-file-format.md`); a categories collection for blog. Installed sites change nothing.

- **C7 — item revisions (Q3).** `record_revisions` on the `page_revisions` pattern from `custom_form.php` and `edit_submitted_form.php`.

- Item scheduling: templates already do it with a `publish_date` field and a live filter (`design-template-news-publishing.md`); no columns unless the owner wants them (Q4).

## Which schema changes are approved?
- Approved in #2393 and done at C2 (step 11.0.43): Q1 `custom_form_pages.collection`, Q2 `form_fields.collection_kind` and `reference_page_id` (`designer/collection-fields.md`); Q3 table `record_revisions` is asked when C7 comes.
- Q4 `forms.publish_at` / `unpublish_at`: not added; the templates' `publish_date` field and live filter cover it.

## Where to start?
- C1–C4b1 are done. Next is C4b2 (records that reference this one; a detail page's list filtered by the shown record's code, `designer/collection-lists.md` has the filter). C5 does not depend on it. C5 does not depend on it.
