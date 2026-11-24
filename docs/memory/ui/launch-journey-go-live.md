---
name: launch-journey-go-live
description: The go-live check: "Ready to go live?", its dialog, "Go live" turning maintenance mode off and removing the robots catch-all, the confirmation, Site Log lines, the go-live time
module: launch_journey_site
---

## Where is it?
- The foot of the set-up card (`ui/launch-journey-setup-card.md`): "Ready to go live?" the fill, or "Live since …" once `live_at` is stored. Card data `golive` from `pg_launch_journey_golive_view()`; dialog `goliveDialog()` in `assets/js/pg_dash_cards_launch.js`. Tests: `tests/launch_journey_test.php`.

## What does the dialog show?
- "Go-live check": "Must be fixed" (maintenance first) and "Worth a look", lines gathered (`.pg-gather`), each with "Fix" to the step's screen; an empty group says "Nothing to fix". Foot: Cancel quiet, "Go live" the fill.
- The first press of "Go live" shows the confirmation when there is something to name: "Go live changes this itself:" ("Maintenance mode turns off", "Search engines may index the site", each only when it applies) and "These are not fixed yet:" with each blocker left and the checkbox "I know, go live anyway", which must be ticked. Nothing to name: it goes live at once.

## What does "Go live" do? (owner, poll 209, `workflow/decisions-made-product-94.md`)
- Panel action `launch_journey_save` with `go_live` (role 0), `pg_launch_journey_go_live()`: the check is read again on the server; a blocker left without `anyway` is refused.
- Turns `config.maintenance_mode` off and takes the catch-all "Disallow: /" out of `config.additional_robots_content` (`pg_launch_journey_robots_opened()`: other groups and rules stay; an emptied `*` group loses its User-agent lines). Never a payment mode, never the home page's noindex (that stays a blocker left).
- Site Log: one line per change ("Go live: …"), then "The site went live." or "The site went live with these left to fix: …" naming each blocker and its line. System Status cache is forgotten after a change.
- `live_at` is stored once: a later "Go live" keeps the first time, so the first week counts from it.
