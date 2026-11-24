---
name: volunteer-report
description: A volunteer programme's Report tab (VS7): periods, places filled of needed and late cancels by week, approved hours by role and volunteer, active and left volunteers, the Excel workbook
module: volunteer_report
---

## Where is it?
- The "Report" tab of `edit_volunteer_programme.php` (`view=report`), after Hours, for everyone who sees the programme (roles 0-2, its coordinators). `&export=xlsx` downloads the workbook. Module `includes/fn/volunteer_report.php`, pure parts tested in `tests/volunteer_report_test.php`. Built in step VS7 of plan `volunteer-shifts`.

## Which period?
- Segments "Last 4 / 12 / 52 weeks": whole weeks Monday to Sunday ending with this week (`pg_volunteer_report_period()`).

## What is counted? (`pg_volunteer_report_build()`)
- A shift counts in the week it starts: its roles' counts as places needed; signed places up to each role's count as filled; "Did not come" (`outcome` `no_show`).
- Late cancel: a `cancelled` sign-up whose `cancelled_at` (read on the site's clock) is past the programme's cut-off before the start, or after it (`pg_volunteer_late_cancel()`). Leaving the waitlist writes no `cancelled_at`, so it is no late cancel (#11345, `core/volunteer-signup.md`).
- Approved minutes only, in the week of their day, by role ('' is "Outside a shift") and by volunteer (most hours first). Active volunteers now and those whose `left_at` falls in the period.
- Hours by programme: each programme's own Report total; there is no all-programmes report.

## What does the screen and the workbook show?
- Screen: figures (Places filled, Approved hours, Did not come with late cancels, Active volunteers with left), then report frames By week, Hours by role, By volunteer (`pg-report-*` classes, as `queue_report.php`).
- Excel (`pg_xlsx_write_book()`): sheets Summary, Weeks, Roles, Volunteers; numbers as numbers, names always text. Never a document, its number or file.

Related: docs/memory/core/volunteer-hours.md, docs/memory/core/volunteer-certificate.md, docs/memory/plans/volunteer-shifts-screens.md
