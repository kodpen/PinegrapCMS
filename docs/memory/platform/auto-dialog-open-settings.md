---
name: auto-dialog-open-settings
description: When, to whom and where an auto dialog opens: open_on (delay, scroll, leave), open_scroll, audience (all, cart), place (centre, top, bottom, corner); defaults, readers, triggers, the cart rule
module: auto_dialogs
---

## Which columns are there?
- `auto_dialogs.open_on` VARCHAR(10) 'delay', `open_scroll` TINYINT UNSIGNED 50, `audience` VARCHAR(10) 'all', `place` VARCHAR(10) 'centre'; all NOT NULL, after `page`, no index, no backfill. Migration 11.0.221 (`upgrade_2026_11_0_auto_dialog_open()`), PR #9851, confirmed by the architect in agent channel message 5632.
- VARCHAR, not ENUM: the session `sql_mode` is not strict, so an ENUM would store an unknown value as ''; a new value needs no MODIFY.

## How is a row read?
- `pg_auto_dialog_open_settings($row)` (`includes/fn/auto_dialogs.php`): a missing or unknown value reads as the default (delay, 50, all, centre), `open_scroll` outside 1 to 100 as 50. A row stored before the columns, by hand or by a newer version opens as before (the architect's condition, 5632).
- `pg_auto_dialog_open_choices()` lists the values, the default first. The page and the API read `SELECT *`, so a site whose files landed before the upgrade still works.

## Who writes them?
- `pg_auto_dialog_save($id, $values, $user_id, $open)`: `$open` is normalised; null, or a site without the columns (`pg_auto_dialog_open_ready()`), leaves them as stored.
- The screens: an Opening card (Opens, Scrolled %, Shown To, Place) in today's form recipe; `pg_auto_dialog_form_open_problems()` refuses a value not offered or a share outside 1 to 100; `pg_auto_dialog_form_open_values()` reads a blank as the default.
- The API: `api/api-design-dialogs-regions.md`.

## How does the site open it?
- `frontend.src.js` (and the hand-patched `frontend.min.js`) `init_auto_dialogs()`: delay as before; scroll waits for `software.when_scrolled(share)` (scroll position over the page's scrollable room; a page too short to scroll opens after 1 s); leave waits for `software.when_leaving()` (the pointer leaving through the window's top edge). On a touch screen (`software_device_type` mobile or `(hover: none)`) leave falls back to the delay. A preview opens at once.
- `software.show_auto_dialog()` opens it and writes the cookie. Place centre is `showModal()`; top, bottom and corner pass `place` and `modal: false` to `open_dialog()` (`show()`), classes `pg-dialog-top`, `-bottom` (full width) and `-corner` (bottom right, 16px).

## Who sees a cart row?
- `get_page_content.php` asks `pg_auto_dialog_session_has_cart()` (the session's order holds a line not saved for later) only while a printed row has audience cart (`pg_auto_dialogs_need_cart()`); `pg_auto_dialogs_on_page($rows, $page, $has_cart)` leaves a cart row out otherwise. A page that prints one calls `pg_page_cache_personal('popup_cart')`: no kept copy.

## Tested?
- `tests/auto_dialog_form_test.php` (defaults, the cart rule, the form's values and fields, the script's triggers in both scripts), `tests/api_design_dialogs_regions_test.php` (the API's values and refusals). The step was run twice on a MariaDB 10.11 table from the starter backup; no browser run.
