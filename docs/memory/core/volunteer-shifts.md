---
name: volunteer-shifts
description: Volunteer shifts features G1 to G16 (Contacts › Volunteers, volunteer.php, check-in, hours, certificate, report, events, API): what each does, the file that holds it and the PR that built it
---

## What was built, and where? (plan volunteer-shifts, 2026.11.0)
- Start file: `volunteer-overview.md`. Decisions and what was left out: `volunteer-shifts-decisions.md`. Each line names the feature, its memory file and the PRs.

## What are programmes and volunteers? (G1 to G4)
- G1, a programme: name, coordinators, application form, roles (skills, trainings, documents), cut-off (24 hours by default), minimum age. #11136, `volunteer-programmes.md`.
- G2, a profile from an application on the form: skills, free days, trainings (a site course completed), state Applied, Active, Paused, Left. Store #10949 (`volunteer-store.md`), application #11136; "Upload a new one" #11279 (`volunteer-document-upload.md`).
- G3, Approve with the welcome e-mail; Pause and Mark as left by a coordinator (#11136, `volunteer-programmes.md`); the volunteer's own "Pause my volunteering" and "Leave the programme", coming sign-ups given up, one coordinator e-mail (#11342, `volunteer-self-move.md`).
- G4, documents with a valid-until date, the file behind `get_file.php` (#10949, `volunteer-document-file.md`); notices 30 and 7 days before (#11250, `volunteer-messages.md`); an expired document takes no sign-up (`pg_volunteer_fits()`, #10922, `volunteer-shift-rules.md`).

## How are shifts made and filled? (G5 to G10)
- G5, a shift with place, times, roles and counts, a note, "Repeat weekly", "Copy last week"; G6, the board by day with teal, amber and rose pills. #11163, `volunteer-board.md`.
- G7, "My volunteering" in My Account and the signed page `volunteer.php`: Sign up, Cancel to the cut-off, the waitlist. #11217 (`volunteer-signup.md`), the `account.volunteering` list #11251 (`volunteer-account-list.md`).
- G8, a freed place to the first who fits on the waitlist, told by e-mail; G9, "Offer my place" and "Take this place", approved or passed by the programme's switch. #11217, `volunteer-signup.md`.
- G10, the unfilled call (3 days by default) by e-mail, SMS or WhatsApp, once a volunteer a shift, "Shift calls" off stops it; the day-before reminder. #11250, `volunteer-messages.md`; "Call volunteers" on the board #11163.

## How is the day recorded? (G11 to G13)
- G11, a QR poster per place, `volunteer_check_in.php`, the list of the day, "Did not come" 30 minutes after the start; G12, hours approved by a coordinator, hours outside a shift sent by the volunteer. #11256, `volunteer-hours.md`.
- G13, the show-up rate of the last 10 shifts on the Volunteers tab only. Rule #10922, its column #11136.

## What else? (G14 to G16)
- G14, the hours certificate with its check code, thank-you e-mails at 50, 100 and 250 approved hours. #11285, `volunteer-certificate.md`.
- G15, the Report tab and its Excel workbook, no document in it. #11285, `volunteer-report.md`.
- G16, the six events through `pg_announce()` and their catalogues (`volunteer-events.md`); `GET /volunteer/programmes`, `/shifts`, `/hours` and the MCP tools (`api/api-volunteers.md`). #11307.

Related: docs/memory/core/volunteer-overview.md, docs/memory/core/volunteer-shifts-decisions.md
