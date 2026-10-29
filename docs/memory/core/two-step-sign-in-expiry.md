---
name: two-step-sign-in-expiry
description: What the second step (mfa.php) and the device-limit question (device_limit.php) do when the sign-in waited past its 10 minutes: the reason on the origin sign-in screen, account name and send_to kept
module: mfa
---

## What happens when the second step runs out?
- The pending record (`pg_mfa_gate()`) lives `pg_mfa_pending_lifetime()`, 600 s. The setup key itself is offered again for 30 min (`pg_mfa_begin_setup()`), so after signing in again the person sees the key they already scanned.
- `mfa.php` first asks `pg_sign_in_step_state($record, $lifetime, $now)`: `live`, `expired` (well formed, older than the lifetime) or `missing` (none or malformed).
- `expired`, browser not signed in: the record is cleared, the origin screen's liveform gets `mark_error('', 'Your sign-in waited more than 10 minutes for its second step. Please sign in again.')` and the account name, and the browser goes back to that screen with `send_to` (`pg_mfa_origin_back_path()`). Forms: `login`/`email` for `index.php` and `registration_entrance.php`, `membership_entrance`/`u` for `membership_entrance.php` (`pg_mfa_origin_form()`). A typed code in that POST is not checked.
- `missing`, or a browser already signed in: the bare redirect to the panel root as before (a liveform error would hold a signed-in browser on the sign-in screen instead of sending it home).
- `passkey.php` keeps reading `pg_mfa_pending()` and answers its own JSON sentence.

## And the device-limit question?
- `device_limit.php` keeps its own 600 s record; when it ran out it puts "…waited more than 10 minutes for your answer…" and the account name on the `login` liveform before its redirect to `registration_entrance.php`. Cancel's target is unchanged.

Related: docs/memory/core/two-step-sign-in.md, docs/memory/ui/liveform-notices-trap.md
