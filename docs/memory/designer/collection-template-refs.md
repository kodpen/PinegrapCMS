---
name: collection-template-refs
description: A template's reference into another tab's form (_cf.ref tab:<key>): Publish, forms target first, sample references by label, blog categories, export (C6b1, C6b2)
module: custom_form
---

## How does a template name another tab's form?
- A control writes `'_cf' => array('kind' => 'reference', 'ref' => '{{tab:<key>}}')`; `_pg_tpl_fill()` makes it `tab:<tab key>`. The form picker of the Form Field panel never writes a key: only templates do.

## What turns the key into a form at Publish?
- `pg_sw_page_link_resolve_tabs()` (`includes/fn/widgets.php`) swaps `props._cf.ref` beside the links' `_bindParams.*.page_id`; `pg_designer_resolve_tab_refs()` runs it on every widget row whose tree holds `"ref":"tab:` or `"page_id":"tab:`. The editor's `_swResolveTreeTabRefs()` makes the same swap in its cache and snapshots. A closed tab gives 0: no form (#3025).

## Why are the forms written in order?
- `pg_cf_sync_page_form()` keeps `reference_page_id` only while the target page has a form (`pg_coll_reference_target()`), so a posts form written before its categories form would lose the reference.
- `pg_cf_reconcile_plan()` reads each page's references from its form widgets; `pg_cf_reconcile_order()` (pure) puts a form after its targets, otherwise in the save's order. A self reference (related posts) needs nothing: the form row is made before the fields. In a loop the first one written is listed in `again` and written once more at the end.
- Used by the editor's save (`$cf_jobs`, `designer_screen.php`) and `pg_design_template_install()`; the targets' sample records exist before the referencing form is seeded.

## How does the design export carry it?
- `_pg_dtc_tokenize_props()` writes a `_cf.ref` naming a page of the design as `{{tab:<key>}}`, so a template made from a design re-imports with its references; a form outside the design keeps its id.

## How does a sample record name the record it points at?
- By the target record's label (C6b2, #3052): `_pg_cf_seed_given_records()` reads the field's `collection_kind` / `reference_page_id`, looks up the target form's records by their Title field (`_pg_cf_seed_reference_labels()`, read fresh, oldest first when two share a label) and stores the `reference_code` (`pg_cf_seed_reference_codes()`, pure: trimmed, any case).
- Several labels: one value joined by `, `; a check box group or a multiple pick list a row each. A label that names no record is left out, no error; a reference with none left gets no row.

## Which template ships a categories collection?
- hello-pinegrap 2.5.0 (#3052): staff page `blog_category_new` (form Blog Categories, `collection`, fields `name` (title role) and `description`, three sample categories), in the staff bar. The post form's `category` pick list is `_cf.kind 'reference'`, `ref '{{tab:blog_category_new}}'`, RSS role `category`; each sample post names its category; the post page prints its name with a `__ref` badge (`show 'name'`). Installed sites change nothing.

Tests: `tests/collections_template_refs_test.php`, `tests/custom_form_seed_test.php`, `tests/design_template_collections_test.php`.
