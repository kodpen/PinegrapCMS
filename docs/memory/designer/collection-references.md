---
name: collection-references
description: A form list or detail element prints a field of the record a reference points at (__ref binding, spec, one read per form, escaping, what is never read)
module: collections
---

## What does it do?
- A record keeps a reference as a value of one of its own fields: the other record's `reference_code`, or its `address_name`; several joined by `, ` like a multiple pick list. An element in `form_list_view` (and its three old names) or `form_item_view` prints a field of the record pointed at: a category's title, an author's photo, a tag's address name.
- No schema: any field can hold references (C2 adds field kinds, `plans/designer-collections-steps.md`).

## How is it stored?
- `_bindings.<prop> = '__ref'` with `_bindParams.<prop> = {field, page_id, show}` on text, alt, src or href. `field` and `show` are binding tokens (lowercased); `show` is a field of the other form or `reference_code` / `address_name`; `page_id` an id or `tab:<key>` until Publish (`pg_sw_page_link_resolve_tabs()` resolves every `_bindParams.*.page_id`).
- Normalised by `pg_coll_ref_spec()` (PHP, a tab reads 0) and `_swRefSpec()` (editor, keeps the tab).

## How does the renderer draw it?
- `pg_coll_ref_bindings(&$tree)` (module `includes/fn/collections.php`) turns each binding into `__ref_<n>`, beside the page-link pre-pass. After the records' fields are indexed (and translated), `pg_coll_ref_fill()` reads the references from the values as stored, loads the records every element points at with one query per form (`pg_coll_ref_load()`, cached per request) and puts the value under `__ref_<n>` in the record's fields.
- So `pg_sw_apply_tokens()` escapes, formats and filters it once like an own field (rich text filtered, a file field an address). Several references print joined by `, `, in stored order; one pointing at nothing is left out.
- Printed as stored, even with `translate_records` on: the translation extracts a widget's own records only, so a lookup of another form's text would miss and count against the page under the "reviewed" policy. Open: extract referenced values (C4).
- A path that does not expand it (another widget kind, a nested shared component): `_apply_bindings()` prints `#` for an href; text is swept empty.

## What is never read?
- Incomplete records (`forms.complete = 0`) and `office_use_only` fields of the other form. A record view does not apply the other form's own list filters (a "live" status), so a draft category's title can show; point only at what may be public.
- The editor offers the other form's visible fields; this form's own staff fields may hold the reference.

## Where is the editor side?
- Bind Data option "A field of a referenced record" (`SW_REF_TOKEN`, `_SW_REF_PROPS`) in the same widget kinds as the page link (`_swPageLinkOffered()`); `_sdRefRows()` draws the three selects, `_sdRefEdit()` writes them; `_setBinding()` seeds or drops the spec. Canvas text previews "Example".

Tests: `tests/collections_ref_test.php`.
