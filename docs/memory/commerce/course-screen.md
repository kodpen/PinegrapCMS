---
name: course-screen
description: The Courses screen (view_courses.php, edit_course.php): switch and menu link, who opens it, lessons and their save, problems named (Z17), learners, CSV, Reset progress
module: courses_admin
---

## Where is it, and who opens it?
- Site Settings, Features, card "Courses" (`pgset-courses`): the switch `courses` in `config_text`, saved by `pg_course_switch_save()`. Off on every site.
- With the switch on, the Pages menu shows "Courses" to managers and above (role < 3, `pg_course_staff_allowed()`); both screens call `validate_area_access($user, 'manager')` and answer an error while the switch is off or the tables are missing.
- The role is the step's own choice (managers already manage folder access and members); the plan left it to the architect, asked in the PR.

## What does the list show?
- `view_courses.php`, a printed list (`pg_list_table_printed()`, name `view_courses`): course, folder, lessons, learners (link to the learners view), completions and a state pill: Working (teal), Needs a look (amber), Closes nothing (rose). The problems are written under the course's name.

## How is a course edited?
- `edit_course.php`: name, private folder (only folders whose access, own or inherited, is private), classroom page, "Lessons in order", "Certificate"; a plain table of lessons (page, days after enrolment, quiz form, score needed), add, remove, move up and down. Page selects offer every private folder's pages and the script shows only the chosen folder's.
- `pg_course_input()` (pure) reads the parallel lists `lesson_id[]`, `lesson_page[]`, `lesson_days[]`, `lesson_quiz[]`, `lesson_score[]`; a row without a page is skipped; a page twice, days outside 0–3650, a quiz without a score from 1 to 100 are refused.
- `pg_course_save()` refuses a page that is another course's lesson and names that course. A lesson left out, or whose page was changed, is deleted with its done rows first (the page key is unique), then kept lessons are updated and new ones inserted.
- Delete removes the course, its lessons and every progress row; the folder, its pages and the members' access stay.

## Which problems are named? (Z17)
- `pg_course_problems()` (pure): folder deleted, folder not private (these two close nothing), no lessons, no classroom page, classroom page outside the folder, a lesson whose page left the folder or was deleted.
- A lesson counts only when its page sits right in the course's folder (`pg_course_lesson_in_folder()`); the gate in `pg_course_gate()` lets any other page through.

## What is the learners list?
- The view `edit_course.php?id=…&view=learners` (printed list `edit_course_learners`): every member with private access to the folder (`aclfolder_rights` 1) and every member with progress rows, with the enrolment day (first counting order, else the first open), lessons done "n of m", last activity and the completion day. Search in the toolbar; "Download CSV" (`&csv=1`, cells through `csv_cell()`).
- "Reset progress" asks first, then deletes the member's done, first-open and completion rows of the course: the certificate's code no longer checks, and a member given the folder by hand counts their days again from the next open.

Related: docs/memory/commerce/course-tables.md, docs/memory/commerce/course-gate.md
