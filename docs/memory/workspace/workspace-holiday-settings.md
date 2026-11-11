---
name: workspace-holiday-settings
description: The Working calendar card in Site Settings (Team and working hours) and on Workspace Settings: one request reader and one save, button and field names, drawers, old anchors
---

## Where is the Working calendar card?
- PR #8741 (SW2b of `plans/settings-workspace-move.md`): in Team and working hours, section `pgset-ws-calendar`, cards in `includes/settings/workspace_team.php`. The old card (`#ws-calendar`, `ws_holidays_settings_card()`) keeps working on `workspace_settings.php` until SW7, as SW2a left Departments and Team members ([[workspace-team-settings]]).
- `pg_settings_legacy_anchors()` maps `ws-calendar` to the new card. A calendar's days on the old screen (`?feed=N#ws-feed-N`) are not mapped: that anchor stays on the old screen until SW7 redirects it.

## How do the two screens share the save?
- `ws_holidays_settings_post($viewer, $action, $liveform, $input, $after_save = false)` (`includes/workspace/workdays.php`) does every action: holiday_add, holiday_remove, feed_add, feed_sync, feed_remove, feed_day. It reads no `$_POST`; `$input` comes from `ws_holidays_settings_input($action, $source)`, which keeps the action's fields as scalars.
- The old screen passes `$_POST` (fields `name`, `from`, `to`, `department_id`, `yearly`, `url`, `public_only`, `event_id`, `feed_id`, `skip`). The dialog reads its pressed button with `ws_holidays_settings_request($_POST)`; both give the same input (tests pin it).
- With `$after_save` (the dialog) a refusal is a warning: the rest of the pane is stored by then, as for a department's archive.

## Which names does the dialog post?
- Named submit buttons of the settings form, value = the record id: `ws_holiday_remove`, `ws_feed_sync`, `ws_feed_remove`, `ws_feed_skip` (leave a day out), `ws_feed_count` (count it again), and `ws_holiday_add` / `ws_feed_add` = 1. `ws_holidays_settings_button()` names them for the markup and `ws_holidays_settings_request()` reads them back.
- Add fields: `ws_hol[name|from|to|department_id|yearly]` and `ws_feed[url|name|department_id|public_only]` (`ws_holidays_settings_field()`); `lists` entries `ws_hol` 1, `ws_feed` 1.

## What opens in a drawer?
- Add a holiday (`pgset-ws-holiday-new`), Add a calendar address (`pgset-ws-feed-new`), and a calendar's days (`pgset-ws-feed-<id>`, the row's Days (N) button). Fields and buttons carry `form=` ([[settings-drawer-card-form]]); no `required`: `ws_holidays_add_fields_html()` marks them required only on the old screen (`$form` null).

## Where are the checks?
- `ws_holiday_input()` (name, first and last day; pure, refused field named) and `ws_holiday_feed_address()` (https or webcal, at most 2,000 characters) are what `ws_holiday_add()` and `ws_holiday_feed_add()` ask. `ws_holidays_settings_refused()` asks them before the dialog writes anything; a refusal is marked on the drawer's field and nothing is stored.
- The card's parts are drawn by both screens: `ws_holidays_rows_html()`, `ws_holidays_next_html()`, `ws_holiday_feed_row_html()`, `ws_holiday_feed_days_html()`, `ws_holidays_add_fields_html()`; a row action's button comes from the caller's callback (a form of its own on the old screen, a named button in the dialog).
- The card lists the site-wide calendar addresses only (`ws_holiday_feeds()`, `user_id` 0); a person's own stay on Calendar subscription (`workspace-personal-ical.md`).

Tests: `tests/settings_workspace_calendar_test.php`.
