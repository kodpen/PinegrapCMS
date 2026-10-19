---
name: sms-provider-layer
description: SMS layer (PR #600): pg_sms_send() and kinds, Netgsm REST v2 driver, account in connect_apps sms_<key> (no schema), Communication › SMS card, save-time check, test message
---

## What sends an SMS?
- `pg_sms_send($to, $text, $kind)` (`includes/fn/sms.php`) returns `ok, id, code, error, retry`. Before reading any account it refuses an unknown kind, a commercial one (`marketing`, until İYS consent records exist), an unreadable number, an empty text.
- `pg_sms_number()` gives E.164 digits without the plus. National forms (`05xx…`, `5xx…`, `90 5xx…`) are read as Turkish mobiles; other countries need `+` or `00`; under 90 only `5xx` mobiles pass.
- It calls the provider at once: only the settings' test does so in a request; other messages go through a queue (plan step 2).

## How is a provider added?
- An entry in `pg_sms_providers()` and `includes/sms/<key>.php` with `pg_sms_<key>_send($app, $number, $text, $commercial)` and `pg_sms_<key>_senders($app)`. Builders and parsers are plain functions (`tests/sms_test.php`); HTTP goes through `pg_sms_http()` → `api_http_request()`.
- No schema: the account is the `connect_apps` row `sms_<key>` like the carriers' API apps (`carrier_api.php`): user code = `client_id`, password sealed with the site key (`pg_connect_app_save()`), `options.sender`, `enabled` = the switch. The plan said "config blob"; this needs no migration step.
- The active provider is the first enabled one with user, password and sender (`pg_sms_provider()`). Saving one provider switches the others off, their accounts kept. With a second provider the card needs a show/hide by the select.

## What does Netgsm take?
- `POST https://api.netgsm.com.tr/sms/rest/v2/send`, Basic auth (subscriber number or API sub-user : password), JSON `msgheader`, `messages[{msg,no}]`, `iysfilter` (`0` informational, `11` commercial), `encoding: TR` only when the text has ç ğ ı İ ş Ş Ğ. Number: 10 digits `5xxxxxxxxx`, Turkish mobiles only.
- Answer: JSON `code` `00` + `jobid`; refusals come as JSON with a 4xx, so the body is always read. `00` without `jobid` is a failure. Retried later: no answer, unreadable 5xx/429, codes 80, 85 (rate / duplicate limit), 100, 101. Not retried: 20 text, 30 (or a bare 401/403) credentials / API permission / IP limit, 40-41 sender, 50-51 İYS, 70 parameter.
- `GET /sms/rest/v2/msgheader` lists approved senders (`msgheaders` or `msgheader`) at no cost: the credential check.
- Endpoints and fields are from two client libraries (siberfx/netgsm, mobinova/netgsm-go).

## What does the Communication › SMS card do on save?
- `pg_sms_settings_decide()` (pure, tested) picks `none` / `save` / `disable` / `forget` from the post and the stored account; `pg_sms_settings_check()` marks its refusals before the config UPDATE (a mark stops the whole category) and asks the provider only about an account that changed or is being switched on, and only if no other card was refused. A refusal is marked on the field (code 30 on the password), an unapproved sender with the approved names; no answer saves unchecked with a notice.
- An unchanged account is never rewritten, so an unreadable one does not block chat or campaign saves; the card warns. Commerce › Reset Encryption Key re-encrypts card numbers only, not `connect_apps`. Switching off writes `enabled = 0` alone (works without sealing). Off + empty user code deletes the row. The password is never drawn back.
- "Save and send a test message" is the pane's only named submit, so Enter presses it: an empty Test Number is a plain save, a wrong one is marked. 10 tests per clock hour per site (`waf_rate` `sms-test`, fails open).

## How was it verified?
- `tests/sms_test.php`; on a sandbox instance through `settings_pane.php`: card in the dialog and the page path, the marks, save / unchanged / disable / forget rows, the test button. The sandbox could not reach Netgsm: real calls are unverified.

Related: docs/memory/plans/sms-and-iys.md, docs/memory/core/security-helpers-and-secret-storage.md
