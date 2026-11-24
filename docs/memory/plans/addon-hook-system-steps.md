---
name: addon-hook-system-steps
description: Steps of plans/addon-hook-system.md, started 2026-11-15: AH1 the census, AH2 the design note, AH3 to AH8 the part before the release (one door, registry, hooks, slots)
---

## Why did it start on 2026-11-15?
- Owner (2026-11-15, in the architect's window), with the workers idle: "mümkünse en önemlisi yeni bir özellik katmak olur yazılıma."
- On 2026-11-14 he set the start after the running plans. Those are closed out but for the last steps of `channel-profile-cards` and `poll-agent-questions`, so the architect started the part before the release (`addon-hook-system-site.md`, "In which part does each go?").

## Which steps are there?
- AH1 (S, no product code) Done (#9653). Census, into `plans/addon-hook-system-census.md`, more files past 4 KB: every moment `includes/fn/events.php` announces and where it is fired; the driver interfaces an add-on registers through (payments, shipping, marketplace connectors, SMS, e-document, jobs) and how a shipped driver is found today; the core screens where a UI slot would sit; what file integrity, backup, System Status and the migration runner must learn.
- AH2 (architect) Done: the design note `plans/addon-hook-system-design.md`.
- AH3 (S) One door: the direct `api_webhook_enqueue()` and `erp_event()` calls go through `pg_announce()`; no behaviour change, the webhook tests prove it.
- AH4 (M) after the owner's yes to the table: `addons` by a migration step, the registry (folders found, manifest check, enable and disable, the fault guard), the "Add-ons" card in Site Settings › Features, System Status rows.
- AH5 (M) after AH3 and AH4: `pg_hook_on()` / `pg_hook()`, the four new moments, an add-on's events and its own migration file.
- AH6 (M) after AH4: the order screen's slots and `addon_action.php` (in `waf_sensitive_scripts()`).
- AH7 (M) after AH6: the orders list's and the printed order's slots, `settings.card`.
- AH8 (S) after AH5 and AH7: an example add-on under `tests/`, the developer text for the documentation pages, memory in `core/`; this part's files deleted, part two's plan written.

## What does every step keep?
- Nothing in the core is removed or moved to an add-on before 2026.11.0 ships, and no zip upload before its signature check (#3344).
- The `addons` table is asked of the owner when its step comes; no schema change before that.
- Claim as `[plan:addon-hook-system] <step> — <topic>`.
