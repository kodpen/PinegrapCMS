---
name: security-settings-tidy
description: Plan (owner 2026-11-21): Firewall and HTTPS and Accounts and sign-in as folded rows, no popover or plain radio, both logs in one row, every limit on one address shown in one Limits row
priority: 8
---

## What is asked?
- Owner (2026-11-21): fix the settings screens' contradictions, gather spread settings, make old screens modern. Item 4 of `plans/settings-tidy-census-order.md` (census K8).

## What contradicts today? (development 6169b52410)
- (S1) Eleven popovers hide fields while their switch is off: in `includes/settings/firewall.php` Enable Firewall, Rate Limiting, Automatic Temporary Bans, Block Unknown Bots, Send Security Headers, Plain-text Block Log; in Accounts the sign-in throttle (`prep.php:1954-1990`), Limit devices, the three sign-in providers, the expiration warning mail.
- (S2) The logs are split: the firewall log's retention and size sit in Enable Firewall's popover, the plain-text block log and its fail2ban files in Send Security Headers' popover inside HTTPS and Security Headers (`firewall.php:600-656`).
- (S3) Three plain radio groups where `pg-seg` is the rule: firewall Mode, Country Restriction, Content Security Policy.
- (S4) The live Active Automatic Bans table and its Release buttons sit in IP Lists (`firewall.php:343-365`).
- (S5) Limits on one address are spread: per-minute limits and bans in Firewall, failed sign-ins in Session & Password, form submissions, CAPTCHA and StopForumSpam in Spam Protection; both help lines send the reader to "the allowed list" with no link (census K8).
- (S6) Secure Mode is the last thing on the pane, under HSTS that says it waits for Secure Mode.
- (S7) Session & Password holds Allow Mass Deletion, a deletion gate, beside passwords; each sign-in provider's popover holds only a state line; Title Case labels starting "Enable" or "Allow".
- (S8, census K4) SEO › AI Crawlers' "Allow AI training" writes only robots.txt: the firewall's built-in list still refuses GPTBot and the other training crawlers unless they are in Allowed Bots (`visitors/ai-crawlers-llms-txt.md`).

## What does the plan settle itself?
- Both panes become folded rows (`pg_settings_fold()`), as Features and Visitors and privacy; a switch's fields sit under it, always drawn; every switch is `pg-switch`, the three radio groups `pg-seg`.
- The rows and their order: `plans/security-settings-tidy-screens.md` (Firewall and HTTPS, Secure Mode first) and `…-screens-accounts.md`; Allow Mass Deletion and the throttle get rows of their own.
- "Logs" holds both logs: the firewall log's retention and size, "Open the firewall log", the plain-text block log with its files under a `details`.
- The bans become a state line in "Addresses" ("2 addresses banned now") with "See the bans" opening a drawer of today's table and Release.
- Each limit stays in its pane (a category writes only its own columns, `ui/settings-card-save-rules.md`); the Firewall's "Limits" row shows the Accounts limits as value rows with Change, and every "allowed list" line links `#settings/firewall/pgset-iplists`.
- Each provider becomes a [Connect row]: its pill, its sentence, the switch, "Set up in Connections".

## What did the owner decide?
- (S8) Owner (2026-11-21, poll 216, `workflow/decisions-made-product-97.md`): with "Allow AI training" on, the firewall takes the training crawlers out of the bad-bot match only; rate limits, bans and attack rules still apply; no new field. Step SE1b.

## What binds it?
- Field names, ids, columns and saves do not change; card ids and anchors keep working (`pg_settings_legacy_anchors()`); one Save a pane; no schema change.
- The architect agreed the plan (agent channel, message 11489). `firewall.php` and `security.php` take one PR at a time each.

Related: docs/memory/plans/security-settings-tidy-steps.md, docs/memory/plans/security-settings-tidy-screens.md, docs/memory/plans/security-settings-tidy-screens-accounts.md, docs/memory/plans/settings-tidy-census-order.md
