---
name: course-student-hunt
description: Bug hunt of the courses' student side (architect, 2026-11-20, agent channel message 10214): files read, the lesson outside its folder fixed (#10601), the parent-folder enrolment day left as #10603
module: courses
---

## Which hunt read the student side?
- Architect (2026-11-20, agent channel message 10214) named the area: `course_action.php`, `includes/fn/courses.php`, `includes/fn/course_blocks.php`, `assets/js/pg_course.js`, `certificate.php`, `includes/fn/course_certificate.php`. Read by #10601.
- The start file of courses is `designer/course-progress.md`; this file holds only the hunt.

## What did it fix?
- A lesson whose page left the course's folder or was deleted (Z17) still counted on the student side. With "Lessons in order" the press refused it (404 `lesson`), so every lesson after it stayed "Locked" for good, and the course could never complete, so no certificate.
- Fix: `pg_course_member_lessons()` (reader) over the pure `pg_course_lessons_counted()` keeps only lessons whose page sits right in the folder; the gate, the blocks' view, the press and `pg_course_mark_done()` read it. Tests in `tests/courses_test.php`. Such a lesson keeps its row (and the member's done rows) until the Courses screen saves.

## What became an issue?
- #10603: a product that grants a parent folder of the course's folder opens the course but is no counting order, so the enrolment day falls back to the first open. Both `pg_course_member_orders()` and the learners list in `pg_course_learners()` (`courses_admin.php`, outside this hunt) match the folder itself only.

## What was read clean?
- The press's refusal order, token and origin; a press writes only the signed-in member's row (`USER_ID`); two presses or a race write one done row and one completion, and `course.completed` once.
- The quiz: the best completed submission of the quiz page by the member; a saved draft (`complete` 0) never counts.
- A visitor or a member without the folder gets names only; an expired grant is no member.
- The certificate code: a 48-bit HMAC signature checked with `hash_equals`, 20 checks per 10 minutes per address, counted before the code is read; a changed part, another course or a reset completion does not match.
- `pg_course.js` writes every server value with `textContent` or `setAttribute`.
