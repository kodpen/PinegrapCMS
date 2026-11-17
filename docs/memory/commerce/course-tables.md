---
name: course-tables
description: Courses' data (2026.11.0, step 11.0.234): the course, course_lesson and course_progress tables, the open-lesson rule, the enrolment day, rows written once, the certificate code
module: courses
---

## What holds a course?
- `course`: name, the private folder a product already opens (`folder_id`), the classroom page, `in_order` ("Lessons in order"), `certificate`. Selling does not change: the product and the order's grant stay as they are.
- `course_lesson`: a page of the folder, `sort_order`, `open_days` (days after enrolment, 0 at once), an optional quiz form page (`quiz_page_id`) and the score it needs (`quiz_pass_score`, 0-100, 0 with no quiz; named apart from `forms.quiz_score`, the score a member got). A page is a lesson of one course only (UNIQUE `idx_page`, it serves the gate); the writer refuses a taken page and names its course (`pg_course_page_taken()`). `idx_course` lists a course's lessons in order.
- `idx_folder` is not unique: one private folder may hold several courses (a membership folder).
- `course_progress`: one row per fact, key (user_id, course_id, kind, lesson_id): kind 1 a lesson done, 2 the first open of a course given by hand, 3 the completion (lesson_id 0 for 2 and 3). Kinds are the constants `PG_COURSE_KIND_*` in `includes/fn/courses.php`, never bare numbers in a query.
- Architect (2026-11-16, agent channel message 7451): the columns as above, with `idx_page` unique and `quiz_pass_score` named apart.
- Readers ask `pg_course_ready()` first: files land before the upgrade runs.

## How is a row written once?
- Every writer goes through `pg_course_write_row()`: `INSERT IGNORE` on the key, so a second press or a race writes nothing and returns false. Done stays done; there is no unmark.
- The first-open row only for a signed-in member who has the folder (`pg_course_first_open_row()`); the caller passes whether they have it.
- `pg_course_mark_done()` writes the done row, then the completion row once every lesson is done (`pg_course_completion_row()`); a course without lessons is never complete. A completion row is never rewritten, not even when lessons are added later: the certificate's day and code hang on it.
- A deleted lesson takes its done rows with it (`pg_course_lesson_delete()`), also when its page is deleted or leaves the folder; a completion stays.

## When is a lesson open?
- `pg_course_lesson_state()`, pure, in this order: an editor passes; a page that is no lesson of the course is not closed; a done lesson stays open; its days since the enrolment day; with "Lessons in order" the lesson before done. Days come first, so a notice names the later of the two.
- Days are calendar days (`pg_calendar_days_later()` from noon of the enrolment day). A member with no enrolment day yet counts from today, the day the first open is written.

## What is the enrolment day?
- The day of the member's first counting order (status complete or exported) with a line that opens the course's folder; else the first-open row's day. Cancelled and incomplete orders do not count.
- No row names an order: owner (2026-11-05, #7204) refused a grants-per-order table, so the day is read from the orders each time.

## What is the certificate's code?
- `pg_course_certificate_code()`: course, member and completion moment in base 36, then 12 hex of an HMAC with `ENCRYPTION_KEY`; nothing more is stored. `pg_course_certificate_parse()` reads it back in any letter case and refuses any changed part; the caller still checks the completion row exists.

## Where does progress go with personal data?
- The export's `courses` section and the erasure's `course_progress` row (delete by user, under "Course progress"); the course and lessons stay.
