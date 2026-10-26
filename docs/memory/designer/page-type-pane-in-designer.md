---
name: page-type-pane-in-designer
description: The Page Type pane of the designer's settings modal: a classic page's type fields and Layout Type, read and saved through edit_page.php?properties=1 in JSON, the types it handles, the rights.
---

## Which page gets the pane?
- A saved page of the design whose `page_type` is not `standard`: a classic page converted into a design keeps its type and draws its system region from it. `pg_designer_load_pages()` and the editor's page objects carry `page_type`; `_sdPageTypeSync(p)` (from `_pgLoadPageIntoFields()`) shows the pill (`tab-page-type-btn`, printed `d-none`) and drops another page's fields.
- `edit_page.php` sends every visual-design page to the designer, so without the pane a converted page's type fields (product group, columns, next page…) and Layout Type were out of reach.

## How are the fields read and saved?
- `edit_page.php?properties=1` hands over to `pg_page_type_pane_request()` (`includes/fn/page_type_pane.php`) before the redirect and the HTML not-found check, and always answers JSON with a status.
- GET: `pg_page_form_type_fields()['properties']` for the page's own type, the classic System Options card with the Layout Type radio. The editor fills the pane with jQuery `.html()`, removes the rows printed hidden for other types (the classic type picker reveals them; the pane has no picker), opens `#options_row` and runs `pgInitInjected()`.
- POST: form token (`hash_equals`), folder edit access, the type right, the page lock (`pg_collab_may_edit_page()` with `collab_key`), then `pg_page_form_type_properties_from_post()`, `pg_page_form_save_type_links()`, `create_or_update_page_type_record()` and `layout_type`. Nothing else of the page is written. The pane sits inside the designer's form, so `_sdPageTypeSave()` posts its fields one by one as a submit would (unticked boxes not at all).
- Saved at once with the pane's own button, not with the design's Save or Publish, so `_pgPageUnchanged()` does not need it.

## Which rights apply?
- Those of `edit_page.php`: `check_edit_access()` on the page's folder and `pg_page_type_user_may_set()`, the role ladder both the form and the handler of `edit_page.php` now call (role < 3 any type; a basic user the type's `set_page_type_*` right, calendar types also `manage_calendars`).
- Layout Type: `pg_page_type_pane_layout_type()`, as the handler: administrators and designers pick either, others only back to `system`; a type without a layout keeps its value; anything but `system`/`custom` keeps the stored one.

## Which types does it handle?
- `pg_page_type_pane_types()`: email a friend, folder view, photo gallery, search results, update address book, custom form confirmation, catalog, catalog detail, order form, shipping method, order receipt, affiliate sign up form, calendar event view. The others answer `later` with a message; they carry rich-text editors, show/hide scripts, per-field access checks or a form-designer redirect (plan step Y2b, `plans/legacy-layouts-in-designer.md`).
- The type itself is shown, never changed from the pane.

## Tests?
- `tests/page_type_pane_test.php`: the types, the ladder, the layout rule, the endpoint's check order, the wiring.
