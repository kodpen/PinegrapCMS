---
name: course-progress-steps
description: Plan course-progress, steps CO1 to CO6: the tables and the rule of an open lesson, the Courses screen, the gate in the page's access path, the blocks and their script, the certificate, close-out
---

## Which steps are there?
- CO1 (M) The tables and the rules, server and data only. After the owner's yes on tables.
  - A migration step adds the courses, lessons and progress tables, listed in `get_tables()`; the draft PR's body carries their columns and indexes for the architect.
  - Existing files: `functions.php` (the module), `includes/fn/privacy.php` (the export and erasure lists).
  - New: `includes/fn/courses.php` (ready check, a course and its lessons, the enrolment day, the pure rule of an open lesson, the done and complete writers, the certificate's code), `tests/courses_test.php`.
  - Tests, red first: the rule by days, by order, a done lesson staying open, an editor passing; the enrolment day from the first counting order and from the first open; a cancelled order not counting; a completion written once; a changed code refused.
  - Features: Z1, Z2, Z3, Z5, Z6, Z10 (the server's side), Z16.

- CO2 (M) after CO1: the Courses screen.
  - Existing files: `includes/fn/output.php` (the menu link), `includes/settings/features.php` and `features.save.php` (the switch).
  - New: the root screens `view_courses.php` and `edit_course.php` with the learners list.
  - Pictures: the list, the editor with its lessons, the learners, a course with a problem (Z17). To the owner; not marked ready before his look.
  - Features: Z4, Z15, Z17.

- CO3 (S) after CO1: the gate.
  - Existing files: `get_page.php` (one call after the access switch).
  - Tests: the gate's answer for a lesson closed by its days, closed by the order, a public lesson, an editor, a page that is no lesson, the switch off.
  - Pictures: the notice on the classroom page. To the architect.
  - Features: Z7.

- CO4 (M) after CO1: the blocks and their script.
  - Existing files: `get_page_content.php` (the script for a page whose markup carries `data-pg-course`), `assets/js/style_designer.src.js` (the palette rows and one row in `_SD_PAGE_SCRIPTS`; held by other open PRs: the step names the clash).
  - New: the root file `course_action.php` (the member's progress, the press), `assets/js/pg_course.js`, the block family's file with its test.
  - Tests: a press without access, on a closed lesson, without the token and over the rate refused; a quiz lesson below and at the needed score; two presses writing one row.
  - Pictures: each block's states, signed out, a phone. To the owner.
  - Features: Z8, Z9, Z11, Z12.

- CO5 (M) after CO4: the certificate and the event.
  - Existing files: `includes/api/outbound/webhooks.php` and `includes/workspace/watch.php` (the event's name for webhooks and flows).
  - New: the root file `certificate.php` (the member's certificate, the public check).
  - Tests: a member who did not complete refused; a code of another course refused; the check's rate limit.
  - Pictures: the certificate on screen and printed, the check page. To the owner.
  - Features: Z10 (the event), Z13, Z14.

- CO6 (S) close-out: every Z number checked against the code with its PR; `designer/course-progress.md`; this plan's three files deleted and its line in `plans/more-features.md` removed.

## Which steps run side by side?
- CO2, CO3 and CO4 run beside each other once CO1 has landed: they touch different existing files. CO5 follows CO4.
