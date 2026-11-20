---
name: course-progress
description: Start file of courses: ordered and timed lessons on a private folder's pages, progress, quiz lessons, the learner's blocks, the certificate, the Courses screen; decisions, PRs, files, left out
module: courses
---

## What are courses?
- A course is a row on a private folder a product already opens: its classroom page and ordered lessons, each a page of that folder, opening N days after enrolment and, with "Lessons in order", after the one before is done. Selling does not change. Off on every site: Site Settings, Features, "Courses".
- Built by CO1 #10063 (tables, open-lesson rule, writers, code), CO2 #10280 (the Courses screen), CO3 #10135 (the gate), CO4 #10454 (blocks, `pg_course.js`, `course_action.php`), CO5 #10487 (certificate, `course.completed`); bug hunt #10358 (quiz scores, escaped name, a deleted member's rows).

## What binds it?
- Architect (2026-11-16, agent channel message 6553): three tables (course, lessons, progress); a form submission cannot be the done mark and a block's setting cannot open or close a lesson. The owner said yes to the tables (poll 91).
- Owner (2026-11-05, #7204): no table of grants per order, so the enrolment day is read from the orders each time.
- Architect (2026-11-17, message 9031): managers and above open the Courses screen (`workflow/decisions-made-product-74.md`). The owner accepted the screen's look (poll 141); the blocks' and certificate's pictures were dropped (message 9546), the unit tests pin them.

## Which file holds what?
- Tables, the open-lesson rule, the enrolment day, rows written once, the code, personal data: `commerce/course-tables.md` (`includes/fn/courses.php`).
- The gate in `get_page.php` and the classroom notice: `commerce/course-gate.md`. The Courses screen, problems, learners: `commerce/course-screen.md` (`includes/fn/courses_admin.php`).
- `course_action.php` and the press's refusals: `commerce/course-blocks.md`; the palette blocks: `designer-blocks-course.md`. `certificate.php`, the public check, `course.completed`: `commerce/course-certificate.md`.

## Which feature is built where? (checked 2026-11-20)
- Z1 course row on a private folder, classroom, lessons: #10063. Z2 order, days, quiz and score: #10063, #10358. Z3 "Lessons in order": #10063. Z4 the switch, menu row, list and editor: #10280.
- Z5 enrolment day from the first counting order, else the first-open row written once: #10063, #10135. Z6 days by `pg_calendar_days_later()`, done stays open, an editor passes: #10063. Z7 one call after the access switch, classroom notice, public lesson never closed: #10135.
- Z8 "Mark as done" with token, same origin, rate, access and open checks, one row: #10454. Z9 quiz lesson needs a completed submission at the score, the press names it: #10454. Z10 completion kept once and `course.completed` for webhooks and flows: #10063, #10487.
- Z11 three blocks filled from one door, stored page the same for all: #10454. Z12 names only for a visitor, nothing for "My courses": #10454. Z13 printable `certificate.php` with name, course, day, logo, code: #10487. Z14 signed code, nothing stored, QR, rate-limited public check: #10063, #10487.
- Z15 learners with search, CSV and "Reset progress" with a confirmation: #10280. Z16 export, erasure and a deleted account's rows: #10063, #10358. Z17 a folder not private or a lesson outside the folder named and gating nothing: #10280, #10135.
- Open items: none; every Z number is in the code.

## What is left out?
- Closing a lesson's files and the site search's results; time watched of a video and SCORM; a PDF made on the server; a lesson opening on a fixed date (the page's publish time does it); an API resource; the Online Courses template placing the blocks (templates are paused). The help section `pages#courses` is not on pinegrap.com yet. Each needs a new yes.
