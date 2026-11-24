---
name: api-volunteers
description: GET /volunteer/programmes, GET /volunteer/shifts and GET /volunteer/hours (VS8, customers:read, MCP tools): fields, filters, coordinator rule for the user role, what is never given
---

## Where are they?
- `includes/api/resources/volunteers.php`, routes `volunteer.programmes`, `volunteer.shifts`, `volunteer.hours` in `api_schema()`, scope `customers:read` (a volunteer is a contact). Types `VolunteerProgramme`, `VolunteerShift`, `VolunteerHours`, presenters paired in `tools/check_api_schema.php`; nested role rows come from `api_volunteer_role_present()` and `api_volunteer_shift_role_present()`. MCP tools by the route rule. Tests: `tests/volunteer_events_test.php`.

## Who reads which programme?
- Roles 0-2 every programme; the user role only the programmes listing it as coordinator (`pg_volunteer_user_sees()`), as Contacts › Volunteers. Another `programme_id` answers 404 `not_found`. Before the volunteer tables the lists are empty.
- Never given: documents, the sign-up link, messages, the volunteers' names.

## What do they answer?
- Programmes: by id, `state` open/paused/all; roles (key, name, skills, course_ids, documents), the sign-up and call settings, `coordinator_ids`, counts applied/active/paused.
- Shifts: earliest first from today (`from`, `to` as site days YYYY-MM-DD, else 422), `place`; per role places, signed, waiting; `needed`, `called` (how many asked). The cursor is the start (unix of the site's time) and the id.
- Hours: newest first by id; a shift's row once checked in, or a row outside a shift; `status` approved (default), pending, all; `volunteer_id`, `contact_id`, `from`/`to` by day. Approved minutes are kept ones; pending ones count as `pg_volunteer_signup_minutes()` would now.

Related: docs/memory/core/volunteer-events.md, docs/memory/core/volunteer-programmes.md, docs/memory/api/api-endpoint-rules.md
