---
name: volunteer-shifts-screens
description: Plan volunteer-shifts, how its panel screens are put together (VS2, VS3, VS6): programmes, a programme in tabs with Set-up as folded rows, applications, volunteers, the shift board, hours
---

## Which screens, and by which rules?
- VS2: Contacts › Volunteers (programmes), a programme's Set-up, Applications and Volunteers tabs. VS3: Shifts. VS6: Hours. VS7: Report. The volunteer's pages and the scan page: `volunteer-shifts-screens-own.md`.
- `ui/screen-composition-rules.md`; parts are copied from the Design examples screen, its section named in brackets (`workflow/screen-described-in-plan.md`). The parts it lacks were answered by the architect (2026-11-20, agent channel message 11146).
- A screen step builds a whole part (a screen with its drawer), never structure first and content later. Its worker renders it at 1440 and 390 px before ready and writes "Screen check" in the PR.

## How are programmes laid out? (VS2)
- A list on the list table driver: name, active volunteers, shifts this week filled of needed, a pill [Segmented control and status]: Open, Paused. One fill "Add programme" [Buttons]: a small drawer asks the name and the form.
- A programme's head: name, pill. Tabs [Segmented control and status]: "Shifts" (VS3, the first tab once built), "Applications", "Volunteers", "Hours" (VS6), "Report" (VS7), "Set-up".
- Set-up as folded rows, one open at a time [Settings pane], one line of state each:
  1. "Application form": the form, and which field gives skills, birth date and free days [Fields].
  2. "Roles": a role a row [Rows and gathered rows]: name, skills, trainings, documents needed; "Add a role", each with its remove. Under the rows one hint line: "Do not ask for a health report."
  3. "Sign-up rules": cut-off hours, minimum age, swaps need approval.
  4. "Calls and reminders": days before a short shift, channels.
  5. "Messages": Welcome, Call, Reminder, Thank you as tabs, each its subject and text.
  6. "Coordinators": people as rows [Icon tiles and people], "Add", each with its remove.
- Foot: Save as the fill, Cancel quiet; "Not set" pill on a row missing values.

## How are applications and volunteers laid out? (VS2)
- Applications: list table rows: name, applied, skills, a pill for documents (Valid, Missing, Running out); "Approve" quiet per row and in bulk; the row opens the profile drawer.
- Volunteers: rows: name, state pill (Active, Paused, Left), roles, hours this year, show-up rate. The profile drawer: details, documents with their dates, shifts, hours; "Pause" quiet, "Mark as left" danger with a confirm.

## How is the shift board laid out? (VS3)
- Not a grid (architect, message 11146): the week's days as gathered groups [Rows and gathered rows], a shift a row with its place, time and one pill a role, "3 of 5": teal full, amber short, rose short and near. The place is a segmented filter [Segmented control and status].
- Week arrows and "Copy last week" quiet; "Add shift" the fill: a drawer with place, date, start and end, roles and counts, "Repeat weekly".
- A shift's drawer: signed up per role, the waitlist, "Call volunteers" quiet (says how many will be asked), Remove (danger, says who is told).

## How are hours laid out? (VS6)
- Rows waiting for approval [Rows and gathered rows]: volunteer, shift or "Outside a shift", minutes, "Approve" quiet; bulk Approve the fill.

## What must be true?
- Set-up has six folded rows, one open at a time; the programmes list and Hours have one fill each.
- The board draws one group a day and one pill a role, in three colours only.
- No list or export shows a document's file or number to anyone but the programme's coordinators.

Related: docs/memory/plans/volunteer-shifts.md, docs/memory/plans/volunteer-shifts-steps.md, docs/memory/plans/volunteer-shifts-screens-own.md
