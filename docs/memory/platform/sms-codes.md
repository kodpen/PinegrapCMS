---
name: sms-codes
description: One-time codes by SMS (includes/fn/sms_codes.php): session hash bound to purpose, subject and number, ten minutes, five guesses, queue reference and expires_at per send
module: sms_codes
---

## What is built?
- `includes/fn/sms_codes.php`, plan step 3b: the shared code (pure, `tests/sms_codes_test.php`), the cash-on-delivery code (#2669, decided in #2670) and the member sign-in code (decided in #2672).
- Purposes `cod` and `signin` (`pg_sms_code_purposes()`); six digits (`pg_sms_code_new()`), ten minutes (`pg_sms_code_lifetime()`).

## Which orders need the COD code?
- `pg_sms_cod_applies()`: payment method `Offline Payment`, not put on the ERP account (`On Account`, `erp_on_account`), while `pg_sms_code_on('cod')`: the SMS Messages card's `cod_code` event (kind `otp`, fields `store`, `code`, off by default) and `pg_sms_ready()`. There is no COD payment method; a real one would be its own plan. Marketplace and ShipWorks orders never pass checkout.
- `submit_order()` checks it after the pre-save hook, only when nothing else stops the order (a right code is not used up by a submit that comes back), against the order's stored `billing_phone_number`: not a mobile marks `billing_phone_number`, the rest `sms_code` (`pg_sms_code_verify()`, `pg_sms_code_sentence()`).

## Where does the customer ask for it?
- `pg_sms_cod_field_html()`: code field and "Send the code", shown by its own script while `Offline Payment` is chosen (radio or the hidden single method). Classic order preview and express order in the payment methods; custom layouts and the designer order preview through `$system`; the designer express order after both payment sections (a second copy removes itself).
- The button posts `sms_code.php` (`purpose=cod`, token, the form's `billing_phone_number` if any, else the order's): JSON `ok, error, message`; token 403 (`pg_sms_code_token_ok()`), only the session's `incomplete` order, `off` 409, `number` 400, `limit` 429, `send` 503.
- Sends limited by firewall buckets (`pg_sms_code_limits()`): 3 per subject in 10 min, 5 per number and 10 per address an hour; a refused send counts.

## Who gets a sign-in code?
- `pg_sms_signin_offered()`: role 3 with a factor (`pg_mfa_has_factor()`), a mobile number on the linked contact (`user.user_contact` → `contacts.mobile_phone`), event `signin_code` on. Staff (0-2) never; the code is no factor of its own (`pg_mfa_has_factor()`, `/auth/login`, ShipWorks, account page proofs, recovery unchanged). `mfa.php` verify: `sms_send` (PRG, notice in the session) and `sms_verify`, which shares `pg_mfa_attempt_blocked()` and passes as the app code does.

## How is a code kept and checked?
- `pg_sms_code_keep(&$store, $purpose, $subject, $number, $code, $now)`: SHA-256 of purpose, subject (`order:12`, `user:5`), E.164 number and code, never the code; one per purpose, a newer replaces the older. Refuses an unknown purpose, an empty subject or number.
- `pg_sms_code_check()`: `''` accepted (used up), `code_wrong`, `code_expired` (none, too old, kept in the future, fifth wrong guess, another purpose, subject or number). A check for something else leaves the code as it is, so a changed billing phone needs a new code.
- Kept in `$_SESSION['software']['sms_code'][<purpose>]` by `pg_sms_code_send()`.
- Same shape as the e-mailed passkey code (`core/passkey-without-totp.md`), kept apart because it binds a number and has no account for a guest's order.

## How does it reach the queue?
- `pg_sms_code_queue_options($purpose, $subject, $now, $nonce)`: a reference per send (`code:<purpose>:<subject>:<time>:<nonce>`, the queue's characters only, at most 64), since the queue dedupes a reference and a code sent again is a new message; `expires_at` at the code's end, so a send the queue could not make in time is dropped (decided in #2432: the normal SMS route, kind `otp`).

Related: docs/memory/platform/sms-outbox-queue.md, docs/memory/platform/sms-order-messages.md, docs/memory/plans/sms-and-iys.md
