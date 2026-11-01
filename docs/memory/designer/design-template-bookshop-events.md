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
- The events page's rows are no longer search sections (`event-<n>`); its "How to reserve" box still is.

## What does Reserve a seat open? (1.3.3)
- The contact page with `?topic=<Reserving a seat at an event>&event=<title>`: the row's `<a>` is a `__page_link` to `{{tab:contact}}`, the topic as fixed text ([[page-link-fixed-text]]), the event from `title`. The contact form's Topic pick list (`url_param` `topic`) and Message (`event`) read them ([[custom-form-url-prefill]]); the `$select` and `$textarea` helpers take a `_cf` like `$input`.
- Symptom fixed (#4808): every row linked the bare contact page, so the visitor chose the topic and typed the event.
- The date is not sent: the message takes one address value, and the inbox and e-mails print only topic and message.

## What does the Book Club page say about meetings? (1.3.2, #4809)
- No date: "We meet on Thursday evenings at 19:00; the next date is on the events page.", and the welcome e-mail's "Meetings" line says the same. Until 1.3.2 both named Thursday 29 October, a past date against the club's sample event. `test_bookshop_events_no_fixed_dates` also refuses a day with a month name.

## What about older sites?
- Nothing changes: rows are never reused; a 1.2.x site keeps the fixed events on its pages.

## How was it checked?
- `tests/design_template_bookshop_events_test.php`: the form and its fields, the sample records' fields, dates and weekdays, both lists' settings and bound fields, the installer preview, no `YYYY-MM-DD` in any tree; `design_template_collections_test` lists `event_new`. No running instance.
