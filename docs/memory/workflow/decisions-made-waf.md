---
name: decisions-made-waf
description: Decisions list, firewall part: the auto-ban thresholds stay, a concurrent refusal is no offence, a rate ban stays off the x4 ladder, the global limit counts routed requests before the session.
---

## Which firewall counting rules are decided?
- Owner, 2026-10-27 (plan `waf-legit-traffic-auto-ban` Q2, answer "önerilerini destekliyorum" to option (b)): the thresholds stay as they are (30 requests a minute on sensitive scripts, 5 offences in ten minutes, a 60-minute base ban, a rate refusal folded to one offence per subject and minute). Reason: two campaign days with the chat open turned a legitimate address's 60-minute ban into 240 minutes, and an office behind one NAT address trips the concurrent cap.
- So the fourth simultaneous request (`waf_handle_inflight()`, rule `concurrent`) is refused with 429 and logged but registers no offence, and a ban brought by a rate refusal (`waf_auto_ban_climbs()` false for category `rate`) is always the base length and leaves the ladder's rung alone; signature hits, attack tools and wrong keys still climb (#2201, `waf/waf-ban-ladder.md`). Not a missing offence or a missing escalation.

## Does the global limit's signed-in exemption stay as it is?
- Owner, 2026-10-27 (plan `waf-legit-traffic-auto-ban` Q5, "senin önerin kabul"): yes. `waf_handle_rate_global()` runs at the router stage, before the session, and latches, so its `waf_user_is_authenticated()` exemption reaches only directly requested scripts (`api.php`, `save_region_content.php`, the panel); a routed page or file is counted whoever asks, and a signed-in designer hard-reloading a media-heavy page can meet the 300/min wall for one clock minute (429, no offence, no ban). Confirmed by three independent readers. Reason for leaving it: sparing sub-resource fetches would remove the only per-address cap on file fetches with a forged `Sec-Fetch-Dest` (the backstop `waf/waf-subresource-sign-in-fetches.md` relies on), and a staff-address exemption would uncap a whole office address, anonymous visitors included, for a day. The comments, the settings text and the memory say so instead (#2257). Not a missing exemption.
