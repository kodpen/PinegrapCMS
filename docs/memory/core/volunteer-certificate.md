---
name: volunteer-certificate
description: Volunteer hours certificate (VS7): volunteer_certificate.php, its signed code with nothing stored, who may open it, the public check; thank-you e-mails at 50, 100 and 250 approved hours
module: volunteer_certificate
---

## Where is it?
- Rules and store: `includes/fn/volunteer_certificate.php`, pure parts tested in `tests/volunteer_certificate_test.php`. Page: root `volunteer_certificate.php`, in `waf_sensitive_scripts()`, no-store, noindex, no referrer, no frame, the volunteer page's throttle (`pg_volunteer_page_throttled()`). Built in step VS7 (`volunteer-shifts.md`).
- Drawn in `certificate.php`'s look (`assets/css/certificate.css`, one A4 landscape page, "Print or save as PDF"); no PDF is made on the server (`commerce/course-certificate.md`).

## Who opens a certificate?
- `?p=<volunteer>&from=&to=`: only a profile this browser holds: `$_SESSION['volunteer']['ids']` (a signed link opened) or the signed-in member's own (`pg_volunteer_account_ids()`); a left volunteer gets the "link cannot be used" card.
- The volunteer's page (signed link and My Account) shows "Approved hours this year" with "Get my certificate" in its Hours part (`pg_volunteer_page_hours_html()`).
- Period: from the first of this year to today by default; a day after today becomes today; a start after the end or more than ten years is refused (`pg_volunteer_certificate_period()`). No approved hours: a note, no certificate.

## What does it state, and how is it checked?
- Approved hours only (`pg_volunteer_approved_minutes()`): a shift's row on its start day, a row outside a shift on its `worked_on`.
- Code: volunteer id, first and last day (YYYYMMDD) and minutes in base 36, then 12 hex of HMAC-SHA256 `volunteer-certificate:` under `ENCRYPTION_KEY`. Nothing is stored.
- `?code=` (or the form) checks it: the signature, the profile still exists (any state), and the period still has at least the code's approved minutes (`pg_volunteer_certificate_holds()`), so hours approved later keep it valid and an erased person's codes fail. Shows name, programme, hours, period, code.

## When does a thank-you e-mail go?
- When approved hours of a profile reach 50, 100 or 250 (`PG_VOLUNTEER_THANKS_HOURS`), once each; passing two at once sends one, for the higher (`pg_volunteer_thanks_due()`).
- `pg_volunteer_hours_approve()` calls `pg_volunteer_thanks_send()` for each volunteer it approved: `volunteers.thanked_hours` is claimed by an UPDATE conditional on the old value, then the programme's "Thank you" message goes by e-mail with a line naming the hours. No address or a left volunteer: claimed, nothing sent.

## What was not verified?
- No database in the cloud session: the page, the approval's e-mail and the check were not run against real rows.

Related: docs/memory/core/volunteer-hours.md, docs/memory/core/volunteer-report.md, docs/memory/commerce/course-certificate.md
