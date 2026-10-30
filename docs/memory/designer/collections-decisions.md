---
name: collections-decisions
description: Typed collections over designer forms: why a form with a flag, the approved schema (#2393, #3051), why items have no schedule columns (#3746), what C1–C7 built and where it is described
---

## What is a collection?
- A designer form (`custom_form_pages`) with the collection flag; its items stay `forms` / `form_data` rows, so RSS, JSON-LD, translation, search, comments and the list/detail widgets keep working. Classic forms and pages are untouched; no new engine.
- Kinds sit over the existing `form_fields.type` (never altered): text, rich text (text area + `wysiwyg`), date, file, option set (pick list, radio, check box); added kinds: number, image, reference (collection, product, page), slug (`collection-fields.md`).
- A reference value is the target's `reference_code` (several joined by `, `), read through the `form_data.data(100)` index; a taxonomy is a reference to a tags or categories collection. The slug feeds `forms.address_name`.
- The canvas is the record: the kind lives on the control (`props._cf.kind`, `_cf.ref`) and the design save derives the row columns.

## Which schema was approved?
- #2393: Q1 `custom_form_pages.collection`, Q2 `form_fields.collection_kind` and `reference_page_id` (step 11.0.43, `collection-fields.md`).
- #3051: Q3 table `record_revisions` in the `page_revisions` shape (step 11.0.123, `collection-revisions.md`, `workflow/decisions-made-schema-3.md`).

## Why do collection items have no publish_at / unpublish_at? (#3746)
- Q4 refused (architect, 2026-10-30, `workflow/decisions-made-schema-6.md`): the design templates already schedule items with a `publish_date` field and a "Live and publish_date <= now" filter applied per request (`design-template-news-publishing.md`); nobody asked for unpublishing; `forms` exists on every site and can be large; two scheduling paths side by side would both need explaining.
- A future-dated item is held back by that filter; ask the architect again before adding schedule columns.

## What was built, and where is it described?
- C1 #1981: `__ref` binding prints a field of the referenced record (`collection-references.md`).
- C2 #1997: the flag and the kinds (`collection-fields.md`).
- C3 #2601, C3b #2694, C3c #2814: inputs by kind (`collection-inputs.md`).
- C4a #2709, C4b2 #2897, C4b2b #2917: lists, reference filters, "Shown record"; C4b1 #2835: product / page references (`collection-lists.md`, `collection-references.md`).
- C5a #2910: feed categories print the label (`collection-lists.md`); C5b #2980: the slug kind (`collection-fields.md`).
- C6a #2969: design template forms opt in (`collection-fields.md`); C6b1 #3025, C6b2 #3052: template references and seeded blog categories (`collection-template-refs.md`).
- C7a #3068: `record_revisions` writes, retention, erasure (`collection-revisions.md`); C7b #3099: History and restore, no API endpoints (`collection-history.md`).
