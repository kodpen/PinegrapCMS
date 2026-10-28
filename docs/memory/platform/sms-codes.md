---
name: sms-codes
description: One-time codes by SMS (includes/fn/sms_codes.php): session hash bound to purpose, subject and number, ten minutes, five guesses, queue reference and expires_at per send
module: sms_codes
---

## What is built?
- `includes/fn/sms_codes.php`, the shared part of plan step 3b (COD confirmation code, member sign-in code), pure and tested (`tests/sms_codes_test.php`). Nothing calls it yet: which orders get the COD code and what the member code signs in to wait on decisions (`plans/sms-and-iys.md`).
- Purposes `cod` and `signin` (`pg_sms_code_purposes()`); six digits (`pg_sms_code_new()`), ten minutes (`pg_sms_code_lifetime()`).

## How is a code kept and checked?
- `pg_sms_code_keep(&$store, $purpose, $subject, $number, $code, $now)`: SHA-256 of purpose, subject (`order:12`, `user:5`), E.164 number and code, never the code; one per purpose, a newer replaces the older. Refuses an unknown purpose, an empty subject or number.
- `pg_sms_code_check()`: `''` accepted (used up), `code_wrong`, `code_expired` (none, too old, kept in the future, fifth wrong guess, another purpose, subject or number). A check for something else leaves the code as it is, so a changed billing phone needs a new code.
- Same shape as the e-mailed passkey code (`core/passkey-without-totp.md`), kept apart because it binds a number and has no account for a guest's order.

## How does it reach the queue?
- `pg_sms_code_queue_options($purpose, $subject, $now, $nonce)`: a reference per send (`code:<purpose>:<subject>:<time>:<nonce>`, the queue's characters only, at most 64), since the queue dedupes a reference and a code sent again is a new message; `expires_at` at the code's end, so a send the queue could not make in time is dropped (decided in #2432: the normal SMS route, kind `otp`).

Related: docs/memory/platform/sms-outbox-queue.md, docs/memory/platform/sms-order-messages.md, docs/memory/plans/sms-and-iys.md
