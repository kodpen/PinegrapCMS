---
name: volunteer-shifts-features
description: Plan volunteer-shifts, features G1 to G16: programmes, profiles from the form, documents, shifts and roles, sign-up, waitlist, swaps, the unfilled call, check-in, hours, certificate, report
---

## What is a programme?
- (G1) A volunteer programme: name, its coordinators (panel users), the application form it reads, its roles (each with the skills, trainings and documents it needs), the cancel cut-off (24 hours by default), a minimum age when the form asks a birth date.

## Who is a volunteer?
- (G2) A profile made from an application on the form: the contact, skills from a choice field, the weekly days and hours free (the bookings' hours shape), trainings done (a course of the site completed), state Applied, Active, Paused or Left.
- (G3) A coordinator approves an application with one press; the welcome e-mail goes from the programme's template. A volunteer pauses or leaves themselves.
- (G4) Documents with a valid-until date (a criminal record certificate, a first-aid card): kind, number, valid until, "seen by" (who, when) and the file (poll 208); never a health report. 30 and 7 days before the date the volunteer and the coordinator are told; a role needing an expired document takes no sign-up.

## How are shifts made?
- (G5) A shift: place, start and end, the roles with how many each needs, a note. A weekly series and "Copy last week".
- (G6) The shift board: a week by place, each shift's roles filled of needed, amber when short, red when the start is near and short.

## How does a volunteer sign up?
- (G7) "My volunteering" in My Account, and a signed link for a volunteer with no site account: the shifts their roles, skills and documents fit, "Sign up", "Cancel" until the cut-off; a full role offers the waitlist.
- (G8) A freed place goes to the first on the waitlist by itself, who is told.
- (G9) "Offer my place": another volunteer who fits takes it; the coordinator approves, or it passes by itself when the programme says so.

## How are short shifts filled?
- (G10) Days before a short shift (a setting, 3 by default): a call to volunteers who fit, are free that day and are not booked then; one call a volunteer a shift; e-mail, SMS or WhatsApp by the volunteer's choice, a service message the volunteer turns off on their page ("Shift calls"). A reminder the day before to everyone signed up.

## How is the day recorded?
- (G11) A QR poster per place: the volunteer scans it on their phone to check in and out; the coordinator's list of the day does the same for someone with no phone. Not checked in 30 minutes after the start is "Did not come".
- (G12) Hours from the check-in and out (else the shift's length), approved by a coordinator; hours worked outside a shift entered by the volunteer and approved.
- (G13) Show-up rate of the last 10 shifts, seen by coordinators only; never used to keep anyone out by itself.

## What does a volunteer get?
- (G14) An hours certificate for a period they choose, with a check link (the course certificate's way); thank-you e-mails at 50, 100 and 250 hours.

## What else?
- (G15) The report: shifts filled of needed by week, hours by programme, role and volunteer, did-not-come and late cancels, active and left volunteers; Excel (`pg_xlsx_write_book()`).
- (G16) Events `volunteer.applied`, `volunteer.approved`, `shift.signed_up`, `shift.unfilled`, `volunteer.checked_in`, `volunteer.hours_approved` in the webhook and flow catalogues; `GET /volunteer/programmes`, `GET /volunteer/shifts`, `GET /volunteer/hours`; the MCP tools.

Related: docs/memory/plans/volunteer-shifts.md, docs/memory/plans/volunteer-shifts-steps.md
