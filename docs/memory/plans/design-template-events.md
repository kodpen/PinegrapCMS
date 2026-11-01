---
name: design-template-events
description: Plan for the Events and Conferences template (pinegrap-events): events as form records, registration on a form, tickets as shop products; parts in a folder; steps 1-5
---

## What is being built?
- A template for an events and conference organiser: home, the annual conference's programme by day and track, speakers, venue and travel, about with sponsors, coming events, registration followed from the account, tickets, contact and inbox, news (the standard blog pages), account pages, staff area, help-center search, the standard e-mails ([[design-templates-standard-pages]]). Id `pinegrap-events`, 1.0.0, Bootstrap 5.3.8, every word through `lang()`.

## How does it work without new product behaviour?
- Events are records of an Events form the organiser adds in the panel (`event_new`), the Café's way (`workflow/decisions-made-product-16.md` (b)): samples dated by `$event_on()`, `form_list_view` lists from today on, `event_view` the record's page; no fixed date.
- Registration is a Registrations form record, as the Salon's booking: the event filled by `_cf.url_param` from the event's Register button, pass, attendee, needs, consent; auto-registration; `registration_view` (`submitter_only`) with messages on the Comments widget; My Registrations; office-use status (Registered, Confirmed, Waitlisted, Cancelled, Attended); staff list `staff_registrations`.
- Paid passes are products of a Tickets catalog group (SKUs in `$passes`); the `ticket_passes` listing and the product, cart, checkout and receipt pages `requires ecommerce`. The tickets page is always there (what each pass includes, the registration form for free events or invoice), so no link is dead without the shop.
- The conference (tracks, speakers, programme, venue, sponsors) is template data, no year or date. Not built: session collections, seat limits, QR check-in.

## Why parts in a folder?
- `pg_design_templates()` offers every `includes/design_templates/*.php`; steps 1-4 write `includes/design_templates/pinegrap-events/` (not globbed), step 5 adds `pinegrap-events.php`, the moment it is offered. `tests/design_template_events_test.php` loads the parts the same way.
- Threads use the Comments widget from the start; home testimonials are sample text beside `google_reviews` (decisions (a), (c)).

## Which steps are there?

- 1 Data and public pages: `parts.php`, `pages_public.php` (home, programme, speakers, venue, about; `google_reviews`), the test. **Done (#4595).**

- 2 Events and registration: `events.php`, `mail.php`; parts.php gained the comment thread, staff bar and page, `$page_link`. **Done (#4612).**

- 3a Tickets: `tickets.php` (catalog group and pass products, `shippable => false`, tickets page, `ticket_passes`, `cart_link`, product, cart, checkout, order, `email_order`; returns `catalog` for step 5). **Done (#4633).**

- 3b Standard: `standard.php`; parts.php gained the newsletter and post parts; test `design_template_events_standard_test.php`. **Done (#4645).**

- 4 Account and staff: `account.php`; test `design_template_events_account_test.php`. **Done (#4686).**

- 5 Assembly: `pinegrap-events.php` (header, order, icon, description, highlights, look, palette, contact group, folders, catalog, `pe-` User Styles, search map), the shared tests' lists, `designer/design-template-events.md`, plan deleted; PR body names `[NEW] Added the Events and Conferences design template`. In progress.

## Where to start?
- `pinegrap-salon/` (same layout), `pinegrap-cafe.php` (events form, `$event_on`, lists, threads, Google Reviews), [[design-templates-file-format]], [[design-template-threads]]; `pinegrap-bookshop.php` shop pages for 3a.
