---
name: system-status-ca-bundle
description: The "CA Certificate Bundle" system status check (CURL_CA_BUNDLE age) - when it runs, how age is read from the mk-ca-bundle header, thresholds, weight and cache.
---

## When does the CA Certificate Bundle check run?
- In `get_system_status_checks()`, right after the SSL check, in the same `security` group: icon family `bi-patch-*`, short label "CA bundle", weight `ca_bundle` = 8 (bottom of the Moderate class, same as IndexNow).
- Only when `CURL_CA_BUNDLE` is defined, non-empty and the file is readable. If empty, the system store is used and **no row is added at all** - not even a grey "Uygulanmaz".

## How is the age computed?
- The first 10 lines of the file are searched for curl's `mk-ca-bundle` header (`## Certificate data from Mozilla as of: <date>`); the date is read with `strtotime()`, age in whole months with `date_diff()`.
- No header, unparseable, or a future date -> the check is silently skipped (no row, no warning).

## What are the thresholds and output?
- Older than 12 months -> yellow (half the weight); older than 24 months -> red (full weight); otherwise green.
- Message carries the parsed date (per site `DATE_FORMAT`) and the age in months; tile value is "N ay".

## Cache caveat
- Subject to the 10-minute status cache: after changing the constant in `config.php` the new row does not show until `data/temp/system_status_cache.json` is deleted. The cache shape version (`v`) was not changed: no new key was added to each check.
