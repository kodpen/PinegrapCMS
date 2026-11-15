---
name: addon-hook-system-steps
description: Steps of plans/addon-hook-system.md, started 2026-11-15: AH1 the census, AH2 the design note for the owner's look, the slices written after his answer
---

## Why did it start on 2026-11-15?
- Owner (2026-11-15, in the architect's window), with the workers idle: "mümkünse en önemlisi yeni bir özellik katmak olur yazılıma."
- On 2026-11-14 he set the start after the running plans. Those are closed out but for the last steps of `channel-profile-cards` and `poll-agent-questions`, so the architect started the part before the release (`addon-hook-system-site.md`, "In which part does each go?").

## Which steps are there?
- AH1 (S, no product code) Census, into `plans/addon-hook-system-census.md`, more files past 4 KB: every moment `includes/fn/events.php` announces and where it is fired; the driver interfaces an add-on registers through (payments, shipping, marketplace connectors, SMS, e-document, jobs) and how a shipped driver is found today; the core screens where a UI slot would sit; what file integrity, backup, System Status and the migration runner must learn.
- AH2 (architect) The design note from the census: the hook list, the manifest, the slots, the site's own add-on. It goes to the owner; from his answer the slices of `addon-hook-system.md` ("What remains?") become steps AH3 and on.

## What does every step keep?
- Nothing in the core is removed or moved to an add-on before 2026.11.0 ships, and no zip upload before its signature check (#3344).
- The `addons` table is asked of the owner when its step comes; no schema change before that.
- Claim as `[plan:addon-hook-system] <step> — <topic>`.
