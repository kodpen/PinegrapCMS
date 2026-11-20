---
name: course-enrolment-day
description: A course's enrolment day: the first counting order granting the course folder or a parent of it, else the first open; one reader for gate, blocks and learners
module: courses
---

## What is the enrolment day?
- The day of the member's first counting order (status complete or exported) with a line that opens the course's folder; else the day of the first-open row. Cancelled and incomplete orders do not count.
- No row names an order: owner (2026-11-05, #7204) refused a grants-per-order table, so the day is read from the orders each time.

## Which order line opens the course's folder?
- One whose product has a private access grant on the course folder or on any parent of it, the way `check_private_access()` walks the parents (#10623).
- Before #10623 only a grant on the course folder itself counted: a member who bought an "Academy" product, with the course in a subfolder, got the day of the first open instead, so every lesson opened late.

## Where is it read?
- `pg_course_member_orders()` (gate and course blocks, `includes/fn/courses.php`) and the learners list's own query in `pg_course_learners()` (`includes/fn/courses_admin.php`). Both match `pg_course_grant_folder_sql(pg_course_folder_ids($folder_id))`; a change to one changes the other.
- `pg_course_folder_ids()` reads the folder tree once per request; the pure `pg_course_folder_chain()` walks it nearest first and stops at the root, a parent that names no folder, a loop, or after `PG_COURSE_FOLDER_DEPTH` (64) folders.
- An empty chain matches nothing (`1 = 0`). Tests: `tests/courses_test.php`.
