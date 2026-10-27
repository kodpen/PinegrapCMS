---
name: db-q-converted-panel-menus-calendars
description: Region, menu, form list view and calendar panel screens converted to db_q(): PR, optional SQL pieces with their values, what stayed raw, guard test.
---

## Which region, menu, form list view and calendar screens are converted?
- `save_region_content.php` 10, `edit_ad_region.php` 9, `edit_menu.php` 8, `edit_menu_item.php` 12, `edit_form_list_view.php` 11, `edit_calendar.php` 10, `add_calendar_event.php` 5, `update_calendar_event_exception.php` 7 calls with values (PR #2327), `(string)` where the SQL quoted, also where a value was written unescaped into quotes (row ids, `$user['id']`, `$custom_form_page_id`, `$count`: now escaped); `(int)` for the bare `$sort_order` of the menu item position lookup.
- Optional SQL pieces carry their values beside them, merged in column order: the ad region name a designer may change (`$name_values`), the new calendar event's publish columns (`$publish_values`). `$sql_seo_analysis_current` of `save_region_content.php` holds no value and stays inline.
- The new calendar event's notes were escaped into `$notes` before the INSERT; `$notes` now holds the raw text and is bound, so it is escaped once (as in `edit_calendar_event.php`, `db-q-converted-panel-content.md`).
- `edit_menu_item.php` names `$query` in one error message: it now shows the SQL with its `?` placeholders, not the values.

## What stayed raw?
- The calls without values: `view_regions.php` has none with values and is unchanged; the calendar and location lists of `add_calendar_event.php`; `SELECT VERSION()` of `edit_form_list_view.php`.
- `tests/db_q_regions_menus_calendars_test.php` guards them: no escaped value in a `db_q()` SQL, no value in a raw query, one value per `?` in each literal value list, and the bound text of the optional ad region name, the bare menu position and the notes.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/data/db-q-converted-panel-content.md
