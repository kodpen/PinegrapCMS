---
name: dashboard-v2-storage
description: The new dashboard's dashboard_layout table (step 11.0.143), the sign-in upsert, the layout and look JSON, the panel actions, the card function contract, the storage alternatives rejected
---

## Where is everything kept?
- One table `dashboard_layout` (owner #4669; step `11.0.143`, `upgrade_2026_11_0_dashboard_layout()` with `install_create_table()`): `id`, `user_id`, `role` TINYINT NOT NULL DEFAULT 0, `enabled` (the switch), `layout` MEDIUMTEXT (JSON), `look` VARCHAR(255) (JSON), `locked`, `signed_in_at`, `signed_in_before`, `updated_at`, `updated_by`. Unique `idx_user_role` (`user_id`, `role`).
- A person's row: `user_id` > 0, `role` 0. A role default row: `user_id` 0, `role` 0-3, or 9 for everyone. `role` is 0, never NULL: a NULL inside a unique key enforces nothing, so one person could get two rows.
- Every sign-in upserts the person's row (`pg_session_sign_in_stamp()`, `includes/authentication.php`): `signed_in_before = signed_in_at`, then the new time, one write, so the since-sign-in chips work the first day the switch is on. Plain mysqli (`get_file.php` signs in without `functions.php`); a failure is ignored (the previous release has no table).

## What is in the layout and look?
- Layout: `{"v":1,"preset","cards":[{"id","w":"S|M|L","per":0-2,"cmp":0-2,"badge":0|1}],"top":{since,actions,tiles,status,brief},"actions","tiles","goals","pins","notes"}`, at most 64 KB (`PG_DASHBOARD_V2_MAX_LAYOUT`; a longer save is refused). Look: `{"style","backdrop","dense","calm"}`; a value outside the Appearance choices is left out, so the site's value stands.
- `pg_dashboard_v2_layout_clean()` drops unknown ids, repeats and keys; bad values take the default. Cards the person may not see stay in the stored list at their old index and are skipped when drawn, so a module switched off and on keeps its cards' places (the lesson of `dashboard.order_widgets`). No layout: the role default, else the first preset (`dashboard-v2-edit-mode.md`).

## Which panel actions? (`includes/panel/dashboard.php`)
- `dashboard_switch` {on} and `dashboard_layout_save` {layout, look}: exempt from the general gate, token, the caller's own row; the save is refused while the role is locked.
- `dashboard_cards` {cards: [{id, per, cmp}]}: up to 40 cards in one answer (`run.php`), exempt, read only.
- `dashboard_role_default` {role, locked, layout, look} and `dashboard_role_default_remove`: general gate, roles 0-1 (`dashboard-v2-share.md`). `dashboard_personal_save`: goals, pins, notes (`dashboard-v2-personal-cards.md`).

## What does a card function return?
- `cards/<id>.php` defines `pg_dashboard_v2_card_<id>($user, $period, $compare)`: `array('status' => 'success'|'denied'|'error', 'data' => array(...))` with figures, rows (text, note, tone, url), points, labels; never HTML, texts already through `lang()`. It checks its own gate, never echoes or exits. `pg_dashboard_v2_card_run()` answers `denied` for a closed gate and `error` for a missing file, a throw or a bad shape, so one card never breaks the batch.
- Where a widget computes the same figures, its query is a helper both call, and the widget's output stays byte for byte what it was (its tests unchanged).
- Tile and goal figures: `pg_dashboard_v2_figure($key, $user)`; chips: `pg_dashboard_v2_chip_count($key, $user, $since)` (`figures.php`), each with its gate.

## What was rejected?
- Browser storage only: the layout would not follow the person to another device; role defaults and sign-in times impossible.
- Columns on `user`: one per setting, still a schema change.
