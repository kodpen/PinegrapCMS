---
name: volunteer-overview
description: Start file of the volunteer cluster: what volunteer shifts are, which decisions bind them, which memory file holds each part, how a new part is added
---

## What is it?
- Volunteer programmes for a body: applications from a site form become profiles with skills, free days and dated documents; coordinators plan shifts with roles on a weekly board; volunteers sign up on My Account or a signed page, take waitlist places, offer theirs, are called for short shifts, check in by QR, and get approved hours, a certificate and thank-you e-mails; a report and Excel.
- Built by the volunteer-shifts plan in 2026.11.0 (#10922 to #11307); features G1 to G16 with their PRs: `volunteer-shifts.md`.

## Which decisions bind it?
- The owner's and the architect's decisions, the words on screen and what was left out: `volunteer-shifts-decisions.md`.

## Which file holds what?
- Pure rules (fit, waitlist, cut-off, call pick, show-up rate, token): `volunteer-shift-rules.md`. Tables, erasure, privacy: `volunteer-store.md`. A document's file: `volunteer-document-file.md`; its upload: `volunteer-document-upload.md`.
- Panel: programmes, Set-up, applications, profile `volunteer-programmes.md`; the Shifts board `volunteer-board.md`; Hours and check-in `volunteer-hours.md`; Report `volunteer-report.md`.
- The volunteer's side: `volunteer.php` and My Account `volunteer-signup.md`; the `account.volunteering` list `volunteer-account-list.md`; certificate and thank-you `volunteer-certificate.md`.
- The job's calls, reminders and notices: `volunteer-messages.md`. Events: `volunteer-events.md`. API and MCP: `api/api-volunteers.md`.

## How is a new part added?
- Functions in an `includes/fn/volunteer_<part>.php` module, behind `pg_volunteer_ready()`; a place moved under the shift's lock; an announcement after the lock is released; a new event goes through every catalogue in `volunteer-events.md`.

Related: docs/memory/core/volunteer-shifts.md, docs/memory/core/volunteer-shifts-decisions.md
