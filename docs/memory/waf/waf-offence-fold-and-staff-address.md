---
name: waf-offence-fold-and-staff-address
description: A rate refusal registers one offence per subject and minute (the burst flag of waf_register_offence()), a staff sign-in's address is never auto-banned (would-ban), Release and Allow forget offences
---

## What changed and why?
- #1989: five refusals in one second banned a visitor's address, because `waf_handle_rate_sensitive()` registered an offence per refused request and the threshold is 5 offences in a ten-minute slot. A limit refuses the request after its allowance and every one after it in the minute, so any burst past the limit was a ban within seconds: a page full of requests, an office signing in together, a reset wave.

## How is a rate refusal counted now?
- `waf_register_offence($ip, $score, $blocking, $burst)`: with `$burst` true the refusals of one clock minute from one subject are one offence. The `of` bucket (60 s) in `waf_rate` counts the minute's refusals; `waf_offence_is_repeat($burst, $minute_hits)` says whether this one was already counted (hits above 1; 0, a bucket that could not be written, still counts). Then the `o` bucket (threshold, 600 s) as before, so a ban needs refusals in six separate minutes of a slot.
- Who passes the flag: `waf_handle_rate_sensitive()`, `waf_handle_rate_api()`, `waf_handle_inflight()` (rule `concurrent`) and `pg_password_reset_deny()` (`rate-password-reset`). Not folded: the signature scan, attack tools, spoofed crawlers, a wrong API or webhook key, a wrong prototype password, a guest room's refusals: each is its own act. `tests/waf_offence_test.php` holds the callers to this.

## Which address is never auto-banned?
- One with a staff session (`waf_user_is_staff()`) or a staff sign-in from the exact address within the last day (`waf_address_has_staff_sign_in()`, `waf_staff_address_window()` = 86400 s): `auth_tokens` keeps a row per sign-in with `ip_address` as the firewall resolved it, `created_at` and `last_used_at`; its users are checked with `waf_user_id_is_staff()` (the per-account half of `waf_user_is_staff()`), so a member's sign-in spares nothing. Asked only once the threshold is reached, because the table has no index on the address.
- Instead of the ban a `would-ban` row is logged (rule `auto-ban`, category `ban`, matched `<subject> staff address`; badge "Would ban" on the log screen). The limits still refuse the address with 429; what they cannot do is lock the operator out of the panel that lifts bans. Someone behind the operator's office address is rate-limited, not banned; the operator bans by hand.
- Not matched: another address of a staff member's IPv6 /64 (the session covers the browser that holds it).

## What do Release and Allow forget?
- `waf_offence_clear($stored)` deletes the `o` and `of` buckets of the stored string and, for an address, of its subject (`waf_ip_subject()`). Before, the bucket kept its count until the slot rolled over and the next refusal placed the ban again: the Release button looked broken.

Related: docs/memory/waf/waf-ban-ladder.md, docs/memory/waf/waf-inflight-request-cap.md, docs/memory/plans/waf-legit-traffic-auto-ban.md
