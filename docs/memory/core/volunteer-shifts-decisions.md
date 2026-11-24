---
name: volunteer-shifts-decisions
description: Volunteer shifts decisions: owner polls 202, 206, 207, 208, the architect's tables, place and screen parts, the words on screen, what binds the code, what was left out
---

## Who decided volunteer shifts, and what?
- Owner (2026-11-20): yes to the whole feature, poll 202 (`workflow/decisions-made-product-93.md`).
- Owner (2026-11-21): tables `volunteer_programmes`, `volunteers`, `volunteer_documents`, `volunteer_shifts`, `volunteer_signups`, poll 206 (`workflow/decisions-made-schema-46.md`); columns and erasure in `decisions-made-schema-47.md`.
- Owner (2026-11-21): the unfilled-shift call is a service message, no marketing consent or İYS, no promotion, poll 207; a document's file is kept, never a health report (KVKK article 6), poll 208 (`workflow/decisions-made-product-94.md`).
- Architect (2026-11-20): hours sit on the sign-up row, hours outside a shift are a sign-up row with no shift; roles are JSON on the programme and the shift, a sign-up names the role's key (agent channel message 11145).
- Architect (2026-11-20): the place is Contacts › Volunteers; a coordinator is a programme's member and sees its programmes only; the board is day groups of rows, not a grid; the scan page reuses `event_check_in.php`'s button and camera box (message 11146).

## What binds the code?
- A volunteer needs no panel account: My Account or a signed link (hash stored) opens their own shifts, hours and documents only.
- A role never goes over its count: every sign-up move is one conditional write under `GET_LOCK('pg_volunteer_shift_<id>')` (`volunteer-signup.md`).
- A call goes once a volunteer a shift, between 09:00 and 20:00, stops when the shift fills; with "Shift calls" off no call goes, the reminder still does (`volunteer-messages.md`).
- A document is seen by the programme's coordinators, roles 0-2 and the volunteer, its file only through `get_file.php`, never in an export or the File Manager, erased with the volunteer (`volunteer-document-file.md`).
- The show-up rate is shown to coordinators, never used to keep anyone out by itself.
- Hours count only once approved; the certificate and the report state approved hours only.
- The five privacy registrations and `pg_contact_holders()` (`volunteer-store.md`).

## Which words are on screen?
- "Programme", "Shift", "Role", "Sign up", "Waitlist", "Did not come", "Hours"; never "slot", "token" or a column's name.
- The programmes list and Hours have one fill each; Set-up has six folded rows, one open at a time, a row missing values carries "Not set" (`volunteer-programmes.md`).

## What was left out?
- Paid shift work and payroll, background checks by a provider, group sign-ups (a company team), mileage claims, AI shift filling: each needs a new yes from the owner.

Related: docs/memory/core/volunteer-shifts.md, docs/memory/core/volunteer-overview.md
