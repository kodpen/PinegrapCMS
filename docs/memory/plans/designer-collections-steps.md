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
- **Done:** C1 #1981 (`__ref` binding, `designer/collection-references.md`); C2 #1997 (step 11.0.43, flag and kinds, `designer/collection-fields.md`); C3 #2601, C3b #2694, C3c #2814 (typed inputs, `designer/collection-inputs.md`); C4a #2709, C4b2 #2897, C4b2b #2917 (lists, filters, "Shown record", `designer/collection-lists.md`); C4b1 #2835 (references by kind); C5a #2910 (feed categories print the label).

- **C5b — slug field.** A `slug` kind (text box) feeds `update_submitted_form_address_name()` before the Title field; `pg_coll_kinds()` and its twin `_CF_KINDS` in `style_designer.js` (a test compares them), the Form Field "Kind" option.

- **C6a — templates opt in.** In progress: the blog, staff, menu, events, article and story forms of the design templates carry `'collection' => 1`, their cover and photo file fields `_cf.kind 'image'`; each changed template's version raised. Installed sites change nothing.
- **C6b — template references.** Resolve `_cf.ref 'tab:<key>'` at Publish, create the forms in order (target first), seed sample records by label, map `_cf.ref` in the design export; then a categories collection for blog.

- **C7 — item revisions (Q3).** `record_revisions` on the `page_revisions` pattern from `custom_form.php` and `edit_submitted_form.php`.

- Item scheduling: templates already do it with a `publish_date` field and a live filter (`design-template-news-publishing.md`); no columns unless the owner wants them (Q4).

## Which schema changes are approved?
- Approved in #2393 and done at C2 (step 11.0.43): Q1 `custom_form_pages.collection`, Q2 `form_fields.collection_kind` and `reference_page_id` (`designer/collection-fields.md`); Q3 table `record_revisions` is asked when C7 comes.
- Q4 `forms.publish_at` / `unpublish_at`: not added; the templates' `publish_date` field and live filter cover it.

## Where to start?
- Next is C5b (the slug field kind) or C6b (template references).
