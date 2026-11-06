---
name: push-disabled-account
description: The device push queue drops a disabled or deleted account's queued rows and browsers instead of waking them (pg_push_account_wakeable())
---

## Who does the push queue wake?
- Only an account that exists and is not disabled (`user.user_disabled_at = 0`). `pg_push_queue_run()` (`includes/push.php`) asks `pg_push_account_wakeable(pg_push_account($user_id))` for each person before reading their rows; for a disabled or deleted account it deletes their `push_queue` rows and every `push_subscriptions` row of the account, and wakes nothing. Every source (notification, chat, ws) passes there.
- `pg_push_account_wakeable()` is pure: a row read before the column existed counts as enabled. `tests/push_disabled_account_test.php`.

## Why was it needed?
- Disabling an account revokes its tokens, and `pg_auth_token_revoke_user()` deletes only the subscriptions bound to those tokens' selectors (`device-limit-and-session-sign-out.md`). A browser that subscribed in a session without "remember me" stored an empty `auth_selector`, so its subscription survived. `pg_push_enqueue_notification()` still queued that account (the bell's ladder reads only role and rights), and the device was woken on every new order or form; the worker's `push_pending` call was refused, so the phone showed the worker's generic banner for a site the person can no longer open.
