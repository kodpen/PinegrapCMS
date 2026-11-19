---
name: course-progress
description: Plan (owner 2026-11-16, agent channel poll 82): courses on the folder and pages that exist: ordered and timed lessons, progress, quiz lessons, a certificate, "My courses"; steps CO1 to CO6
priority: 6
---

## What is asked?
- The owner's words and the conditions of the set: `plans/more-features.md`. In the study (agent channel message 6433): progress, lessons in order and opening in time, a quiz that must be passed, a certificate, a list of the member's own courses.

## What is there today, and what is reused?
- The Online Courses template: a course is a product that opens a private folder, its classroom and lessons are pages of that folder, the first lesson is a free preview in a public folder (`designer/design-template-courses.md`). It left progress, scored quizzes, certificates, drip release and a list of only the bought courses out as new behaviour: this plan is that behaviour, for any site.
- The order's grant and its take-back on cancel and refund: `pg_order_complete_line_folder_access()`, `pg_order_folder_access_cancelled()`. The access row has no start day and names no order.
- A page's access is judged by one switch in `get_page.php` (`check_private_access()`); a page can already open at a fixed time (`designer/page-schedule.md`).
- A quiz form writes its score to the submission, on a fail too (`forms.quiz_score` in `custom_form.php`).
- `pg_calendar_days_later()` counts calendar days; the QR maker is `includes/fn/qr.php`; `pg_privacy_export_pdf()` makes a PDF on the server with the embedded library.

## What binds it?
- The architect's conditions: `plans/website-second-set-conditions.md` for all eight plans, `plans/website-second-set-conditions-per-plan.md` for this one. They are not repeated here.
- Owner (2026-11-05, #7204, `workflow/decisions-made-product-34.md`): a table of grants per order was refused. So the enrolment day is read from the orders and is not kept per grant (Z5).

## What is built?
- The numbered features: `course-progress-features.md` (Z1 to Z17). Steps: `course-progress-steps.md`. Claim as `[plan:course-progress] <step> — <topic>`.
- In short: three tables (courses, their lessons, progress), one pure rule that says whether a lesson is open, one call in the page's access path, blocks filled after the page loads, and a certificate page.

## What did the architect decide? (2026-11-16)
- Architect (agent channel message 6553), after the owner asked that what exists be grown instead of a table (poll 84): the three tables stay. A form's submission cannot be the mark: `forms` has no index on the member, so that road needs schema too, makes every "done" a form record and every lesson a form page.
- A block's setting cannot open or close a lesson: the access gate would hang on content, and deleting the block would open the lesson. A menu's row cannot hold the days, the quiz and the score. Used from what exists: the product that opens a folder, the lesson pages, the quiz form and its score.
- Architect (message 6554): Z5 as planned; the first-open row is written once, only for a signed-in member with the folder.
- Architect (2026-11-17, message 9031): managers and above (role below 3) open the Courses screen. The tables' columns were settled in CO1's PR.

## What did the owner decide? (2026-11-16)
- The three tables: yes, with the set's schema (agent channel poll 91, `plans/more-features.md`). Nothing else was his: no course row, no change on a site.

## What is left out?
- Closing a lesson's files and the site search's results; time watched of a video and SCORM; a PDF made on the server; a lesson opening on a fixed date (the page's publish time does it); an API resource; the Online Courses template placing the blocks (templates are paused). Each is a later yes.

## Whose look?
- The owner's: the Courses screen and the learners (CO2), the three blocks (CO4), the certificate (CO5). The closed lesson's notice (CO3): the architect's. CO2: yes (poll 141, `workflow/decisions-made-product-74.md`).
