---
name: course-blocks
description: The course blocks on the site: pg_course.js, course_action.php (view and the "Mark as done" press), the checks in order, quiz score, rates
module: course_blocks
---

## How does a page get the script?
- `get_page_content.php`: a page whose HTML holds `data-pg-course` (`pg_course_blocks_pattern()`) loads `includes/fn/course_blocks.php` and `pg_course_blocks_inject()` adds `window.pgCourseConfig = {door, page, labels}` and `assets/js/pg_course.js` before the last `</body>`, once. Nothing while the "Courses" switch is off or the tables are missing. Not in an e-mail.
- The stored page is the same for everyone: the script fills the blocks from `course_action.php`, so the page stays in the anonymous cache.

## What does course_action.php answer?
- GET `page=<id>`: the page's course (the one it is a lesson of, else the one whose classroom page it is, `pg_course_for_page()`) as `pg_course_member_view()`; for a signed-in member also `mine` (`pg_course_member_courses()`) and the form `token` the press sends back.
- The view is the pure `pg_course_blocks_view()`: with the folder (`check_private_access()`, not expired) every lesson's state `done`, `open`, `opens` (with `opens_on`) or `locked` from `pg_course_lesson_state()`, `total`, `done`, `complete`, `next` (the first open lesson not done) and `lesson` (the page's lesson: id, done, needed score). Anyone else: names only (Z12).
- POST `lesson`, `token`: "Mark as done". A let-through press writes through `pg_course_mark_done()` (INSERT IGNORE), so two presses write one row and give the same answer: `done`, `completed`, and the view again.

## In which order is a press refused?
- `pg_course_press_refusal()`, pure: not POST 405; another site (`pg_consent_answer_same_origin()`) 403 `origin`; switch off 404 `off`; over the rate 429 `rate`; a visitor 401 `sign_in`; a wrong token 403 `token` (`hash_equals`); no lesson, or its page left the course's folder, 404 `lesson`; without the folder 403 `access`; not open 403 `closed`; the quiz 403 `quiz` with `need`.
- A quiz lesson (`quiz_pass_score` > 0) is done only with a completed `forms` row of its quiz page by the member scored at or above it (`pg_course_quiz_passed()`, `pg_course_member_quiz_best()`, best score counts).

## What are the rates?
- Per address with `waf_rate_exceeded()` (works with the firewall off): views 120 per 10 minutes (`course_view`), presses 30 per 10 minutes (`course_press`); a refusal is logged as `rate-course`.
- `course_action.php` is in `pg_page_cache_request_dirties()`'s list: a press changes no page, so it does not clear the page cache.

## Tests?
- `tests/course_blocks_test.php`: each refusal, the quiz below and at its score, two presses, the member's and the visitor's view, the script added once to a page with a block only.
