---
name: volunteer-shifts
description: Plan (owner 2026-11-20, poll 202): volunteer programmes: profiles, dated documents, shifts with roles, sign-up and waitlist, the unfilled call, QR check-in, hours, certificate; VS1a to VS9
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 202, message 11057): "Evet, plana girsin": profile and documents, the shift board, sign-up and swaps, the unfilled-shift call, check-in on the spot, hours and the certificate, report, events, API.
- Recorded in `workflow/decisions-made-product-93.md`.

## What is there today?
- The charity template keeps a volunteer offer as a form record (office status, comments, `submitter_only` views; `designer/design-template-charity.md`). No volunteer or shift code outside the templates.
- `contacts` has fixed columns only: no skills, availability or custom fields; groups by `contacts_contact_groups_xref`, also by rules (`core/audiences-rules.md`). Forms map only to those columns (`pg_cf_contact_fields()`).
- No document with a valid-until date; the nearest are a contact's membership expiry (`membership_job.php`) and store-credit expiry mails.
- The course certificate: `certificate.php`, a printable HTML page with a QR (`pg_qr_svg_data_uri()`), checked by `?code=` signed with `ENCRYPTION_KEY`, nothing stored (`commerce/course-certificate.md`).
- My Account sections: a key and a flag in `_pg_my_account_extras()` (`includes/fn/widgets_account.php`), a list `account.*` and the designer twin (`commerce/event-tickets-page.md`).
- Scanning: `event_check_in.php`; hours in the bookings' shape (`pg_booking_hours_clean()`); SMS kinds in `pg_sms_kinds()`, WhatsApp's `kind` free.

## What binds it?
- A volunteer needs no panel account: My Account or a signed link (the per-feature HMAC, hash stored) that opens their own shifts only.
- A coordinator sees their programmes only, by membership; roles 0-2 see all.
- A role's places never go over its count: a sign-up is one conditional write under a lock per shift.
- A call goes to a volunteer once a shift, never at night, and stops when the shift fills.
- A document is seen by the programme's coordinators only, never in an export of the report, and is erased with the volunteer. A health report is special-category data (KVKK article 6): asked of the owner before it is offered.
- The show-up rate is shown, never used to keep anyone out by itself.
- Hours count only once approved; the certificate states approved hours only.
- The five privacy registrations (`pg_privacy_erase_tables()`, `pg_privacy_erase_labels()`, `pg_privacy_export_sections()`, `pg_privacy_inventory()`, `pg_privacy_rules_catalogue()`) and `pg_contact_holders()`.

## What is asked of the architect?
- The tables (proposed five: programmes, volunteers, documents, shifts, sign-ups with hours on the sign-up row); the place and gate (proposed: Contacts › Volunteers); the call's consent (a service message to a volunteer who applied, or commercial) and health documents, both for the owner; the parts the Design examples screen lacks (`volunteer-shifts-screens.md`).

## What is built?
- Features: `volunteer-shifts-features.md` (G1 to G16). Steps: `volunteer-shifts-steps.md`. Screens: `volunteer-shifts-screens.md`. Claim as `[plan:volunteer-shifts] <step> — <topic>`.

## What is left out?
- Paid shift work and payroll, background checks by a provider, group sign-ups (a company team), mileage claims, AI shift filling: each a later yes.
