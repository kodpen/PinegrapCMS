---
name: upgrade-automation-and-testing
description: Cron/automated upgrade entry points (AUTOMATED_UPGRADE_SECRET), admin-session behavior, and where the upgrade system test protocol lives.
---

## How is an upgrade run from cron?
- `php pinegrap/install/index.php automated_upgrade` (needs no key), or `install/index.php?automated_upgrade=true&secret=<AUTOMATED_UPGRADE_SECRET>`.
- The constant lives in `data/config.php`, is opt-in, at least 16 characters; if absent the URL path is closed. Both run in one request and answer plain text.
- A wrong key gives 403 plus an attempt counter.

## What does an admin session get?
- An administrator session (redirect from `software_update.php`) needs no key and lands on the screen: the loop starts by itself; if no version is pending it returns to `welcome.php`.

## How is the upgrade system tested?
- Test protocol: `docs/_plan_upgrade_sistemi.md` "Test protokolü".
- A fake-MySQL harness (old/new equivalence, double run, mid-run interruption) is in the development records: the old code failed in 26 of 41 versions on a double run, at 84 interruption points; the new code failed in none.
- Every new version also needs the bridge test (previous released DB + new files): see docs/memory/release/upgrade-bridge-rule.md
