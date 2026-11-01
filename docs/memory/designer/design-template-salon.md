---
name: design-template-salon
description: Beauty Salon template (pinegrap-salon): its parts folder, the booking on the Clinic's request pattern, what is not built, the psl- look, tests.
---

## What does it ship?
- 39 pages: home, services (prices, durations, packages), team, lookbook, about; booking (`appointment`, `appointment_view`, `my_appointments`, `privacy`); contact, inbox, conversation, newsletter, blog, blog post, help-center search, error; sign-in and My Account pages; `staff_directory`; `staff`, `blog_new` (four sample posts), `staff_entry` in `private`; 8 e-mail pages.
- Shared: account_bar, notice, header, footer, staff_bar, booking_band, email_header/footer. Order 55, icon `bi-flower2`, look `elegant`, palette `burgundy`, no gallery drawing of its own (the generic one). Contact group `newsletter` (Salon Newsletter). Built in #4470, #4508, #4524, #4570 and #4583; 1.1.0 (#4871) moved the threads to the Comments widget and added Google Reviews beside the testimonials.

## Why is it a main file plus a folder?
- `pg_design_templates()` offers every `includes/design_templates/*.php`; the subfolder is not read as a template. `pinegrap-salon.php` includes `pinegrap-salon/parts.php` (builders, the salon's data, shared parts), `mail.php`, then `pages_public.php`, `booking.php`, `standard.php`, `account.php` in one scope; each page file returns its pages (and widgets) and uses the variables the files before it set. A change to a page goes in its part; the main file holds only the order, the search map, the look and the template keys.

## How does booking work?
- As in the Clinic template: a booking is a record of the Appointments custom form (service, stylist with "No preference", date with `rules.min_date` today, time of day, notes, consent linking `privacy`). Auto-registration gives a visitor an account, so the booking's page and My Appointments show it at once.
- Status is an office-use-only field the staff change in the panel (Requested, Confirmed, Completed, Cancelled); messages are the booking page's comments, drawn by its Comments widget (`appointment_messages`) and mailed to the client and the salon. The staff area lists the bookings from today on (`staff_appointments`).
- Every comment thread (booking, conversation, blog post) and the reply e-mail's card are Comments widgets since 1.1.0 (#4871): [[design-template-threads]].

## What is deliberately not built?
- Live slot holding (an hour taken off the form once booked) and stylist calendars: both are new product behaviour, likely schema; the request-and-confirm flow needs neither. Calendar event reservations need the shop and one event per slot, so they are not used.

## Why psl- and not ps-?
- Bootstrap owns `ps-*` (padding-start utilities, `ps-3` is used in the parts). The template's own classes are `psl-brand`, `psl-eyebrow`, `psl-hero`, `psl-lift`, with their rules and two fonts (Cormorant Garamond, Jost) in `assets`.

## Which tests cover it?
- `tests/design_template_salon_test.php` loads the parts and the assembled file: data, page folders, every `{{page:}}`/`{{tab:}}` names a page, every placed widget defined once, search anchors, the booking form, the staff area, `psl-` classes equal their rules, and the threads, e-mail card and Google Reviews through `tests/helpers/template_threads.php`. It is also in the shared lists of the collection-form, account-e-mail, submitter-name, form-rules and consent-privacy tests.
