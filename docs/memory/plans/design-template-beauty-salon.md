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

- 1 **done (#4470)** `pinegrap-salon/parts.php` (builders, the salon's data, shared parts) and `pages_public.php` (home, services, team, lookbook, about).

- 2 **done (#4508)** Booking: `pinegrap-salon/booking.php` (pages and widgets: the form, the booking's page with its messages, My Appointments, the staff list `staff_appointments` placed on step 3's staff page, privacy, booking e-mails, `email_reply`) and `mail.php` (e-mail builders, header and footer trees).

- 3a **done (#4524)** `pinegrap-salon/standard.php`: contact and inbox, newsletter, blog and post, help-center search, error, the message and newsletter e-mails, their widgets (`newsletter_band`, `home_posts` included).

- 3b **done (#4570)** Account and staff: `pinegrap-salon/account.php` (pages and widgets): sign-in pages, My Account and its pages, the staff area (`staff` placing `staff_appointments`, `staff_directory`, `blog_new` with sample posts, `staff_entry`), account e-mails; widgets `login_region`, `my_account`, the starters. After 3a.

- 4 Assembly and look: `pinegrap-salon.php` (header, name, order, icon, description, highlights, look and palette, contact group, folders, `assets` User Styles with own `ps-` rules, search sections map), a gallery drawing if one is added, the template in the shared template tests' lists, memory `designer/design-template-salon.md`; PR body names `[NEW] Added the Beauty Salon design template with online booking`. After 3b.

## Where to start?
- Read `includes/design_templates/pinegrap-clinic.php` (the closest template) and [[design-templates-file-format]]; copy the builder shapes, not the clinic's words.
