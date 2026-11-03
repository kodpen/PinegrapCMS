---
name: device-limit-and-session-sign-out
description: Which sessions the device limit signs out, what ends with a session (notifications), and the Sessions screen's confirmations and wording
module: auth
---

## Which sessions does "Sign out the others and continue" close?

`pg_device_limit_pick_evictions()` (pure, `includes/fn/auth.php`, test
`tests/device_limit_evictions_test.php`) gets the account's active tokens
newest first and `$keep` = limit − 1. Locked (`pinned`) tokens count toward
`$keep` but are never chosen; unlocked ones go oldest first until the total is
`$keep`. If too few are unlocked, it chooses **nothing**: no device is lost
for a sign-in that is refused anyway. `pg_device_limit_evictable()` runs the
query; `pg_device_limit_revoke_oldest()` revokes what it returns.
`device_limit.php` counts the same list for "the oldest N will be signed
out", and refuses at once with the "locked by the site owner" message when
the list is empty. Before #5977 the newest `limit − 1` rows were kept whatever
their lock, so a locked older session made the screen close nothing (or one
device) and then refuse.

## What ends with a session besides its token?

Its device notifications (`push_subscriptions.auth_selector`).
`pg_auth_token_revoke()` deletes them for one token; since #5978
`pg_auth_token_revoke_user()` (password change/reset/set, "Sign out all
sessions", "Sign out of all devices") reads the selectors it is about to delete
and deletes their subscriptions first; with `$respect_pin` a locked session
keeps its own. The selectors are listed, not joined, so the two columns'
collations never matter.

## Which Sessions screen buttons confirm?

Every row's Sign out carries `data-confirm-content` (#5979); on the "This
device" row the text says the panel session ends too, as the bulk "Sign out
all sessions" on the user screen does.

## How is the screen worded?

The operation is "lock" everywhere (button, badge, tooltip); Lock and Unlock
have their own tooltip. Headings are sentence case ("All sessions",
"Notification subscriptions" → "Bildirim abonelikleri"). `?user=<id>` heads
the table "Sessions of <name>" with the trail "All sessions › <name>"; an
unknown id adds no empty crumb and keeps "All sessions" (#5981).

## How are POST fields read?

A field sent as `name[]` reads as empty: `view_sessions.php` reads through
`$post_text()`, `account_security.php` and `device_limit.php` check
`is_scalar()` (#5980).
