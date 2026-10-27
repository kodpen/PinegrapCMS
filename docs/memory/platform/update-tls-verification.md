---
name: update-tls-verification
description: TLS on outgoing cURL: pg_curl_tls() / da_curl_tls(), no silent downgrade, CURL_CA_BUNDLE, ALLOW_INSECURE_UPDATE_TLS; gateways, carriers and the package download use it, Stripe and iyzico do not.
---

## Why must the update channel verify TLS?
- `CURLOPT_SSL_VERIFYPEER` was 0 on licence / update / package download paths; the adjacent `VERIFYHOST = 2` did nothing (without chain verification there is no verified certificate to match a hostname against).
- On a code-carrying channel the cost differs: whoever can intercept serves their own archive, it is opened into the web root and runs. The update mechanism becomes a delivery mechanism.

## What enables verification and what are the escape hatches?
- `pg_curl_tls($ch)` (`includes/fn/update.php`) and its standalone twin `da_curl_tls()` (`download_assistant.php` at the repository root, outside `pinegrap/`) turn verification on. On failure they do NOT fall back to insecure: a silent downgrade is worse than no verification, since the attacker need only break the first attempt.
- Opting out is an explicit decision in `data/config.php`: `CURL_CA_BUNDLE` (path of a cacert.pem when the CA store is not on the default path) and `ALLOW_INSECURE_UPDATE_TLS` (last resort, default `false`). Only `pg_curl_tls()` and `download_assistant.php` read the flag, so it reaches every call listed below; it stays as the operator's one last resort (#70).
- `pg_curl_run()` (same file) wraps a fixed vendor-host call and applies `pg_curl_tls()` last: `curl-run-wrapper.md`.
- `pg_curl_tls_hint()` turns cURL errors 60/77/35 into a sentence the operator can act on (their own CA bundle, not the other side).

## Are payment and carrier calls covered?
- Yes, all but two SDKs. The card gateways written in `submit_order.php` (Authorize.Net, ClearCommerce, First Data, PayPal Payflow Pro / Payments Pro / Express Checkout, Sage; every `curl_init()` there), PayPal in `recurring_payment_job.php`, Givex (`send_givex_request()`, through `pg_curl_run()` since #2124) and the carriers (`pg_carrier_api_http()`: every USPS, UPS and FedEx call) go through `pg_curl_tls()`.
- Stripe and iyzico (the card form and the Pay With Iyzico driver `includes/payments/iyzico.php`) run inside their bundled libraries (`includes/stripe/`, `includes/iyzipay-php/`; their HTTP clients are not edited): verification is on with the library's own CA file (iyzipay's `cacert.pem`; Stripe retries with its `ca-certificates.crt` after a certificate error), and the two config settings do not reach them.

## Which other calls go through pg_curl_tls()?
- Through `pg_curl_run()`: the vendor-host calls, the package download of Software Update and Repair Software (#2074), Mailchimp, the IndexNow ping, Givex, StopForumSpam, currency rates, AI bot ranges, SMS and İYS, Paraşüt, e-documents, licence checks, CAPTCHA, `migration.php` (`curl-run-callers.md`). `api_http_request()`: `import_design.php`. Directly: the location database, S3 backups, time stamps. New calls use one.
- https only: an İşbaşı API address typed as `http://` fails since #1921 (`curl-run-wrapper.md`).

## Which calls do not?
- The outbound client `api_http_request()` (webhooks, push, marketplace connectors, MCP client, cloud backup uploads) verifies and reads `CURL_CA_BUNDLE` but deliberately has no insecure escape (a delivery to an unidentified receiver goes to whoever answered); the Workspace AI and Claude calls set the same two options.
- The CA store probe (`pg_ca_bundle_config_state()`): it tests the default store, which the flag would hide.
- `url_exists()` (`core.php`, no caller) turns verification off.

Related: docs/memory/platform/ca-bundle-panel-update.md, docs/memory/platform/curl-run-callers.md
