---
name: course-progress-features
description: Plan course-progress, features Z1 to Z17: a course and its ordered lessons, when a lesson is open, marking done and quiz lessons, the learner's blocks, the certificate, the staff's learners list
---

## What is a course?
- (Z1) A course is a row: its name, the private folder a product already opens, its classroom page and its ordered lessons, each a page of that folder. Selling does not change: the product, its price and the order's grant stay as they are.
- (Z2) A lesson keeps its place in the order, "opens N days after enrolment" (0: at once) and, optionally, a quiz form with the score it needs.
- (Z3) A course may say "Lessons in order": a lesson opens when the one before it is done.
- (Z4) A switch "Courses" in Site Settings, Features, off. On, a screen "Courses" lists the courses with their learners and completions; its editor picks the folder, adds the folder's pages as lessons and orders them.

## When is a lesson open?
- (Z5) The enrolment day is the day of the member's first counting order that opens the course's folder; for a member given the folder by hand, the day they first opened a lesson, kept as a row. A cancelled order does not count.
- (Z6) A lesson is open when its days have passed since the enrolment day, counted at read with `pg_calendar_days_later()`, and, with "Lessons in order", the one before it is done. A lesson already done stays open. Whoever may edit the page always passes.
- (Z7) One call in `get_page.php` after the access switch, only for a page of a private folder that is a lesson: a closed lesson sends the member to the classroom page with a notice that names the day it opens or the lesson to finish first. A lesson in a public folder is a free preview and is never closed.

## How is a lesson done?
- (Z8) A block "Mark as done" on a lesson page: a signed-in member's press goes to `course_action.php` with a token, a same-origin check and a rate limit; the server checks the folder access and that the lesson is open, and writes the row (member, lesson) once. Done stays done.
- (Z9) A quiz lesson is done only when the member has a completed submission of its form with a score at or above the lesson's needed score; a press without one names the score needed.
- (Z10) With the last lesson done the course is complete: the day is kept once and `course.completed` is announced for flows and webhooks.

## What does the learner see?
- (Z11) Three blocks filled by `assets/js/pg_course.js` from one signed-in endpoint, so the stored page is the same for everyone: "Course progress" (a bar and "4 of 12"), "Course lessons" (each lesson done, open, "opens on" its day or locked, and "Continue"), "My courses" (only the courses whose folder the member has, each with its progress).
- (Z12) Without a signed-in member the blocks show the lessons' names only, or nothing for "My courses".

## What is the certificate?
- (Z13) A course with "Certificate" on gives a member who completed it the page `certificate.php`: an HTML page with print styles (name, course, day, the site's name and logo, a code), printed or saved as a PDF by the browser.
- (Z14) The code names the completion and carries a signature made with the site's secret, so nothing more is stored; a public page checks a code typed or scanned from the certificate's QR (`pg_qr_svg()`) and shows whose it is, the course and the day. Checks are rate limited.

## What does staff see?
- (Z15) A course's learners: every member with the folder, the enrolment day, lessons done, the last activity and the completion day; a search, a CSV file, and "Reset progress" with a confirmation.
- (Z16) Progress rows are in the personal data export and erasure, and go with a deleted user.
- (Z17) A course whose folder is not private, or a lesson whose page left the folder, is named on the Courses screen and gates nothing.
