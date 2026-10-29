---
name: decisions-made-waf
description: Decisions list, firewall part: thresholds stay, a concurrent refusal is no offence, rate bans off the ladder, global limit before the session, no lock release, nothing served to a ban.
---

## Which firewall counting rules are decided?
- Owner, 2026-10-27 (issue #1989, answer "önerilerini destekliyorum" to option (b)): the thresholds stay as they are (30 requests a minute on sensitive scripts, 5 offences in ten minutes, a 60-minute base ban, a rate refusal folded to one offence per subject and minute). Reason: two campaign days with the chat open turned a legitimate address's 60-minute ban into 240 minutes, and an office behind one NAT address trips the concurrent cap.
- So the fourth simultaneous request (`waf_handle_inflight()`, rule `concurrent`) is refused with 429 and logged but registers no offence, and a ban brought by a rate refusal (`waf_auto_ban_climbs()` false for category `rate`) is always the base length and leaves the ladder's rung alone; signature hits, attack tools and wrong keys still climb (#2201, `waf/waf-ban-ladder.md`). Not a missing offence or a missing escalation.

## Does the global limit's signed-in exemption stay as it is?
- Owner, 2026-10-27 (issue #1989, "senin önerin kabul"): yes. `waf_handle_rate_global()` runs at the router stage, before the session, and latches, so its `waf_user_is_authenticated()` exemption reaches only directly requested scripts (`api.php`, `save_region_content.php`, the panel); a routed page or file is counted whoever asks, and a signed-in designer hard-reloading a media-heavy page can meet the 300/min wall (429 until the clock minute ends, no offence, no ban). Confirmed by three independent readers. Reason for leaving it: sparing sub-resource fetches would remove the only per-address cap on file fetches (`get_file.php` is counted nowhere else) for any client claiming `Sec-Fetch-Dest: image`, and with it the backstop `waf/waf-subresource-sign-in-fetches.md` relies on, the global limit still counting such a fetch of a sign-in screen; and a staff-address exemption would uncap a whole office address, anonymous visitors included, for a day. The comments, the settings text and the memory say so instead (#2257). Not a missing exemption.

## Is there a release action for the sign-in throttle's address lock?
- Owner, 2026-10-27 (issue #1989, asked in #2238, recorded in #2276): no; Unlock Sign-in on `edit_user.php` (`pg_login_unlock()`) clears the account subjects only. Reason: Allow on a `login-lock` row of the firewall log's suspects list, or an allow-list entry, already lifts the lock at once, since an allow-listed address is not counted by the throttle (#2187, `waf/waf-login-throttle.md`); and the lock needs 50 failures in 15 minutes and ends after 60. Not a missing release button.

## Is anything served to a banned address?
- Owner, 2026-10-27 (issue #1989, "Q3 gerek yok"): no script, token or signed link or not; the IP list and the ban shield stay without exceptions. Reason: an exception is a way past the ban for whoever holds it. The rescue runs on the server (`waf_unban.php`) and Settings refuses a block entry covering the operator's own address (#2187, `waf/waf-self-rescue.md`). Not a missing rescue link.
