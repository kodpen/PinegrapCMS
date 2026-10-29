---
name: collection-template-refs
description: A design template's reference field naming another tab's form (_cf.ref tab:<key>): resolved at Publish, forms written target first, the design export (C6b1)
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

## What is still open?
- C6b2 (`plans/designer-collections-steps.md`): sample records' reference values by the target's label, and a categories collection for a blog template.

Tests: `tests/collections_template_refs_test.php`.
