---
name: update-tls-verification
description: TLS on outgoing cURL: pg_curl_tls() / da_curl_tls(), no silent downgrade, the store from pg_ca_bundle_effective(), ALLOW_INSECURE_UPDATE_TLS; who uses it (classic Stripe and iyzico do not).
---

## Why must the update channel verify TLS?
- `CURLOPT_SSL_VERIFYPEER` was 0 on licence / update / package download paths; `VERIFYHOST = 2` beside it did nothing. On a code-carrying channel whoever can intercept serves their own archive, which is opened into the web root and runs.

## What enables verification and what are the escape hatches?
- `pg_curl_tls($ch)` (`includes/fn/update.php`) and its standalone twin `da_curl_tls()` (`download_assistant.php` at the repository root) turn verification on. On failure they do NOT fall back to insecure: a silent downgrade is worse than none, the attacker need only break the first attempt.
- The store is chosen by the site, not by a setting: `pg_ca_bundle_effective()` (`ca-store-choice.md`). `da_curl_tls()` keeps the offline half only (override, else the shipped file beside it when PHP has no store): the software may not exist yet.
- `CURL_CA_BUNDLE` and `ALLOW_INSECURE_UPDATE_TLS` (last resort, default `false`) in `data/config.php` stay file-level overrides and no screen names them as the fix (`workflow/decisions-made-access.md`). Only `pg_curl_tls()` and `download_assistant.php` read the flag, so it reaches every call listed below (#70).
- `pg_curl_run()` (same file) wraps a fixed vendor-host call and applies `pg_curl_tls()` last: `curl-run-wrapper.md`.
- `pg_curl_tls_hint($errno, $advice = false)` turns cURL errors 60/77/35 into a `lang()` sentence (their own CA store, not the other side). The advice, only with `$advice` and USER_ROLE 0, points at the CA store row of System Status; `api_http_request()` has no flag (#3515).

## Are payment and carrier calls covered?
- Yes, all but two SDKs (below). The card gateways written in `submit_order.php` (Authorize.Net, ClearCommerce, First Data, PayPal Payflow Pro / Payments Pro / Express Checkout, Sage), PayPal in `recurring_payment_job.php`, Givex (through `pg_curl_run()` since #2124) and the carriers (`pg_carrier_api_http()`: USPS, UPS, FedEx) go through `pg_curl_tls()`.
- The Stripe PaymentIntents driver (`includes/payments/stripe.php`) uses `pg_curl_tls()` through `pg_curl_run()` (#2743). Classic Stripe and iyzico (`includes/payments/iyzico.php`) run inside their bundled libraries (`includes/stripe/`, `includes/iyzipay-php/`, not edited): verification is on with the library's own CA file, and the chooser does not reach them.

## Which other calls go through pg_curl_tls()?
- Through `pg_curl_run()`: the vendor-host calls, the package download of Software Update and Repair Software (#2074), Mailchimp, the IndexNow ping, Givex, StopForumSpam, currency rates, AI bot ranges, SMS and İYS, Paraşüt, e-documents, licence checks, CAPTCHA, `migration.php`, the PayPal Checkout and Stripe drivers (`curl-run-callers.md`). `api_http_request()`: `import_design.php`. Directly: the location database, S3 backups, time stamps. New calls use one.
- https only: an İşbaşı API address typed as `http://` fails since #1921 (`curl-run-wrapper.md`).

## Which calls do not?
- The outbound client `api_http_request()` (webhooks, push, marketplace connectors, MCP client, cloud backup uploads) verifies against `pg_ca_bundle_effective()` but deliberately has no insecure escape (a delivery to an unidentified receiver goes to whoever answered); the Workspace AI and Claude calls set the same two options. The stream contexts of `import_design.php`, `imap_client.php` and the licence fallback in `output.php` take the same file as `cafile`.
- The CA store probe (`pg_ca_bundle_probe_run()`, `ca-store-choice.md`): it tests the server's own store, which the flag or the shipped file would hide.
- `url_exists()` (`core.php`, no caller) turns verification off.

Related: docs/memory/platform/ca-store-choice.md, docs/memory/platform/ca-bundle-panel-update.md, docs/memory/platform/curl-run-callers.md
