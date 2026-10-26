---
name: page-type-pane-in-designer
description: Designer settings Page Type pane: a classic page's type fields, Layout Type and form editor links via edit_page.php?properties=1 (JSON), rights shared with edit_page.php.
---

## Which page gets the pane?
- A saved page of the design whose `page_type` is not `standard` (the server answers "no settings of its own" for a type without fields or layout, `pg_page_type_pane_has_fields()`): a classic page converted into a design keeps its type and draws its system region from it. `pg_designer_load_pages()` and the editor's page objects carry `page_type`; `_sdPageTypeSync(p)` (from `_pgLoadPageIntoFields()`) shows the pill (`tab-page-type-btn`, printed `d-none`) and drops another page's fields.
- `edit_page.php` sends every visual-design page to the designer, so without the pane a converted page's type fields (product group, columns, next page…) and Layout Type were out of reach.

## How are the fields read and saved?
- `edit_page.php?properties=1` hands over to `pg_page_type_pane_request()` (`includes/fn/page_type_pane.php`) before the redirect and the HTML not-found check, and always answers JSON with a status.
- GET: `pg_page_form_type_fields()['properties']` for the page's own type (the classic System Options card with the Layout Type radio) and `links`. The editor fills the pane with jQuery `.html()` (the inline scripts set the `original_*_form` globals the `show_or_hide_*()` switches read), removes the hidden rows of other types (id not starting with the type's prefix, `layout_type_row` kept), hides the `*_form_notice` boxes (they name the classic "Save & Continue"), opens `#options_row` and runs `pgInitInjected()`. Rich-text fields (custom form messages) stay plain HTML textareas: no editor is loaded in the designer.
- POST: form token (`hash_equals`), folder edit access, the type right, the page lock (`pg_collab_may_edit_page()` with `collab_key`); for a custom form `pg_page_type_custom_form_errors()` (form name taken, `pg_cf_legacy_settings_errors()`) refuses with 422. Then `pg_page_type_properties_for_save()`, `pg_page_form_save_type_links()`, `create_or_update_page_type_record()`, `layout_type`, the pretty-URL address names of a custom form. Nothing else of the page is written. The reply carries `warnings` (values kept as stored) and a message when a custom shipping or billing form was just switched on. The pane sits inside the designer's form, so `_sdPageTypeSave()` posts its fields one by one as a submit would.
- Links (`pg_page_type_pane_links()`): the classic button bar's editors (custom form fields, form list/item view, custom shipping/billing form once on), opened in a new tab with `send_to` back to the designer; they replace the classic redirect to the form designer.
- Saved at once with the pane's own button, not with the design's Save or Publish, so `_pgPageUnchanged()` does not need it.

## Which rights apply?
- Those of `edit_page.php`, in shared functions: `check_edit_access()` on the folder, `pg_page_type_user_may_set()` (role < 3 any type; a basic user the type's `set_page_type_*` right, calendar types also `manage_calendars`), and `pg_page_type_properties_for_save()`, the per-type part of the handler moved out of it (#1851): a basic user keeps the stored contact group, private folder or custom form beyond their reach, the offer stays without the commerce right.
- Layout Type: `pg_page_type_pane_layout_type()`: administrators and designers pick either, others only back to `system`; a type without a layout keeps its value.

## Which types does it handle?
- Every type with fields or a layout (#1819 the simple ones, #1851 the rest). The type itself is shown, never changed from the pane. Edit/Generate Layout is plan step Y3.

## Tests?
- `tests/page_type_pane_test.php`: which types have fields, the ladder, the layout rule, the endpoint's check order, the shared checks in edit_page.php, the wiring.
