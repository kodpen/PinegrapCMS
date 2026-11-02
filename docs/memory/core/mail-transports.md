---
name: mail-transports
description: API mail transports beside SMTP (Postmark, Mailgun, Brevo, Resend; SES follows): the driver contract, where the account is kept, when email() uses one, the SMTP Settings card
module: mail_transport
---

## What is a mail transport?
- A provider's HTTP API that takes a message instead of the SMTP server: a driver `includes/mail/<key>.php` listed in `pg_mail_transports()` (`includes/fn/mail_transport.php`), functions `pg_mail_<key>_fields()`, `_capabilities()`, `_request($message, $settings)` and `_answer($status, $body)` (both pure), `_send()`; `_webhook()` comes with the webhooks step. Contract comment: `includes/mail/driver.php`.
- Drivers: Postmark (JSON, server token header, `MessageStream`, a separate campaign stream if named), Mailgun (multipart form built by hand, Basic `api:<key>`, US or EU host; the body is not left to cURL because a file part from memory needs `CURLStringFile`, PHP 8.1), Brevo (JSON, `api-key`, one Reply-To), Resend (JSON, Bearer). Amazon SES is MD3b, with `includes/fn/aws_sign.php`.
- Every call goes through `pg_mail_transport_call()` → `pg_curl_run()` (60 s), refuses a body over the driver's `max_bytes` and one `json_encode()` could not build (not UTF-8).

## When does email() use one?
- Order: the message's own `smtp`, its own `connection`, the connected account SMTP Settings chose for its kind, then the transport switched on (`pg_mail_transport()`), then the SMTP server or `mail()` as before. Nothing switched on: legacy behaviour unchanged.
- The message is still built by PHPMailer (as SMTP), read back by `pg_mail_transport_message()` (addresses, text/html, custom headers including `List-Unsubscribe` and threading, attachments with their bytes, inline ones with their cid) and handed to the driver. The site's DKIM key is not used: the provider signs for the domain verified with it. `keep_mime` still gets the SMTP form of the message.
- A refusal is permanent (the queue gives up) for a 4xx except 401, 403, 408 and 429 (`pg_mail_transport_status_permanent()`): a wrong key can be fixed, a rate limit passes. A connection failure is not permanent.

## Where is the account?
- `connect_apps` row `mail_<key>` (no schema, as `platform/sms-provider-layer.md`): the driver's one `secret` field sealed as `client_secret`, the other fields in `options`, `enabled` = in use. The step line named `config.mail_transport`; a column would have been a schema change, and the enabled row already says which one is in use.
- One is on at a time: saving one switches the others off and keeps their accounts. A switched-on transport with an unreadable or missing key is not used (SMTP sends) and the card says why.

## What does the SMTP Settings card do?
- "Sending service" card at the top of `smtp_settings.php`: the select (SMTP first), each driver's fields in a `data-mail-transport` pane (`pg_mail_transport_settings_script()` shows the chosen one), a link to the provider's portal, "Save and send a test" (the same test as the System card; the notice names the service).
- Every save of the screen runs `pg_mail_transport_settings_save()` first; its pure `pg_mail_transport_settings_decide()` marks a required field left empty (nothing is saved then), writes only a changed or newly chosen account, keeps a stored secret when the box is empty, refuses a write without an encryption key. The audit line records the choice and the options, never the secret.

## How was it verified?
- `tests/mail_transports_test.php`: each driver's request from a fixed message, its answers, the message read from PHPMailer, the permanent statuses, the card's decisions. No provider was called; the card was not run in a browser.
