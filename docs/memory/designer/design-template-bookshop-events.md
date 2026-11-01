---
name: design-template-bookshop-events
description: Bookshop template (1.3.0) events as records of a collection form, the Café way: New Event page, sample events dated from today, home and events lists from today on, no fixed dates
---

## Where do the bookshop's events come from?
- The records of the Event Calendar form (collection), on the staff page New Event (`event_new`, also in the staff bar and the staff area). Fields: title, event_date (date), event_time (free text "19:00"), kind (Reading night, Signing, Book club, Workshop), place, summary. Decision: owner, 2026-11-01, item (b) (`workflow/decisions-made-product-16.md`).
- Before 1.3.0 the template drew five events from a PHP array with fixed dates (2026-10-15 to 2026-11-19), stale on any site installed after them.

## How are the first events dated?
- `'sample_records' => $sample_events` (`pg_cf_seed_sample_records()`, [[design-templates-standard-pages]]); `$event_on($weekday, $weeks)` as the Café's ([[design-template-cafe]]): readings and the book club on Thursdays, the signing and the workshop on Saturdays, all within three weeks of the day the template is opened.

## Which lists show them?
- `home_events` (home band, three cards) and `events_list` (events page, a list-group, 20 a page): `form_list_view` on `{{tab:event_new}}`, filter `event_date` is greater than or equal to the current date, ordered by date ascending, so a past event drops out. Empty: "No events planned right now..." with the bookshop letter.
- The date leaf binds `event_date` as `d` over `m.Y`: date month and weekday names print in English ([[widget-fill-filters]]), so the weekday the old rows showed is gone.
- Reserve a seat still opens the contact page. The events page's rows are no longer search sections (`event-<n>`); its "How to reserve" box still is.

## What about older sites?
- Nothing changes: rows are never reused; a 1.2.x site keeps the fixed events on its pages.

## How was it checked?
- `tests/design_template_bookshop_events_test.php`: the form and its fields, the sample records' fields, dates and weekdays, both lists' settings and bound fields, the installer preview, no `YYYY-MM-DD` in any tree; `design_template_collections_test` lists `event_new`. No running instance.
