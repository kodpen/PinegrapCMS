---
name: course-gate
description: The course gate in get_page.php: a lesson not open yet sends the member to the classroom page with a one-time notice; the "Courses" switch reader; the first-open row
module: courses
---

## Where does the gate run?
- `get_page.php`, right after the access control switch: `pg_course_gate($page_id, $folder_id, $access_control_type)`. A member who got that far may see the folder; the gate only asks whether the lesson is open yet.
- It acts only with the "Courses" switch on, a signed-in member and a page of a private folder that is a lesson. A lesson in a public folder is a free preview and is never closed (Z7).
- The decision is pure: `pg_course_gate_answer()` (switch, access type, lesson, lessons, done ids, enrolment day, today, may edit) over `pg_course_lesson_state()`. Tests in `tests/courses_test.php`.

## What does a closed lesson do?
- A 302 to the course's classroom page, in the language being drawn, with the notice in `$_SESSION['software']['course_notice']` (page id and plain text).
- The notice names the day the lesson opens (`get_absolute_time()`, long date, site time zone) or the lesson to finish first by its page title (else its page name).
- The classroom page is never sent to itself, and a course without a classroom page answers 403 with the notice.

## How is the notice shown?
- `pg_course_notice_inject()` at the output tail of `get_page.php` (HTML page only), on the classroom page only, taken once. It calls `pg_page_cache_personal('course_notice')`.
- Markup: a `div.pg-course-notice` with `role="status"` right after `<body>`, its own scoped look (light, and dark under `data-bs-theme="dark"`), so it reads on a theme without Bootstrap. No close button: it is gone on the next load.

## Who writes the first-open row?
- The gate, for a member who is not an editor and has no counting order (the folder given by hand): the first open of a lesson writes it once, and that day is the enrolment day (Z5).

## Where is the "Courses" switch stored?
- `config_text` key `courses` (`PG_COURSE_SWITCH`): '1' on, missing off. `pg_course_switch_on()` is the one reader; the Features card's save writes it.
