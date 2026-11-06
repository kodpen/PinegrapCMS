---
name: custom-form-private-access-expiry
description: A custom form's private access expiry is today plus N calendar days (pg_cf_private_expiry_date()), never N * 86400 seconds, so a clock change does not end it a day early (PR #7039)
module: custom_form
---

## How is the expiry date of a form's private access worked out?

`custom_form.php` calls `pg_cf_private_expiry_date(date('Y-m-d'), $private_days)`
(`includes/fn/custom_form.php`): the date $days calendar days after today,
built with `gmmktime()` on the date parts. A bad date or no days gives `''`.
A longer expiry already on the access row is kept.

## Why not `strtotime(today) + N * 86400`?

A 25-hour autumn day inside the period made that land at 23:00 of the day
before, so the access ended one day early (fixed in PR #7039, test
`tests/custom_form_private_expiry_test.php`).

## And the Cloudflare custom range?

`cloudflare.php` takes a custom range's day count from
`pg_cloudflare_analytics_range()['limit']`, which counts on UTC midnights;
`(int)(diff / 86400) + 1` was one short across a spring clock change.
