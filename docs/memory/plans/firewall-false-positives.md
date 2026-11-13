---
name: firewall-false-positives
description: Plan (owner 2026-11-13, #9125): the firewall refuses honest people (page beacons on the sign-in allowance, prose taken for attacks, in-app browsers as bots, unlisted proxy); steps FP1-FP7
---

## What is asked?
- Owner (2026-11-13, #9125): a site on 2026.4.10 kept refusing its own administrator; "bu tarz hatalı pozitifler beni yoruyor", and a whole check of the firewall. The findings and their evidence (honest values run through the rules, real user agents through `waf_classify_bot()`) are on #9125.
- Records: `workflow/decisions-made-waf-2.md` (the fixes ship with 2026.11.0; Monitor mode and automatic bans). `workflow/decisions-made-waf.md` stays: no threshold moves here.

## Which rules does every step keep?
- `waf/README.md`. A change to who is refused comes with a test that is red first. No schema change; a step that needs one asks first.
- Never switch a check off: narrow it until the honest case passes and the attack is still refused.

## Which steps?
- FP1 (S, before 2026.11.0 ships) The site's own beacons leave the sign-in allowance. `page_view.php` (speed figures of every page, the visit of a cached page, search and experiment clicks) and `consent_answer.php` are POSTs, so `waf_sensitive_request_counts()` counts them on the 30 a minute allowance and the cap on open requests: a shared address spends both by reading pages.
  - A named list of beacon scripts skips the sensitive limit and the open-request cap and is counted on a bucket of its own; over it the answer is the script's own empty 204, no offence, one log row a minute. The global limit still counts them. First a census of the scripts a site page posts to without a click.
- FP2 (M) Honest sentences pass the signature scan. New `tests/waf_rules_corpus_test.php`: honest values (Turkish and English prose, product names, addresses, searches; the table on #9125 is the start) must score under the Medium threshold, real attack payloads at or over it. Then rule by rule (the twelve named on #9125) until both hold. Thresholds and the other rules' scores stay.
- FP3 (S) In-app browsers are people. `bytedance` leaves `waf_bad_bots()` (`bytespider` stays); `pinterest` and `naver` match their crawlers' own tokens only; Better Uptime's entry matches its real user agent. Test: the in-app user agents of TikTok, Pinterest, Naver, Instagram, Facebook, LinkedIn, Snapchat and Telegram classify `human`.
- FP4 (S) Settings tells and acts. The Firewall save rewrites the ban mirror at once (`waf_ip_lists(true)`, `waf_ban_shield_refresh(true)`); the screen lists active automatic bans with Release; the Sensitivity field says it moves only the signature threshold, not bans or rate limits.
- FP5 (S) The block page says which kind of refusal: a ban with its end (also from `waf_run()`, rule `ip-list`), too many requests, the country, the request's content, an automated client. No rule name, no matched text. The texts are shown to the owner by the architect before the step is claimed.
- FP6 (M) A proxy on a public address that is not listed. The settings readout warns when the operator's own request carries a forwarding header from such a peer and offers "Add this proxy to Trusted Proxies". The same finding from a signed-in staff request (a visitor cannot forge it) is kept in a state file under `data/temp` and shown on the dashboard's maintenance card; while it stands, that peer is not counted or banned, as a private peer already is not.
- FP7 (S) Monitor mode does not enforce automatic bans (owner, 2026-11-13): `waf_run()`'s list branch and the mirror's block lines keep only hand-written entries while the mode is Monitor; the rows stay and apply again in Block mode. `waf/waf-kill-switch-and-monitor-mode.md` and the README's key rule are corrected in the same PR.
- Close-out: each step corrects its `waf/` memory file; this plan is deleted.

## In which order?
- FP1 first. FP3, FP4 and FP7 side by side; FP2 any time; FP5 after its texts are approved; FP6 last. Claim as `[plan:firewall-false-positives] <step> — <topic>`.
