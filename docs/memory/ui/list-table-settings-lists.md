---
name: list-table-settings-lists
description: Countries, states, zones, tax zones, currencies, referral sources, contact groups, calendars, event locations and auto dialogs on the list table driver (TD11): printed whole in browser mode
---

## Which screens and how are they drawn?
- `view_countries.php`, `view_states.php`, `view_zones.php`, `view_tax_zones.php`, `view_currencies.php`, `view_referral_sources.php`, `view_contact_groups.php`, `view_calendars.php`, `view_calendar_event_locations.php`, `view_auto_dialogs.php` (markup in `includes/templates/view_auto_dialogs.php`): printed whole in browser mode (`pg_list_table_printed()`, `ui/list-table-printed.md`), each in `pg_list_table_printed_lists()` under its `pg_list_state()` name. Column keys are the old sort codes, so the toolbar sort menu and a heading click agree; a toolbar search box sits beside the sort menu.
- The first column opens the record (`data-pg-open`); the Edit buttons are gone. A calendar's name opens its events (`calendars.php?calendar_id=`), as its Expand button did.
- Flags (zip code required, default, base currency, subscription, enabled) are `pg_list_table_check_cell()`; money (zone rates) sorts by minor units, a tax or exchange rate by its number, days and sizes by their integer (`data-pg-sort-value`).

## What stays as it was?
- The server still orders the rows (countries and states by the label in the panel language, currencies by `pg_currency_list_sort()`); the browser then sorts by the kept levels, comparing the text on screen in Turkish collation.
- Contact groups and calendars print only the records the user may use. Membership and Members have no sort code in `$sort_columns`, so their headings do not sort.
- An auto dialog's name keeps `status_enabled`/`status_disabled`; its Preview link is an actions column at the row's end.
- A creator or modifier the database no longer knows shows only the time (the event locations list printed "[Unknown]" before).

Tests: `tests/settings_lists_td11_test.php`.

Related: docs/memory/ui/list-table-printed.md, docs/memory/ui/list-table-shipping-lists.md
