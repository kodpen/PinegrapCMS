---
name: waf-legit-traffic-auto-ban-findings-lockout
description: Review findings of 2026-10-26 for plans/waf-legit-traffic-auto-ban.md steps 2, 4 and 5: how staff lock themselves out, what the operator is not told, the recovery paths that do not exist
---

## Where do these come from?
- The same sweep as `waf-legit-traffic-auto-ban-findings-counting.md`; line numbers as of #2011's head.

## How do staff lock themselves out (steps 2 and 5)?
- Settings › Firewall accepts a block entry that covers the operator's own address (`includes/settings/firewall.save.php` ~339: a mistyped CIDR, an ISP's /8, `*.*.*.*` or `0.0.0.0/0` while testing): applied in every mode and by the shield, never expiring, and the Release button deletes only `auto` rows. Refuse the save when a block entry matches `waf_client_ip()` and no allow entry does, as the country guard does (step 5).
- Staff's own actions register offences when the session is not recognised: a revoked device token (password changed elsewhere, device limit) ends the session on an XHR region save without a redirect, so the POST runs anonymous through the sensitive limit and the scan. `auth_tokens` is the table for the "recent staff sign-in" rule of step 2: every sign-in writes a row with `ip_address = waf_client_ip()`, `user_id`, `created_at`, `last_used_at`; `log.log_ip` is `REMOTE_ADDR` (the edge behind Cloudflare) and `user` has no address column.
- The sign-in throttle's address lock ignores the allow list (`pg_login_throttle_subjects()`, includes/fn/auth.php ~4003-4030) and no screen releases it: 50 failures from a shared address lock everyone behind it, the allow-listed operator included, for an hour or more, with the firewall off too. Skip the address subject for an allowed address; a release action beside the account unlock (owner).
- The global limit's staff exemption never applies to routed requests: `waf_handle_rate_global()` runs in the router phase before the session and latches, so a signed-in designer previewing media-heavy pages can hit the 300/min wall (no offence, no ban). Say so in the comment or spare routed sub-resource fetches (low; owner).

## What is the operator not told (step 4)?
- Nothing notifies anyone of an automatic ban: no e-mail, no activity log entry, no status warning; the dashboard card shows a count. A run of false-positive bans (several addresses by one rule in an hour) raises no alarm (step 4 as planned; `pg_mail_enqueue()` from a `data/temp/` pending file drained after `waf_run('init')` or by the job).
- No link from a banned address to its events (`view_waf_log.php` ~627, 754; the settings badge): the operator retypes the address into the search box.

## Which recovery paths are missing (step 5)?
- None works from the banned address: Release, Allow and the settings save are panel screens behind the shield and the IP list; no script under `tools/` or `*_job.php` touches `banned_ip_addresses` or the mirror. Add a CLI root script `waf_unban.php` on the pattern of `waf_ranges_job.php` (init.php, refused unless `pg_cron_is_background_run()`, an address or `--all`, deletes `auto` rows, refreshes the mirror, logs) beside the signed one-time link (Q3).
