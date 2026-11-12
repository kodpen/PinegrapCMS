---
name: list-table-region-lists
description: Regions, ads and form fields on the list table driver (TD9c): printed whole, list names, quick edit fields and checks, what stays on the edit screen; menu items stay a plain table
module: region_list, field_list
---

## Which screens and list names?
- `view_regions.php`: one printed list per filter, named `view_regions_all_<kind>_regions` (`pg_region_list_names()`; an unknown filter is common regions). `view_ads.php` (`view_ads`, the ad region filter stays a session filter) and `view_fields.php` (`view_fields`). All are in `pg_list_table_printed_lists()`; sort codes are the column keys.
- Cells and columns live in `includes/fn/region_list.php` (ad, login, dynamic/designer/common regions, ads) and `includes/fn/field_list.php`; the screens only read rows and draw. The name links to the edit screen (`data-pg-open`); the old Edit buttons are gone.
- Fields print in the form's order; Position (1..n) is the default sort and brings that order back. Delete Selected posts `fields[]` (`ids_field`) to `delete_fields.php` unchanged; a designer form has no selection bar button and no quick edit.

## What changes in place?
- Ad region: name, display type, transition type and duration, autoplay, interval, continuous (checks: `pg_ad_region_name_error()`, `pg_ad_region_behavior_errors()` on the row with the new value). A static region shows its transition values empty but they still edit, as the edit screen keeps them.
- Login region: name (unique), Show Login Form. Dynamic region: name (administrators only). Designer/common region: name (`pg_region_form_name_refusal()`) and Region Type, except a common region made a shared component.
- Ad: name (unique), ad region (only regions the user may use; a user role edits only ads of its regions), label, sort order (`pg_ad_form_sort_order_error()`).
- Field: name (reserved characters, unique in the form), label and required (not for an information field), custom forms also RSS element and Office Use Only. After a save: `pg_field_form_touch_owner()`, a variant set template is re-applied, RSS title on a pretty-URL form renames the submitted forms' addresses.

## What stays on the edit screen and why?
- Multi-line code or rich text a cell cannot hold: login region headers/footers, region content, dynamic region code, ad content and caption, a field's information text. A field's type changes together with its choices and size settings, so it is read-only in the list.

## Why is the menu items list plain?
- `view_menu_items.php` is the menu's tree; its order is the menu's order (changed by Sort Items). It is a plain `table table-hover` without sorting or search, the name links to the item.

## How does the menu items list fit a phone?
- Below md, Created and Last Modified are hidden (`d-none md:d-table-cell` on both header and row cells), the Link cell is `text-break pg-cell-wrap`, and the card body is `.table-responsive`, so a deep tree scrolls inside the card, never the page. Before, the document was 500 to 1,800 px wide at 375 px and the header and toolbar scrolled sideways (#8994, PR #9071). Pinned by `tests/menu_tree_test.php`.

Related: docs/memory/ui/list-table-printed.md, docs/memory/ui/list-table-quick-edit.md, docs/memory/ui/list-table-driver-lists.md
