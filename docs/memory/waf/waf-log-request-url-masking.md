---
name: waf-log-request-url-masking
description: Firewall log request_url keeps path and names, credential values become * (waf_mask_request_url(), waf_credential_params(), per-script short names) (#11778)
---

## Does the Firewall log keep a secret carried in the query string?
- No. Architect (2026-11-26, agent channel request 3050): `waf_log_event()` writes `request_url` through `waf_mask_request_url($url, waf_script_name())`, for every script: the path and every parameter name stay (the log reader needs them), the value of a credential parameter becomes `*`.
- Before, the full `REQUEST_URI` was stored: a flow's web address (`flow_start.php?flow=…&key=…`, rule `rate-flow-web`) or the calendar feed (`workspace_ical.php?token=…`) showed its secret to every administrator who opened the Firewall log (#11778).

## Which names are masked?
- One list in one pure function, `waf_credential_params($script)`: `key`, `token`, `secret`, `signature`, `sig`, `password`, `access_token`, `refresh_token`, `id_token`, `api_key`, `client_secret` for every script.
- A short name that is a credential on one script and plain input on another is masked only for the scripts that read it as one, by a list per script. A site page's `t` is a tracking code and stays.
- `t`: token pages such as `tickets.php`, `queue.php`, `feedback.php`, `workspace_share.php`, `mail_webhook.php`. `k`: `router.php` (the set-password page), `get_set_password.php`, `subscription_card.php`.
- `s`: `confirm_subscription.php`, `privacy_request.php`. `code` and `state`: `connect.php`, `get_oauth_token.php`. `code`: `certificate.php`, `volunteer_certificate.php`.
- Names are whole names compared as PHP reads them: decoded, case-insensitive, `.` or space as `_`, an array suffix ignored (`%6Bey`, `ToKeN`, `access.token`, `key[]` are masked; `monkey`, `key_id` are not).
- A new script that takes a secret in its query adds its name here, with a case in `tests/waf_log_url_mask_test.php`.

## What stays as sent?
- Every other value of a refused request, and the `target` and `matched` columns: they are the evidence the log is for. A name with no value or an empty value is left as it is.

## Where else is the address masked?
- The Firewall log screen's request cell (`pg_waf_log_list_row()`) and the ban notice e-mail's samples (`pg_waf_ban_notice_samples()`) call `waf_mask_request_url($url)` with no script: it is read from the address (a `.php` file name, else `router.php`). So a row stored before the change is shown masked too.
- `data/firewall.log` and the ban shield keep only the path, never the query (`waf_text_log_path()`, `pg_ban_shield_request_path()`).
- Rows already stored are not rewritten: no migration step; they age out with the log's own keep period.
