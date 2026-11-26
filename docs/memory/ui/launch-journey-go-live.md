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
- Since #11807 both changes are one UPDATE guarded by the values read (`pg_launch_journey_golive_query()`): both land or neither, and a second press changes no row and writes no Site Log line (`pg_launch_journey_golive_log()`). `live_at` and the summary link are written through `pg_launch_journey_row_change()`, which claims the row and retries, so two writers do not drop each other's keys.

## How are broken internal links checked? (#11379)
- Go-live check key `broken_links` (catalogue `card` false: never a card step, never hidden). Fact from `pg_launch_journey_facts()`: the `seo_issue` rows of code `link_broken_internal` (every entity type), or `never` when no page has `seo_struct_checked_at` (the SEO structure check never ran); no crawl of its own.
- Always a warning, last under "Worth a look" (after the Search Console and backup ones); never a blocker, so "Go live" stays possible. Some rows: "Internal links point nowhere on N pages", "Fix" to `view_pages.php?filter=seo_broken_links`. Never run: "The SEO check has never run on this site" with "Run the SEO check" (row `action`) to `audit_run.php?pass=structure` (Analyze HTML Structure, back to that filter), never "0". None: no row. Fact not read: unknown, no row.
