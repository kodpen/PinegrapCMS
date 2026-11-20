---
name: launch-journey
description: Plan (owner 2026-11-20, poll 204): a set-up path by the site's purpose, steps marked from the real settings, a go-live check, the first week, a team member's path, the agency summary, API; LJ1a to LJ7
priority: 5
---

## What is asked?
- Owner (2026-11-20, agent channel poll 204, message 11083): "Evet, plana girsin": the purpose choice, steps marked by themselves, one click to the right screen, the go-live check, the first week, a new team member's path, the agency's summary, API and MCP.
- Recorded in `workflow/decisions-made-product-93.md`.

## What is there today?
- Every set-up screen exists; nothing says where to start, what is missing or whether the site is ready to go live. No dashboard card for a new site; only "No orders yet." empty states.
- The sources the steps read (`launch-journey-features.md` names each): the system status checks (`get_system_status_checks()`, cached 10 minutes, keyed by English titles, no ids), mail domain health, legal settings, robots, payment modes, Connections, internal broken links, backups, ops alerts.
- Nothing stores the site's purpose: the install wizard's template is not kept beyond a log line.
- Per-user first-visit marks exist for tours only (`user.tours_seen`, `pg_tour_seen()`, `pg_tour_mark()`, `ui/guided-tour.md`). No invitation: `add_user.php` with "notify" sends a set-password link.

## What binds it?
- No second source: a step's state is read from the function that already decides it, at view time; never ticked by hand. "Not for this site" hides a step and never marks it done; a blocker cannot be hidden.
- A check is read by its function, never by a system status check's English title.
- The set-up card, the purpose and the go-live screen are for administrators (role 0). A step links only to a screen its reader may open (`ui/dashboard-module-switches.md`).
- The journey ends by itself when every step is done or hidden, or with "Hide set-up"; Site Settings › General brings it back.
- Going live with a blocker asks a confirmation naming each one; the choice is kept in the Site Log.
- The agency's summary page shows only what the agency wrote for it and the steps' states, never a setting's value; its link is signed (the per-feature HMAC, hash stored) and can be withdrawn.

## What is asked of the architect?
- The schema: the site's purpose, the go-live time and the hidden steps (config columns proposed), the agency summary (a table: items, answers, link hash, expiry).
- Whether `user.tours_seen` may carry first-visit hint marks (proposed: `hint:<screen>` keys) or a column of their own.
- What "Go live" writes: turning off maintenance mode and the robots catch-all `Disallow: /` (`pg_robots_catch_all_closed()`), or only links to them; whether the template install sets the purpose (a `purpose` key in the template's metadata).
- The parts the Design examples screen lacks (`launch-journey-screens.md`).

## What is built?
- Features: `launch-journey-features.md` (L1 to L8). Steps: `launch-journey-steps.md`. Screens: `launch-journey-screens.md`. Claim as `[plan:launch-journey] <step> — <topic>`.

## What is left out?
- A domain or DNS ownership check, an external link checker, a real invitation flow (accept, expire), uploads by the customer on the summary page, AI-written set-up advice: each a later yes.
