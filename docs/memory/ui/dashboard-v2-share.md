---
name: dashboard-v2-share
description: New dashboard role defaults and lock (#4975): the Share with the team section, dashboard_role_default, which layout and look a person sees, why the lock never binds administrators and designers
---

## What does Share with the team do?
- The last section of the edit-mode side panel (`drawShare()` in `pg_dash_panel.js`), drawn only when `config.edit.share` is set: `pg_dashboard_v2_share_config()` gives it to roles 0 and 1 (`pg_dashboard_v2_can_share()`), roles as `[value, label]` pairs (Everyone 9, Administrator 0, Designer 1, Manager 2, User 3).
- "Make default" posts `dashboard_role_default` {role, locked, layout, look} at once, apart from Save: the layout is what is on screen (`pgDash.edit.layout()`), the look the one shown. The answer's message (`pg_dashboard_v2_share_done()`) shows in a `.pg-dash-saved` chip until the role or lock changes.

## What does the server check and write?
- `pg_panel_dashboard_role_default()` (`includes/panel/dashboard.php`): keeps api.php's general gate (role <= 1) and checks `can_share` itself, token, a valid role; the layout goes through `pg_dashboard_v2_layout_clean()` with the sharer's cards and pools, the look through `pg_dashboard_v2_look_clean()`. Written by `pg_dashboard_v2_role_default_write()` to the row `user_id` 0, `role` n (9 for everyone).

## Which layout does a person see?
- `pg_dashboard_v2_row_for($user)` = `pg_dashboard_v2_row_effective(own, default)` (pure, `tests/dashboard_v2_share_test.php`): the default is the role's row or the everyone row, a locked one first (`pg_dashboard_v2_role_default()`). Unlocked: it fills an empty own layout and an empty own look. Locked: its layout and look replace the person's, whose own stay stored for when the lock lifts. The switch and sign-in times are always the person's own.
- Used by `page.php` (top, look, grid) and the greeting. Each person still sees only the cards they may see (`pg_dashboard_v2_grid_layout()` filters).

## Why does the lock not bind administrators and designers?
- They set and lift it from their own edit mode. A lock on their own role or on everyone would otherwise hide their edit button and refuse their save, leaving nobody able to lift it from the panel. `pg_dashboard_v2_locked_for($user)` (no edit button, `config.edit.locked`, save refused) is false for them, and `row_for` treats their default as unlocked.

## What was checked?
- The unit test above. The panel script ran in headless Chromium on a static page with a stubbed `$.ajax`: options in order, the request body, the chip. Not on a running panel or database.
