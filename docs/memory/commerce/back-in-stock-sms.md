---
name: back-in-stock-sms
description: Back-in-stock notice by SMS (PR #PRNUM): stock_back event and kind stock, when a request takes a number, number-only requests, texting after the claim, the item view's phone field, privacy by number
---

## When does a request take a mobile number?
- Only while `pg_stock_notify_sms_on()`: the SMS Messages card's `stock_back` event is on and an SMS account is set up (`pg_sms_ready()`). Otherwise `phone` is not read and every request is by e-mail as before.
- Then a number alone is enough: `pg_stock_notify_request($id, $email, $user_id, $phone, &$by)`. The number is kept as E.164 digits (`pg_stock_notify_phone()` = `pg_sms_number()`), so a landline or an unreadable number answers `phone` ("Please enter a valid mobile phone number."). `pg_stock_notify_contact_error()` checks what was typed: a wrong address is `email` even with a right number.
- `already` matches the address, the number or the account; `limit` (50 waiting) counts the address and the number, the larger one. `$by` (`email`, `sms`, `both`) picks the sentence of `added`.
- A signed-in visitor always has the account's address, so their notice goes by both.

## How is the notice texted?
- `pg_stock_notify_send()` claims the row first (as for the mail), then `pg_sms_stock_back($row_id, $phone, $name, $url)` (`includes/fn/sms_messages.php`): `pg_sms_message_queue('stock_back', pg_sms_dial($phone), …)`, reference `stock:<row id>`, expiry two days, once per number in an announcement. It is only queued; the general job sends it.
- Template fields `^^store^^`, `^^product^^` (`short_description`, else the SKU), `^^url^^` (`pg_stock_notify_url()`, empty without a detail page). Built-in: "^^store^^: ^^product^^ is back in stock. ^^url^^".
- Kind `stock` in `pg_sms_kinds()`, not commercial: the visitor asked for this one message, so no consent record or İYS check (`core/consent-log-writers.md` records e-mail marketing only).
- The event switched off after a request was kept: the row is still claimed and mailed when it has an address; a number-only row then reaches nobody.

## What does the item view draw?
- A field bound to `stock_notify_phone` (designer: Field binding, also in the item view starter beside the address): `type="tel" name="phone" autocomplete="tel" inputmode="tel" maxlength="30"`, never required, same form and `can_notify` rule as the address (`designer/catalog-stock-notify-form.md`).
- Context `stock_notify_sms`: while false the number field is dropped; while true the address field loses `required`, since a number alone is enough.

## What about personal data?
- Export: section `stock_notify` also by the person's mobile numbers (the same list the `sms` section uses, now built once before both). Erasure: `stock_notify` found by `phone` too (`pg_privacy_erase_tables()`).

## How was it tested?
- `tests/stock_notify_test.php` (number, typed errors, status with a number, sentences, texting after the claim, kind and event, privacy), `tests/stock_notify_item_view_test.php` (phone field, dropped while off), `tests/sms_messages_test.php`, `tests/privacy_erase_test.php`. Not run on a sandbox: Netgsm cannot be reached from a session; the queue path is the one #1839 uses.
