---
name: volunteer-shifts-screens
description: Plan volunteer-shifts, how its screens are put together (VS2 to VS7): programmes, a programme in tabs with Set-up as folded rows, the shift board, volunteers, hours, the volunteer's own pages
---

## Which screens, and by which rules?
- VS2: Contacts › Volunteers (programmes), a programme's Set-up, Applications and Volunteers tabs. VS3: Shifts. VS4: the volunteer's pages. VS6: the scan page and Hours. VS7: Report.
- `ui/screen-composition-rules.md`; parts are copied from the Design examples screen, its section named in brackets (`workflow/screen-described-in-plan.md`); a part it lacks is asked of the architect, never made.

## How are programmes laid out? (VS2)
- A list on the list table driver: name, active volunteers, shifts this week filled of needed, a pill [Segmented control and status]: Open, Paused. One fill "Add programme" [Buttons]: a small drawer asks the name and the form.
- A programme's head: name, pill. Tabs [Segmented control and status]: "Shifts" (VS3, the first tab once built), "Applications", "Volunteers", "Hours" (VS6), "Report" (VS7), "Set-up".
- Set-up as folded rows, one open at a time (the settings pane of `plans/design-examples-patterns.md` DX1; before it lands, [Cards]), one line of state each:
  1. "Application form": the form, and which field gives skills, birth date and free days [Fields].
  2. "Roles": a role a row [Rows and gathered rows]: name, skills, trainings, documents needed; "Add a role", each with its remove.
  3. "Sign-up rules": cut-off hours, minimum age, swaps need approval.
  4. "Calls and reminders": days before a short shift, channels.
  5. "Messages": Welcome, Call, Reminder, Thank you as tabs, each its subject and text.
  6. "Coordinators": people as rows [Icon tiles and people], "Add", each with its remove.
- Foot: Save as the fill, Cancel quiet; "Not set" pill on a row missing values.

## How are applications and volunteers laid out? (VS2)
- Applications: list table rows: name, applied, skills, a pill for documents (Valid, Missing, Running out); "Approve" quiet per row and in bulk; the row opens the profile drawer.
- Volunteers: rows: name, state pill (Active, Paused, Left), roles, hours this year, show-up rate. The profile drawer: details, documents with their dates, shifts, hours; "Pause" quiet, "Mark as left" danger with a confirm.

## How is the shift board laid out? (VS3)
- A week by place: days as columns, a shift a card [Cards] with its time, roles as "2 of 3", a pill Full, Short, or Short and near. Week arrows and "Copy last week" quiet; "Add shift" the fill: a drawer with place, date, start and end, roles and counts, "Repeat weekly".
- A shift's drawer: signed up per role, the waitlist, "Call volunteers" quiet (says how many will be asked), Remove (danger, says who is told).
- The week grid is not on the Design examples screen: asked of the architect.

## What does a volunteer see? (VS4, VS6, VS7)
- In the site's own look: "My volunteering": my next shifts with "Cancel" (or "Offer my place" past the cut-off), "Shifts for you" with "Sign up" or "Join the waitlist", my hours and "Get my certificate", my documents with their dates and "Upload a new one".
- The scan page on a phone: the shift's name and one large button, "Check in" or "Check out", then the time; asked of the architect as a part.

## How are hours laid out? (VS6)
- Rows waiting for approval [Rows and gathered rows]: volunteer, shift or "Outside a shift", minutes, "Approve" quiet, bulk Approve the fill.

## Which words?
- "Programme", "Shift", "Role", "Sign up", "Waitlist", "Did not come", "Hours"; never "slot", "token" or a column's name.

Related: docs/memory/plans/volunteer-shifts.md, docs/memory/plans/volunteer-shifts-steps.md
