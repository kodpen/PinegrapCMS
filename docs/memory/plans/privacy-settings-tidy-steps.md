---
name: privacy-settings-tidy-steps
description: Plan privacy-settings-tidy, steps VP1 to VP4: the pane as folded rows, the "How long we keep data" row and the inventory's IP lines, the architect's two answers, close-out
---

## Which steps are there?
- VP1 (M) first: the pane as folded rows (`pg_settings_fold()`): "Visitor tracking", "Cookie consent", "Legal documents", "KVKK notice", "Accessibility statement", "Privacy centre", each with its state line. No popover: the switches' fields sit under them, always drawn. Every switch is `pg-switch`. Fixes: V4, V6, V3's drawing.
- In VP1 each card keeps its id inside its row; the KVKK fields leave the Legal documents popover for their own row (`pgset-kvkk`, new, in the registry with keywords); the fields table stays under a `details`.
- Tests of VP1: `tests/settings_card_ids_test.php` and `settings_card_names_test.php` with the ids kept; every field posts under its old name, `privacy.save.php` unchanged; a row an anchor names arrives open.

- VP2 (M) after VP1 and DX2b (`plans/design-examples-patterns-mail.md`, "Value rows"): the "How long we keep data" row (`pgset-retention`, new). The visitor periods and the daily summary move in from Visitor tracking; under them a value row a period set elsewhere, each its value today and Change opening its card. Fixes: V1, V5.
- In VP2 the values come from one pure `pg_privacy_retention_overview()` in `includes/fn/privacy_retention.php` (keys, labels, values, card anchors), red test first; the anchors `visitor_retention_days`, `visitor_ip_days` and `visitor_summary` open the row.
- In VP2 the inventory gains `visitors`, `waf_log` and `api_request_log` (IP and cookie, visitors; legitimate interest), with retention keys naming their settings in `pg_privacy_inventory_retentions()`; `tests/privacy_inventory_test.php` stays green with no person column.

- VP3 (S) after the architect's answer below: V3 and V2 as answered. On the proposals: the KVKK switch in `config_text` set at the update, `pg_legal_kvkk_settings()` reading it; the chat field out of `contact.php`, the "Site chats" rule in `pg_privacy_rules_catalogue()` through the chats' erase entry. If the answer is larger than S, two steps.

- VP4 (S) close-out: `ui/settings-privacy.md` written (the rows, the overview, what stayed where); `core/privacy-inventory.md` and `core/privacy-log-retention.md` corrected; item 2 of `plans/settings-tidy-census-order.md` removed; this plan's files deleted.

## Which steps run side by side?
- None: VP1, VP2 and VP3 enter `includes/settings/privacy.php` and `registry.php` one after another. VP3 also enters `contact.php`, before Communication's tidy (census order item 6).

## How is a step checked?
- A step copies its parts from the Design examples screen and writes no recipe of its own; it renders the pane at 1440 and 390 px before ready and writes "Screen check" in the PR.

Related: docs/memory/plans/privacy-settings-tidy.md, docs/memory/plans/privacy-settings-tidy-screens.md
