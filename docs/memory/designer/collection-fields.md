---
name: collection-fields
description: Collection forms and field kinds (11.0.43, C2): the three columns, where the kind is written and read, which kinds each field type takes, templates that opt in (C6a), no slug kind yet
---

## What is stored?
- `custom_form_pages.collection` (TINYINT, 0): the form is a collection; its records are items other forms' records point at. `form_fields.collection_kind` (VARCHAR(32), ''): `number`, `image`, `reference`, `product` or `page`; `form_fields.reference_page_id` (INT, 0): the form page a reference points into. Step `upgrade_2026_11_0_collections()` (11.0.43); owner's approval recorded in `plans/designer-collections-steps.md`.
- VARCHAR, not ENUM, so a new kind needs no migration; `form_fields.type` is never altered (classic screens read it).
- Every reader and writer probes `pg_coll_ready()` (all three columns): before the upgrade nothing changes.

## Where is the kind written?
- The canvas is the record: `props._cf.kind` and `props._cf.ref` on the control (`_pg_cf_node_record()`), carried by `_pg_cf_controls()` as `kind` / `reference_page_id`, written by `pg_cf_sync_page_form()` on every design save. Not three-state: a control without `_cf.kind` writes ''. The legacy field screens and the API never set it.
- `pg_coll_field_kind()` keeps a kind only on a type that can have it (`pg_coll_kinds()`; twin `_CF_KINDS`, a test compares them): number → text box, image → file upload, reference → text box, pick list, radio button, check box; product, page → pick list (C3c, the id is stored). A field changed to another type loses it.
- `reference_page_id` is kept only for a reference and only while that page has a `custom_form_pages` row (`pg_coll_reference_target()`).
- The collection flag is a form setting (`pg_cf_load_page_form_settings()` / `pg_cf_write_form_settings()`, key `collection`), the switch "Use as a collection" beside Pretty URLs.

## Where is it read?
- `pg_cf_load_page_fields()` (`kind`, `reference_page_id`; `_cfBuildFromFields()` copies them onto a rebuilt control) and `list_form_fields` (`collection_kind`, `reference_page_id`).
- The Form Field panel shows "Kind" when the page's form is a collection or the field already has a kind; a reference adds the form picker (`_swFormOptions()`; this form itself too: related posts).
- Inputs by kind: `collection-inputs.md` (C3); lists and filters by kind are C4.

## Which design template forms are collections?
- C6a (#2969): the blog (posts, journal, articles, stories), staff directory, cafe menu and events forms carry `'collection' => 1` in their page `form` (Publish writes it with the other form settings). Each template's `$file` helper adds `_cf.kind 'image'` to a field that takes only `image/*` (covers, photos, dish and event pictures, byline photos); attachments and CVs stay plain files. The template files are read only when a template is opened or published, so installed sites change nothing; the version of each changed template was raised.
- No `_cf.ref` yet: a template reference needs the target form's page id at Publish (C6b in `plans/designer-collections-steps.md`).

## Why is there no slug kind yet?
- A slug kind that does not feed the record's address name would be a visible but dead choice. It comes with C5, which makes `update_submitted_form_address_name()` read it before the Title field.

Tests: `tests/collections_kinds_test.php`, `tests/design_template_collections_test.php`.
