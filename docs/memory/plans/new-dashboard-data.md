---
name: new-dashboard-data
description: New dashboard plan: where the switch, layouts, look, role defaults and sign-in times are kept (question 1), the layout JSON, the API actions, the card data contract and gates
---

## Where are they kept? (question 1, decided)
- Decided (#4669): one table `dashboard_layout`, made by step `11.0.143` of the open version with `install_create_table()` (InnoDB, utf8mb4_unicode_ci): `id`; `user_id` (0 on a role default row); `role` TINYINT UNSIGNED NOT NULL DEFAULT 0 (a role default row: 0-3, or 9 for everyone; 0 on a person's row, told apart by its `user_id`: a NULL inside a unique key would not enforce uniqueness); `enabled` TINYINT (the switch); `layout` MEDIUMTEXT (JSON); `look` VARCHAR(255) (JSON); `locked` TINYINT (role rows); `signed_in_at`, `signed_in_before` INT (person rows); `updated_at`, `updated_by`. Unique (`user_id`, `role`).
- Every sign-in upserts the person's row moving `signed_in_at` into `signed_in_before` (one write per sign-in), so the chips work the first day the switch is on.
- Weighed against: browser storage only (no schema, but the layout would not follow the person to another device, and role defaults and sign-in times are impossible); columns on `user` (one per setting, still a schema change).

## What is in the layout?
- `{"v":1,"preset":"corporate","cards":[{"id":"ai","w":"M","per":0,"cmp":0,"badge":1}],"top":{"since":1,"actions":1,"tiles":1,"status":1},"actions":["edit_page"],"tiles":["visits","forms","seo","tasks"],"goals":[],"pins":[],"notes":""}`, at most 64 KB. Look: `{"style":"aurora","backdrop":"auto","dense":0,"calm":0}`.
- Read: unknown ids and keys are dropped, bad values take the default. Cards the person may not see stay in the stored list and are skipped when drawn, so switching a module off and on again keeps their places (the lesson of `dashboard.order_widgets` in `welcome.php`).
- No row, or no layout: the role default (N13), else the first preset (`new-dashboard-presets.md`).

## Which API actions?
- In `api.php`, exempt from its role gate like `get_widget_data`, each checking the person itself (`ui/dashboard-widget-files.md`), behind the panel token:
- `dashboard_switch` {on}: the person's `enabled`.
- `dashboard_layout_save` {layout, look}: every id against the card list, widths S/M/L, periods and compares 0-2, shortcut and tile keys against their pools, text lengths; refused while the person's role is locked (never for roles 0-1, `ui/dashboard-v2-share.md`).
- `dashboard_cards` {cards: [{id, per, cmp}]}: the data of the listed cards in one answer; a card the person may not see answers `denied` and draws nothing; a slow card (Google, flows) is asked for on its own beside the batch.
- `dashboard_role_default` {role, locked, layout, look}: role < 2; writes the role row from the layout and look on the person's screen (`ui/dashboard-v2-share.md`).

## What does a card function return?
- `includes/dashboard/v2/cards/<id>.php` defines `pg_dashboard_v2_card_<id>($user, $period, $compare)`: `array('status' => 'success'|'denied'|'error', 'data' => array(...))`, figures, rows (text, note, tone, url), points and labels; never HTML, texts already through `lang()`. It checks its own gate and never echoes or exits.
- Where a widget computes the same figures, its query moves to a helper both call (in `includes/dashboard/widgets.php` or the widget's file); the widget's output stays byte for byte what it was, its tests unchanged.
- A tile, chip or goal figure is a function in `includes/dashboard/v2/figures.php`: `pg_dashboard_v2_figure($key, $user, $since)` returning the number, its note and tone, or null when not allowed.
