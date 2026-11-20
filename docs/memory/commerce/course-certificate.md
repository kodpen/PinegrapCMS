---
name: course-certificate
description: The course certificate (certificate.php): the member's printable page, the public code check and its rate, refusals in order, and the course.completed event for webhooks and flows (#10487)
module: course_certificate
---

## What does certificate.php show?
- `?course=<id>`: the signed-in member's certificate of a completed course with "Certificate" on: the site's logo (`ORGANIZATION_LOGO`) and name, the member (contact name, else username), the course, the day of the completion row, the code and a QR of the check's address (`pg_qr_svg_data_uri()`). "Print or save as PDF" prints it; `assets/css/certificate.css` makes it one A4 landscape page. No PDF is made on the server.
- The member reaches it from the "Course progress" block: `pg_course_member_view()` gives `certificate` (the address, '' before the completion row or without "Certificate") and `pg_course.js` adds the "Certificate" link after the progress line.
- `?code=<code>` (or the form): the public check, showing name, course, day and code for a code that matches; else "No certificate has this code."

## In which order is a certificate refused?
- `pg_course_certificate_refusal()`, pure: switch off or tables missing 404 `off`; a visitor 401 `sign_in` (sent to `registration_entrance.php` and back); no course 404 `course`; "Certificate" off 404 `certificate`; no completion row 403 `not_complete`.

## When does a code match?
- `pg_course_certificate_matches()`, pure: the code's signature holds (`pg_course_certificate_parse()`, `ENCRYPTION_KEY`), its course exists with "Certificate" on, and the member's completion of that very course has the code's moment. A code made for another course, a reset completion (Reset progress) or one written again later does not match. Nothing is stored for a code.

## How is the check limited?
- `pg_course_throttled('check')` per address before any code is read: 20 per 10 minutes, counter `course_check` (`pg_course_rate()` holds all three course rates). Over it 429 and a `rate-course` log line for `certificate.php`. `pg_course_check_refusal()` orders it: off, rate, invalid.

## What is course.completed?
- Announced by `pg_course_mark_done()` only when it wrote the completion row, so once per member and course (again after Reset progress). Data: `id`, `name` (course), `user_id`, `contact_id` (null for none), `completed_at` (UTC ISO 8601).
- Webhooks: `api_webhook_core_events()`, fields in `api_webhook_event_payloads_site()`, area "Courses" in `api_app_event_area()`.
- Flows: `pg_event_workspace_events()`, `ws_events_catalog()` once `pg_course_ready()` (record the member's contact, right `contacts`), `ws_flow_event_outputs()`, the Customers area of `ws_flow_quick_areas()`, a parity line.

## Tests?
- `tests/course_certificate_test.php`: each refusal, a code of another course, a changed code, a reset completion, the check's rate before the code, the event's places.
