---
name: waf-self-rescue
description: CLI waf_unban.php lifts a ban from the server, Settings refuses a block entry covering your own address, the sign-in lock spares allow-listed addresses (#2187)
module: waf_rescue
---

## Why does it exist?
- #1989: a banned operator could not reach Release, Allow or the settings save (panel screens behind the ban shield and the IP list), and no script touched `banned_ip_addresses` or the mirror. The owner decided no script is ever served to a banned address, token or not (owner, 2026-10-27, `workflow/decisions-made-waf.md`), so the rescue runs on the server.

## How is a ban lifted from the server?
- `php waf_unban.php <address>` in the software directory removes every block row covering the address, automatic or manual (a manual range covering one's own address is a way to get shut out); `<range>` removes the row naming that range (compared as packed base and prefix, `pg_waf_unban_entry_key()`); `--all` removes every automatic ban. Wrong arguments print the usage and exit 2.
- `pg_waf_unban()` (`includes/fn/waf_rescue.php`) deletes the rows, clears their offence buckets (`waf_offence_clear()`, for the given address too), reloads `waf_ip_lists(true)` and rewrites the shield (`waf_ban_shield_refresh(true)`); the script prints each removed row and writes a `log_activity` line under the user name `waf_unban.php`.
- The script answers 404 to anything but `PHP_SAPI === 'cli'`, checked before `init.php` loads; unlike `waf_ranges_job.php` it is not reachable through the general job (`PG_CRON_DISPATCH`) or by a manager.
- The single-ban e-mail names the command with the banned subject (`pg_waf_unban_command()`, `waf-ban-notice.md`).

## What does Settings › Firewall refuse?
- A save whose block entries cover `waf_client_ip()` while no allow entry as submitted does (`pg_waf_own_address_blocks()`): "Nothing was saved", the covering entries named, checked before anything is written, like the country guard. Matching is `waf_ip_matches()`, so wildcards, CIDR, `*.*.*.*` (covers IPv6 too) and `::/0` count.

## Does the sign-in lock honour the allow list?
- Yes: `pg_login_throttle_address_subject()` returns no address subject for an address the allow list covers (`waf_ip_lists()`, read with the firewall off too); the account subject still counts (`waf-login-throttle.md`).

## How is it tested?
- `tests/waf_self_rescue_test.php`: own-address blocks (IPv4, IPv6, allow wins, rejects), argument parsing, row choice per mode, the command, the throttle subject, the e-mail line.
