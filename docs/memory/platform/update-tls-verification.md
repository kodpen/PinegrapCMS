---
name: update-tls-verification
description: TLS verification on licence/update/package cURL calls: pg_curl_tls(), no silent downgrade, CURL_CA_BUNDLE, ALLOW_INSECURE_UPDATE_TLS, exclusions.
---

## Why must the update channel verify TLS?
- `CURLOPT_SSL_VERIFYPEER` was 0 on licence / update / package download paths; the adjacent `VERIFYHOST = 2` did nothing (without chain verification there is no verified certificate to match a hostname against).
- On a code-carrying channel the cost differs: whoever can intercept serves their own archive, it is opened into the web root and runs. The update mechanism becomes a delivery mechanism.

## What enables verification and what are the escape hatches?
- `pg_curl_tls()` (functions.php) and its standalone twin `da_curl_tls()` (download_assistant.php) turn verification on. On failure it does NOT fall back to insecure: a silent downgrade is worse than no verification, since the attacker need only break the first attempt.
- Opting out is an explicit decision in `config.php`: `CURL_CA_BUNDLE` (path of cacert.pem if the CA bundle is not at the default path) and `ALLOW_INSECURE_UPDATE_TLS` (last resort, default `false`).
- `pg_curl_tls_hint()` turns cURL errors 60/77/35 into a sentence the operator can act on; otherwise the screen says "cURL error 60" and it is not clear the cause is their own CA bundle, not the other side.

## What is deliberately excluded?
- Payment gateways (`submit_order.php`, Givex) and shipping APIs (`shipping.php`). Some old gateways have genuinely broken certificates; the risk of breaking live payment far exceeds the gain.

Related: docs/memory/platform/ca-bundle-panel-update.md
