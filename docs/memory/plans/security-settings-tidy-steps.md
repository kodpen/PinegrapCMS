---
name: security-settings-tidy-steps
description: Plan security-settings-tidy, steps SE1 to SE5: Firewall and HTTPS as folded rows in two steps, Accounts and sign-in as folded rows, the Limits row's overview, close-out
---

## Which steps are there?
- SE1 (M) first: Firewall and HTTPS as folded rows (`pg_settings_fold()`) "Firewall", "Limits", "Bots", "Addresses", "Countries", "Trusted proxies", each with its state line; the popovers of Enable Firewall, Rate Limiting, Automatic Temporary Bans and Block Unknown Bots gone, their fields always drawn; Mode and Country Restriction as `pg-seg`. Fixes: S1 and S3 for these rows.
- In SE1 "Limits" (`pgset-limits`, new) takes Rate Limiting, Open at Once and Automatic Temporary Bans from the Firewall card; the bans table leaves IP Lists for a drawer (`pg_settings_drawer()`) opened by "See the bans" under a state line in "Addresses", its Release form unchanged. Fixes: S4.
- Tests of SE1: `tests/settings_card_ids_test.php` and `settings_card_names_test.php` with the ids kept; every field posts under its old name, `firewall.save.php` unchanged; a row an anchor names arrives open.

- SE1b (S) after SE1: with SEO's "Allow AI training" on, `waf.php` takes the training crawlers (`pg_ai_crawler_catalog()['train']`) out of the bad-bot match only (no good-bot privilege; rate limits, bans and attack rules apply); off, refused as today. No new field. Owner: poll 216 (`workflow/decisions-made-product-97.md`).
- In SE1b user fetchers and search indexers keep their own switch and range check; the Bots row's line says whether training crawlers pass; `waf/waf-ai-bots-ranges.md` and `visitors/ai-crawlers-llms-txt.md` corrected. Red test first: on, GPTBot passes the bot match and meets the rate limit; off, refused. Fixes: S8.

- SE2 (M) after SE1: the rows "HTTPS" (`pgset-headers`: Secure Mode and its offer first, then the headers, Refuse Framing, HSTS, Content Security Policy as `pg-seg`, the policy, "Policy reports") and "Logs" (`pgset-waf-logs`, new: Log Retention, Log Size Limit, "Open the firewall log", the plain-text block log with its files under a `details`). The fields' anchors (`waf_log_retention_days`, `waf_text_log`) open "Logs". Fixes: S2, S6, S1 and S3 for these rows.

- SE3 (M) after SE2: Accounts and sign-in as folded rows; "Sign-in attempts" (`pgset-throttle`, new) takes the throttle out of Session & Password, "Deleting many at once" (`pgset-deletion`, new) Allow Mass Deletion; the providers as [Connect row]; Limit devices and the expiration warning mail without popovers; every label in sentence case and without "Enable". Fixes: S1, S7.
- In SE3 the throttle's and the form limits' help link "the allowed list" to `#settings/firewall/pgset-iplists`; the moved fields' anchors open their new rows (`pg_settings_legacy_anchors()`).

- SE4 (S) after SE3 and DX2b (`plans/design-examples-patterns-mail.md`, "Value rows"): under the Firewall's own limits in "Limits", value rows for Sign-in attempts and Form submissions with today's values and Change. Values from one pure `pg_settings_limits_overview()`, red test first. Fixes: S5.

- SE5 (S) close-out: `ui/settings-firewall.md` and `ui/settings-accounts.md` written (rows, what moved, what stayed); `ui/look-settings-maintenance-security.md` and `waf/` files naming the old cards corrected; item 4 of `plans/settings-tidy-census-order.md` removed; this plan's files deleted.

## Which steps run side by side?
- None: every step enters `registry.php`; SE1, SE1b and SE2 enter `firewall.php`, SE3 `security.php` and `prep.php`, SE4 both panes.

## How is a step checked?
- A step copies its parts from the Design examples screen and writes no recipe of its own; it renders the pane at 1440 and 390 px before ready and writes "Screen check" in the PR. The firewall's save is tried with a fresh install's defaults (Monitor, no bans) and with a ban listed.

Related: docs/memory/plans/security-settings-tidy.md, docs/memory/plans/security-settings-tidy-screens.md, docs/memory/plans/security-settings-tidy-screens-accounts.md
