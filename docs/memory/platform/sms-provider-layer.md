---
name: sms-provider-layer
description: SMS layer (PR #600): pg_sms_send() rules and kinds, account in connect_apps sms_<key> (no schema), demo sites, the account check on save, test message
---

## What sends an SMS?
- `pg_sms_send($to, $text, $kind)` (`includes/fn/sms.php`) returns `ok, id, code, error, retry, uncertain`. Before any account is read it refuses, in `pg_sms_check()` (shared with the queue), an unknown kind, `marketing` without sender identity (`sms-marketing-kind.md`), an unreadable number, an empty text or one over `pg_sms_max_length()` (1000); then a demonstration site (`pg_demo()`, like `email()`).
- `pg_sms_text()`: CRLF/CR → `\n`, tab → space, other control characters dropped, non-UTF-8 → empty.
- `pg_sms_number()` → E.164 digits without the plus; national forms (`05xx…`, `5xx…`, `90 5xx…`) are Turkish mobiles, other countries need `+`/`00`; under 90 only `5xx` (a `0` after `+90` is dropped).
- A stored number goes back into `pg_sms_send()` as `pg_sms_dial($digits)` (`'+' . digits`), never bare: bare ten digits starting with 5 read as a Turkish mobile. Symptom (#858): Cuba's +53 5123 4567, queued as `5351234567`, went to `905351234567` from the queue run and from the settings test message; Belize, Panama too.
- It sends at once and waits: background job or an operator's button only; a request queues with `pg_sms_enqueue()` (`sms-outbox-queue.md`). `retry` true means sending again cannot deliver twice, `uncertain` that the provider may have it (`sms-netgsm-driver.md`).

## Where is the account and why there?
- `connect_apps` row `sms_<key>`, as the carriers' API apps (`carrier_api.php`): user code / API key = `client_id`, password / API hash sealed with the site key (`pg_connect_app_save()`), `options.sender`, `enabled` = the switch. Chosen over a config blob: no migration step, a provider added later needs no column (as `commerce/marketplace-outbound-layer.md`).
- Active provider: the first enabled one with user, password and sender (`pg_sms_provider()`); the SMS card's switch turns the chosen one on and the others off, their accounts kept.
- Per provider (PR #3623), in `pg_sms_providers()`: field labels and `help`, `refused_fields` (code → field a refused check marks, else user), `user_sends` (Netgsm: the user code is a sender, `pg_sms_sender_approved()`). Sender rule: `sender_*` keys (`sms-twilio-driver.md`). A new driver gets its page and card block from these keys alone.
- Reset Encryption Key re-seals `connect_apps` with the rest (`encryption-key-reseal-registry.md`).

## What happens on save?
- Since CX11d2 the account is saved on its provider's page of Connections and the card holds only the switch and the Provider select: `ui/connections-sms-providers-page.md` (`pg_sms_account_decide()`, `pg_sms_switch_decide()`).
- An unchanged account is never rewritten or asked about, so an unreadable one does not block other saves; the password is never drawn back.
- `pg_sms_settings_refusal()`: demo site, schema not upgraded or no encryption key: a typed account is marked, nothing is asked.
- A changed account is asked about (`senders`): `refused` → mark the field `refused_fields` names (else the user code); unapproved sender → mark with the approved names, the approved spelling (or the user code itself) is stored; could not ask → saved unchecked with a notice.
- Test messages: 10 per clock hour per site (`waf_rate` `sms-test`, fails open).

## How was it verified?
- `tests/sms_test.php`; a sandbox run of `settings_pane.php` (marks, save / unchanged / disable / forget, test button). No provider was reached.

Related: docs/memory/platform/sms-netgsm-driver.md, docs/memory/platform/sms-and-iys-rules.md
