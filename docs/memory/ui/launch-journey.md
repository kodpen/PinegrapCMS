---
name: launch-journey
description: The site set-up journey as a whole: "Set up your site" card by purpose, go-live check, First week, Start here, hint lines, agency summary, GET /launch; its decisions and which file holds each part
module: launch_journey
---

## What is it?
- A new site's path on the new dashboard: "What is this site for?" picks and orders the steps, each step's state is read from the setting that decides it, "Do it" opens its screen, "Ready to go live?" runs the go-live check, then a First week card. A new team member gets Start here and hint lines; the agency gets a signed summary page for its customer; agents read it all through `GET /launch`.
- Built 2026.11.0: SJ1a #11160, SJ1b #11200, SJ2 #11292, SJ3 #11312, SJ3b #11379 (the go-live check's broken internal links warning), SJ4 #11344, SJ5 #11314, SJ6 #11337.

## What binds it?
- Owner (2026-11-20): a set-up path by purpose, steps marked by themselves, one click, the go-live check, first week, team path, agency summary, API and MCP (poll 204, `workflow/decisions-made-product-93.md`).
- Owner (2026-11-20): the summary's lines are one table (poll 206, `workflow/decisions-made-schema-46.md`); "Go live" turns maintenance mode off and removes the robots catch-all itself, never a payment mode (poll 209, `workflow/decisions-made-product-94.md`).
- A step's state comes only from the function that already decides it, at view time, never ticked by hand and never from a System Status check's English title. "Not for this site" hides a step, never marks it done; a blocker cannot be hidden.
- The set-up card, the purpose and the go-live check are for administrators (role 0); a step links only to a screen its reader may open.
- Architect (2026-11-20): the purpose, go-live time, hidden steps and the summary link are one JSON `config_text` row, no config column (`data/config-table-no-varchar.md`); hint lines are `hint:<screen>` keys in `user.tours_seen`, only for catalogue screens; a design template's key maps to a purpose, no template file touched.
- The customer's page shows only the agency's lines and the steps' names, never a setting's value.

## Which file holds which part?
- Purposes, steps, rules, blocker or warning, payment live per driver, the first week: `launch-journey-catalogue.md`.
- "Set up your site" card, stored row, facts reader, purpose dialog, Site Settings › General field: `launch-journey-setup-card.md`.
- "Ready to go live?", the Go-live check dialog and its broken internal links warning, "Go live", Site Log lines, `live_at`: `launch-journey-go-live.md`.
- First week, Start here, hint lines: `launch-journey-first-week-path.md`.
- "Agency summary" drawer and `launch_summary.php`: `launch-summary-agency.md`; its table: `launch-summary-lines.md`.
- `GET /launch`, MCP `launch_read`, the guide's start topic: `api/api-launch-journey.md`.

## What is not built?
- Left out on purpose, each a later yes: a domain or DNS ownership check, an external link checker, a real invitation flow (accept, expire), uploads by the customer on the summary page, AI-written set-up advice.

## How is a step added?
- A key in `pg_launch_journey_catalogue()` and its place in `pg_launch_journey_steps()`, its rule in `pg_launch_journey_rule()` with a test, its fact filled in `pg_launch_journey_facts()` from the deciding function (left out when unreadable), its face in `pg_launch_journey_step_face()` and line in `pg_launch_journey_step_line()`. Card, go-live check and API follow without change.
