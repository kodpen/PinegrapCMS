---
name: sms-provider-layer
description: SMS layer (PR #600): pg_sms_send() rules and kinds, account in connect_apps sms_<key> (no schema), demo sites, the Communication › SMS card and its save decision, test message
---

## What sends an SMS?
- `pg_sms_send($to, $text, $kind)` (`includes/fn/sms.php`) returns `ok, id, code, error, retry, uncertain`. Before any account is read it refuses, in `pg_sms_check()` (shared with the queue), an unknown kind, a commercial one (`marketing`, until İYS consent records exist), an unreadable number, an empty text or one over `pg_sms_max_length()` (1000); then a demonstration site (`pg_demo()`, like `email()`).
- `pg_sms_text()`: CRLF/CR → `\n`, tab → space, other control characters dropped, non-UTF-8 → empty.
- `pg_sms_number()` → E.164 digits without the plus; national forms (`05xx…`, `5xx…`, `90 5xx…`) are Turkish mobiles, other countries need `+`/`00`; under 90 only `5xx` (a `0` after `+90` is dropped).
- A stored number goes back into `pg_sms_send()` as `pg_sms_dial($digits)` (`'+' . digits`), never bare: bare ten digits starting with 5 read as a Turkish mobile. Symptom (#858): Cuba's +53 5123 4567, queued as `5351234567`, went to `905351234567` from the queue run and from the settings test message; Belize +501…, Panama +507… too.
- It sends at once and waits: background job or an operator's button only; a request queues with `pg_sms_enqueue()` (`sms-outbox-queue.md`). `retry` true means sending again cannot deliver twice, `uncertain` that the provider may have it (`sms-netgsm-driver.md`).

## Where is the account and why there?
- `connect_apps` row `sms_<key>`, as the carriers' API apps (`carrier_api.php`): user code / API key = `client_id`, password / API hash sealed with the site key (`pg_connect_app_save()`), `options.sender`, `enabled` = the switch. Chosen over a config blob: no migration step, a provider added later needs no column (as `commerce/marketplace-outbound-layer.md`).
- Active provider: the first enabled one with user, password and sender (`pg_sms_provider()`); saving one switches the others off.
- Per provider (PR #3623), in `pg_sms_providers()`: field labels and `help`, `refused_fields` (code → field a refused check marks, else user), `user_sends` (Netgsm: the user code is a sender, `pg_sms_sender_approved()`). Every provider's fields sit in a `data-sms-provider` pane; `pg_sms_settings_script()` shows the select's one, so a new driver needs no script.
- Reset Encryption Key re-encrypts card numbers only, not `connect_apps`: the stored password then reads empty and the card warns.

## What does the card do on save?
- `pg_sms_settings_decide()` (pure, tested) picks `none` / `save` / `disable` / `forget`. An unchanged account is never rewritten or asked about, so an unreadable one does not block chat or campaign saves. Off writes `enabled = 0` alone (no sealing needed); off + empty user code deletes the row; the password is never drawn back.
- `pg_sms_settings_refusal()`: demo site, schema not upgraded or no encryption key: switching on is marked, nothing is asked.
- `pg_sms_settings_check()` runs in `contact.save.php` before MailChimp's checks (they may create a store a refusal here could not undo). A changed or newly switched-on account is asked about (`senders`): `refused` → mark the field `refused_fields` names (else the user code); unapproved sender → mark with the approved names, the approved spelling (or the user code itself) is stored; could not ask → saved unchecked with a notice.
- "Save and send a test message" is the pane's only named submit, so Enter presses it: an empty Test Number is a plain save, a wrong one is marked; 10 tests per clock hour per site (`waf_rate` `sms-test`, fails open).

## How was it verified?
- `tests/sms_test.php`; a sandbox run of `settings_pane.php` (marks, save / unchanged / disable / forget, test button). No provider was reached.

Related: docs/memory/platform/sms-netgsm-driver.md, docs/memory/platform/sms-outbox-queue.md, docs/memory/plans/sms-and-iys.md
