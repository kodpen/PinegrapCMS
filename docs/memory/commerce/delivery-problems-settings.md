---
name: delivery-problems-settings
description: The Settings drawer of Orders › Delivery problems (DT5, #10969): each problem kind on or off, the two day counts under Advanced, who may save, what it writes
module: delivery_problems
---

## Where is it and who opens it?
- Orders › Delivery problems, a quiet "Settings" at the toolbar's right end (the `view` slot), opening `dialog#delivery_problems_settings` through pgUI (a bottom sheet under 576 px). Drawn by `pg_delivery_problem_settings_drawer()` in `includes/fn/delivery_problems.php`.
- Button and save only for Manager and up (`user_role` < 3): the store right alone opens the desk but cannot change what the pass looks for. Shown once the problem table exists.

## How is it laid out?
- Head "Delivery problem settings" and one line. Body: one `.pg-card-inner` with a row per kind (Stuck, Not handed over, Delivery problem, Returned), each its name, one help line and a `.pg-switch` described by it.
- "Advanced" is a folded `details.pg-start-advanced` (Design examples › Starting-point sheet) holding the two day counts as number fields: working days before a label counts as not handed over (1 to 10), days a parcel may take beyond the usual before it is stuck (0 to 30).
- Foot: Cancel quiet, Save fill; no danger button. The list's filters come back after a save.

## What does a save write?
- `pg_delivery_problem_settings_input()` (pure): a switch left out of the post is off; the day counts go through `pg_delivery_tower_settings_clean()` (clamped, not a number keeps the default). `pg_delivery_problems_settings_save()` writes the JSON to `config_text` `delivery_tower_settings`; a Site Log line "The delivery problem settings were saved.".
- The next hourly pass reads it (`delivery-tower-passes.md`). A kind switched off is no longer opened; problems already open stay until a status or staff close them.

## How is it tested?
- `tests/delivery_problems_test.php`: the input, the drawer's counts (four switches each with its help line, two number fields inside the fold and none outside, one fill, one quiet, no danger) and the screen's pins (quiet button, Manager and up).

Related: docs/memory/commerce/delivery-problems-desk.md, docs/memory/commerce/delivery-tower-rules.md
