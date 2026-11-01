---
name: design-template-bookshop-events
description: Plan (owner, 2026-11-01): the bookshop template's hard-coded events become form records the business adds in the panel, the Café way, past events dropping out of lists
---

## What did the owner decide?
- 2026-11-01, item (b) of `workflow/decisions-made-product-16.md`: the bookshop's events become form records, as the Café's events are.

## What is hard-coded now?
- `pinegrap-bookshop.php`: the `$events` array (~:194-214, fixed dates 2026-10-15 to 2026-11-19), the home band (`array_slice($events, 0, 3)`, ~:993), the events page (`key 'events'`, ~:1221-1260), its search map entry (`'events'`, ~:3217). Once those dates pass, the sample reads stale on a new site.

## How does the Café do it?
- `pinegrap-cafe.php`: a custom form page for events with `'sample_records' => $sample_events` (`pg_cf_seed_sample_records()`, [[design-templates-standard-pages]]); dates from `$event_on($weekday, $weeks)` (~:2492), the next such weekday after today plus weeks ([[design-template-cafe]]); `form_list_view` widgets (~:3117-3159) list them, filtered to dates from today and sorted by date, so past events drop out.

## Steps

- 1. The bookshop's events as form records: an events form (staff page, panel), sample records from `$event_on()`, the home band and the events page as `form_list_view` widgets with a today-or-later date filter, the search map pointing at the list; version bump; test pinning the form, the filter and that no fixed date is left. Open.

## Open questions?
- None; layout and wording follow the current events page and band.

## Where to start?
- Read `designer/design-template-cafe.md`, `designer/design-templates-standard-pages.md`, `designer/collection-lists.md`, and the Café's event form, `$event_on`, and list widgets. PR title `[plan:design-template-bookshop-events] 1 — Bookshop template: events as form records`.
