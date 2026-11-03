---
name: system-status-ca-bundle
description: The "CA Store" and "CA Certificate Bundle" system status checks - which store outbound TLS verifies against and why, the probe from System Status, the bundle age, thresholds, weights and cache.
---

## What does the CA Store row say?
- In `get_system_status_checks()`, `security` group, right after the SSL check, title `CA Store` (short label "CA store", weight `ca_setting` = 12), a tile and not a job: there is no button since #5711. It calls `pg_ca_bundle_config_state()`, which runs the probe of the server's own store first unless the `CURL_CA_BUNDLE` override is in use (`update-tls-verification.md`), so the row costs one five-second request per status build, cached ten minutes with the rest.
- Green, with the reason as the value: the override (`Override`, the file named); the bundled `data/cacert.pem` because PHP has no store of its own or because the server's store failed the probe on a date, with the cURL error (`Bundled file`); the server's own store that verified a connection on a date (`Server store`). Grey `Not probed` when the server's store is in use and the probe reached nothing or never ran.
- Red, full weight: the override names a missing file (`File missing`; the choice went on without it and the row says what is used instead), or the server's store is missing or failing and `data/cacert.pem` is gone too (`No store`: update the CA certificate bundle).

## When does the CA Certificate Bundle check run?
- Right after the CA Store row, same group: icon family `bi-patch-*`, short label "CA bundle", weight `ca_bundle` = 8 (bottom of the Moderate class, same as IndexNow).
- Only when `pg_ca_bundle_effective()` names a readable file (the override or the shipped bundle the site chose). With the server's own store in use the package manager keeps that current, so **no row is added at all** - not even a grey "Uygulanmaz".

## How is the age computed?
- The first 10 lines of the file are searched for curl's `mk-ca-bundle` header (`## Certificate data from Mozilla as of: <date>`); the date is read with `strtotime()`, age in whole months with `date_diff()`.
- No header, unparseable, or a future date -> the check is silently skipped (no row, no warning).

## What are the thresholds and output?
- Older than 12 months -> yellow (half the weight); older than 24 months -> red (full weight); otherwise green.
- Message carries the parsed date (per site `DATE_FORMAT`) and the age in months; tile value is "N ay".

## Why does a change not show at once?
- Subject to the 10-minute status cache: a probe that healed or a new override does not show until `data/temp/system_status_cache.json` is deleted or expires. The cache shape version (`v`) was not changed.
