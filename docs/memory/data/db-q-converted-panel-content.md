---
name: db-q-converted-panel-content
description: Folder, page copy, calendar event, image editor and theme designer screens converted to db_q(): PR, optional SQL pieces with their values, what stayed raw, guard test.
---

## Which panel content screens are converted, and what stayed raw?
- `edit_folder.php` 21, `duplicate_page_f.php` 22, `edit_calendar_event.php` 25, `image_editor_edit.php` 26, `theme_designer.php` 20 calls with values (PR #2302), `(string)` where the SQL quoted, also where a value was written unescaped into quotes (row ids, `$user['id']`, the ad region name of the theme designer: now escaped).
- Optional SQL pieces carry their values beside them, merged in column order: the folder style columns (`$style_values`), the calendar event publish columns (`$publish_values`), the theme designer preview page lookup (`$where_values`, empty for the home page), the copied page's tree and AI columns (`$page_values`, a value only when its `?` is in the text).
- Inline stay PHP ints with no value of their own: the copied page's `noindex` / `nofollow`, the form field's `$translate_value` and the comment's `$sql_parent_value`.
- The calendar event notes were escaped into `$notes` before the UPDATE; `$notes` now holds the raw text and is bound, so it is escaped once.
- Raw stay the calls without values. The `db_value()` / `db_items()` / `db()` calls with `e()` are untouched; so are the `escape()` arguments of `check_calendar_event_location_availability()`.
- `tests/db_q_content_screens_test.php` guards them: no escaped value in a `db_q()` SQL, no value in a raw query, one value per `?` in each literal value list, and the bound text of the form name copy, the folder update and a missing file size.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/data/db-q-placeholder-helper.md
