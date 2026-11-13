---
name: waf-settings-save-and-release
description: Settings › Firewall acts at once: the save rewrites the ban mirror from the saved row, IP Lists shows active automatic bans with Release, Sensitivity says it moves only the signature threshold
module: waf_rescue
---

## What does a Firewall save do to the ban mirror?
- `firewall.save.php` ends with `pg_waf_save_refresh_mirror()` (`includes/fn/waf_rescue.php`): `waf_config(null, false, true)`, `waf_ip_lists(true)`, `waf_ban_shield_refresh(true)`. A removed block entry, a switch to Monitor (whose mirror leaves automatic bans out) or a new proxy reaches the shield before the operator's next request, not within five minutes.
- Why the reload: `waf_config()` is cached for the request and `WAF_ENABLED` is a constant from init.php, so a rewrite in the save request wrote the old mode. The third argument of `waf_config()` reads the row again; `waf_config_reloaded()` then lets `waf_mode()` pass over the older constant.
- A refused save returns before it and writes nothing, the mirror included.

## Where are automatic bans released?
- On the firewall log screen and on Settings › Firewall › IP Lists, both through `pg_waf_release_auto_ban()`: deletes the `auto` row only (hand-written entries belong to Banned IP Addresses), rewrites the mirror, clears the offences (`waf_offence_clear()`); false when no row was found.
- IP Lists shows Active Automatic Bans: address linked to the filtered log, reason, end (relative, clock time on hover), Release; at most 25, built in `prep.php`.
- Each Release button submits the hidden form `pgset_waf_release` by its form attribute (forms cannot be nested); it posts to `view_waf_log.php` with `release_return=settings`, and `pg_waf_release_return()` sends it back to `settings_firewall.php#pgset-iplists` with the message in the `settings` liveform. Any other value goes back to the log.

## What does the Sensitivity field say?
- That it moves only the signature scan's threshold; the rate limits and automatic bans keep their own settings. The thresholds themselves are in `waf-score-calibration-and-bot-order.md`.

Related: docs/memory/waf/waf-ban-shield-mirror.md, docs/memory/waf/waf-offence-fold-and-staff-address.md
