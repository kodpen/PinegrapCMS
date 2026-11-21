---
name: privacy-settings-tidy
description: Plan (owner 2026-11-21): Site Settings › Visitors and privacy as folded rows, one "How long we keep data" row showing every retention, popovers gone, the KVKK fields on their own
priority: 8
---

## What is asked?
- Owner (2026-11-21, in the R&D agent's window): fix the settings screens' contradictions, gather spread settings, make old screens modern; e-mail first, then the rest. Item 2 of `plans/settings-tidy-census-order.md`; one category a plan (architect, message 11313).

## What contradicts today? (development b047ef1eaf)
- (V1) How long data is kept is set in six places and none shows the others: visitor records and their IP addresses (`privacy.php:62-82`), the audit trail (`security.php:226-247`), the firewall log (`firewall.php:112-117`), the API log (`api.php:109-115`), site chats (`contact.php:51`), the Privacy centre's rules; the consent history's 3 years only in the Audit Trail's help.
- (V2) Chat Retention Period saves `chat_retention_days` (7 to 180, default 60) and `init.php:526` defines `CHAT_RETENTION_DAYS`, but nothing reads it: chats stay until deleted, as the Privacy centre's inventory says (`privacy_inventory.php:229-232`).
- (V3, census K1) The KVKK notice page and the consent sentence work only while the distance sales switch is on (`pg_legal_kvkk_settings()`, `legal_documents.php:870-876`), and sit in its popover, hidden while it is off (`privacy.php:164-177,195`).
- (V4) Two popovers: Enable Visitor Tracking's (Tracking Code Duration, Pay Per Click Tracking Code Flag) and Legal Documents' (two 12-row templates, the KVKK fields, the fields table); a switched-off feature's fields are hidden (`ui/settings-card-save-rules.md`).
- (V5) The inventory lists only tables the erasure reaches (`core/privacy-inventory.md`): the IP addresses in `visitors`, `waf_log` and `api_request_log` are on no line, so the Privacy centre says nowhere how long they stay.
- (V6) Visitor Tracking mixes the switch, the tracking code's cookie, the paid flag, the periods, the summary and Google Analytics; three labels with no help; `div.switch` drawings.

## What does the plan settle itself?
- Each period stays where it is set, beside what bounds the same data (the firewall log by its size limit, the audit trail by Open it): one place per setting (`decisions-made-product-14.md`), as the e-mail addresses stay (`plans/mail-settings-one.md`, C8).
- The "How long we keep data" row shows every period with today's value and Change opening its card; the visitor periods and the daily summary move into it from Visitor tracking (same pane, same post names).
- The pane becomes folded rows (`pg_settings_fold()`), as Features (FS2) and Assistants and agents.
- The KVKK fields get a row of their own, drawn whatever the switch; with VP3 they follow their own switch (V3).
- The inventory gains the three IP lines (V5), no person column: counted, never erased by a person's erasure.

## What did the owner decide? (`workflow/decisions-made-product-96.md`)
- (V3) Owner (2026-11-21, poll 214): the KVKK notice page and the consent sentence get a switch of their own, a `config_text` row the update sets to the distance sales switch's state, so no running site changes; after that the two go on and off apart.
- (V2) Owner (2026-11-21, poll 215): "Chat Retention Period" leaves Communication › Chat; a "Site chats" rule enters the Privacy centre's rules, off by default, dry run then yes (`core/privacy-retention-rules.md`), 60 days suggested; the update deletes no chat.

## What binds it?
- Field names, ids, columns and what a save stores do not change; card ids and old anchors keep working (`pg_settings_legacy_anchors()`); one Save for the pane; no field hidden by a switch.
- `includes/settings/privacy.php` takes one PR at a time; the Privacy centre's steps go on beside it.

Related: docs/memory/plans/privacy-settings-tidy-steps.md, docs/memory/plans/privacy-settings-tidy-screens.md, docs/memory/plans/settings-tidy-census-order.md
