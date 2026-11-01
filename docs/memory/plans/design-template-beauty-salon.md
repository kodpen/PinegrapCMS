---
name: design-template-beauty-salon
description: Plan for the Beauty Salon design template (pinegrap-salon) with online booking on the Clinic template's request pattern: parts built in a folder, offered once assembled; steps 1-4
---

## What is being built?
- A design template for a beauty salon (hair, nails, skin, make-up): home, services with prices and durations, the team with their days, a lookbook, about, booking that the client follows from their account, contact and inbox, blog, account pages, a staff area, help-center search and the standard e-mails ([[design-templates-standard-pages]]).
- Id `pinegrap-salon`, version 1.0.0, Bootstrap 5.3.8 site markup, every word through `lang()`, Turkish through `php tools/tr_add.php`.

## How does booking work without new product behaviour?
- As in the Clinic template (`pinegrap-clinic.php`): an appointment request is a record of an Appointments custom form (service, stylist with "No preference", preferred date with `form_fields.rules` `min_date` today, time of day, notes, consent linking the privacy page). A visitor without an account gets one (auto-registration), so the request's own page and My Appointments show it at once.
- Status is an office-use-only field the staff change in the panel (Requested, Confirmed, Completed, Cancelled); the messages under a request are the page's comments, mailed to the client and to the salon. The staff area lists the coming requests.
- Not built: live slot holding (a booked hour taken off the form) or stylist calendars. Both would be new product behaviour (and likely schema); the request-and-confirm flow needs none. Calendar event reservations need the shop and one event per slot, so they are not used for appointments.

## Why are the parts built in a folder first?
- `pg_design_templates()` offers every `includes/design_templates/*.php` at once; a template of ~3,500 lines does not fit one ~1,000-line step. Steps 1-3 write the parts into `includes/design_templates/pinegrap-salon/` (not globbed), each part included in the scope the main file gives it (the builders' variables). Step 4 adds `pinegrap-salon.php`, which includes the parts and is the moment the template is offered.
- Tests load the parts the same way (`tests/design_template_salon_test.php`).

## Which steps are there?

- 1 **done (#4470)** Parts and the public pages: `pinegrap-salon/parts.php` (node builders, the salon's services by category with durations and prices, the team, the shared account bar, header, footer and booking band, page shells, section helpers, form-field builders) and `pinegrap-salon/pages_public.php` (home, services and prices, team, lookbook, about). Test: the parts load, every page has a key, a root tree and its builders' `{{page:…}}` links name pages of the plan.

- 2 Booking: `pinegrap-salon/pages_booking.php`: the appointment form page (Appointments form, rules, notify and confirm pages, auto-registration), the request's page with its messages, My Appointments, the staff list of coming requests, the privacy page, the booking e-mail pages. Test: field names, the `min_date` rule, consent links `{{page:privacy}}`, e-mail page keys. After 1.

- 3 Standard pages and widgets: `pinegrap-salon/pages_standard.php` and `pinegrap-salon/widgets.php`: contact and inbox, newsletter, blog and post, account pages, staff area and staff bar, search with `site_search`, error, the account e-mails; the login region, newsletter band and form widgets. After 2.

- 4 Assembly and look: `pinegrap-salon.php` (header, name, order, icon, description, highlights, look and palette, contact group, folders, `assets` User Styles with own `ps-` rules, search sections map), a gallery drawing if one is added, the template in the shared template tests' lists, memory `designer/design-template-salon.md`; PR body names `[NEW] Added the Beauty Salon design template with online booking`. After 3.

## Where to start?
- Read `includes/design_templates/pinegrap-clinic.php` (the closest template) and [[design-templates-file-format]]; copy the builder shapes, not the clinic's words.
