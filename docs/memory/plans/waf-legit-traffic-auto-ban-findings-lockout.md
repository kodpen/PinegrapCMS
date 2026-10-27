---
name: waf-legit-traffic-auto-ban-findings-lockout
description: Review findings of 2026-10-26 for plans/waf-legit-traffic-auto-ban.md steps 2, 4 and 5: how staff lock themselves out, what the operator is not told, the recovery paths that do not exist
---

## Where do these come from?
- The same sweep as `waf-legit-traffic-auto-ban-findings-counting.md`; line numbers as of #2011's head.

## How do staff lock themselves out (steps 2 and 5)?
- Settings › Firewall accepts a block entry that covers the operator's own address (`includes/settings/firewall.save.php` ~339: a mistyped CIDR, an ISP's /8, `*.*.*.*` or `0.0.0.0/0` while testing): applied in every mode and by the shield, never expiring, and the Release button deletes only `auto` rows. Done in #2187: the save is refused when a block entry matches `waf_client_ip()` and no allow entry does (`waf/waf-self-rescue.md`).
- Staff's own actions register offences when the session is not recognised: a revoked device token (password changed elsewhere, device limit) ends the session on an XHR region save without a redirect, so the POST runs anonymous through the sensitive limit and the scan. `auth_tokens` is the table for the "recent staff sign-in" rule of step 2a (done, #2071): a row per sign-in with `ip_address = waf_client_ip()`, `user_id`, `created_at`, `last_used_at`; `log.log_ip` is `REMOTE_ADDR` (the edge behind Cloudflare) and `user` has no address column.
- The sign-in throttle's address lock ignores the allow list (`pg_login_throttle_subjects()`, includes/fn/auth.php ~4003-4030) and no screen releases it: 50 failures from a shared address lock everyone behind it, the allow-listed operator included, for an hour or more, with the firewall off too. The address subject is skipped for an allowed address (#2187); a release action beside the account unlock is still open (owner).
- The global limit's signed-in exemption never applies to routed requests: `waf_handle_rate_global()` runs in the router phase before the session and latches, so a signed-in designer hard-reloading a media-heavy page can hit the 300/min wall (429 until the clock minute ends, no offence, no ban). Confirmed by three independent readers 2026-10-27; left as it is by the owner, the texts corrected (#2257, `workflow/decisions-made-waf.md`).

## What was the operator not told (step 4)?
- Nothing notified anyone of an automatic ban, a run of false-positive bans by one rule raised no alarm, and no ban row linked to its events. Done in #2167: an e-mail per ban, one notice per rule's burst, ban rows linked to the filtered log (`waf/waf-ban-notice.md`). Still no activity log entry or status warning for a ban.

## Which recovery paths are missing (step 5)?
- None works from the banned address: Release, Allow and the settings save are panel screens behind the shield and the IP list; no script under `tools/` or `*_job.php` touches `banned_ip_addresses` or the mirror. Done in #2187: the CLI root script `waf_unban.php` (command line only, an address, a range or `--all`; rows, offence buckets and the mirror); no signed link (Q3).
