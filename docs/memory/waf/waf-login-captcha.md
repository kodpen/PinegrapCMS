---
name: waf-login-captcha
description: pg_login_captcha_* login question: when it appears, counter-driven decision, field name/id rule for password managers, wrong answer is not an offence.
---

## When does the login question appear?
- Before the lock, after `login_throttle_captcha_after` failures (3; 0 disables). The decision comes from `waf_rate` COUNTERS, not the session: an attacker who drops the cookie drops the session, the counter stays.
- Do not use `get_captcha_fields()` (answer sits in a hidden field as base64, a script can read it). The question is session-bound, single-use, 10 min.
- The intermediate screen is printed from the POST handler (`pg_login_captcha_gate` right after the guard; `pg_login_captcha_after_failure` after the error is flagged); do NOT add a field to the four render places of the login forms. Excludes API login.

## Which field names/ids must it use?
- The credential fields' name and id are IDENTICAL to the login form it stands in for (`email`/`password`; in membership `u`/`p`). Password managers match the record by them; with invented ids (`login_captcha_password`) managers filled nothing or another record, and a user entering the right password got "wrong password".
- A screen that asks for a password must look like the screen where the password was saved.

## What does a wrong answer count as?
- A failed attempt (`pg_login_record_failure`), NOT an offence. The first version counted offences and banned an office address for an hour: the question is a step BEFORE the lock, so it cannot be harsher than the lock; five wrong answers must not put an office behind 403.

Related: docs/memory/waf/waf-ip-subject-ipv6-64.md
