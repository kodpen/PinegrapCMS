---
name: mail-rules
description: A support box's incoming mail rules (IB11): one condition and one action, kept in the box's settings JSON, run at ingest on a new conversation, the list and Add rule on the Mailboxes card
---

## What is a rule?
- One condition: the sender's address (whole), the sender's domain (with its subdomains, as `ws_mail_block_domains()`), or words anywhere in the subject (folded by `ws_mail_rule_fold()`: small letters, spaces tidied, the U+0307 dot a capital İ leaves taken off so "İade" equals "iade"; at most `WS_MAIL_RULE_WORDS_MAX` 100 characters). One action: give the conversation to a person, or close it. Conversations carry no labels, so there is no label action.
- Kept in `ws_mail_boxes.settings` JSON as `rules`, like the routes: a list of `id` (8 hex characters), `field` (address | domain | subject), `value`, `action` (assign | close), `user_id`; at most `WS_MAIL_RULES_MAX` (20) a box. No column; `ws_mail_rules()` reads and cleans them (`ws_mail_rule_clean()`).
- The box drawer's save starts from the stored settings, so it keeps `rules`; `ws_mail_rules_write()` reads the settings again before writing.

## Which boxes have rules?
- A support e-mail box only (`ws_mail_rule_box_ok()`): a plain box has no status or holder (`mail-support-box.md`), and a WhatsApp or SMS box has none (`ws_mail_phone_ingest()` is untouched). A box turned plain keeps its rules; they do not run.

## When do they run?
- `ws_mail_rules_run()` from `ws_mail_ingest()`, only for a mail that opens a conversation, after the block check and the conversation's write, before the `mail_customer` notice, the `workspace.mail.received` event and the automatic answer. A blocked, looping or held mail stops earlier, so it runs none. Rules run in their order; the first that fits wins (`ws_mail_rule_match()`).
- "Close it": status `closed`, `closed_at` now; that conversation gets no automatic answer (`$ruled !== 'close'`).
- "Give it to": `assignee_id` and inbox kind `mail_assigned` with actor 0. A person off the team, or who may not answer in the conversation's place (`ws_mail_can_answer()`), is passed over and the conversation stays with nobody.
- A person away today ("I am away", `workspace/mail-away.md`) hands it to their stand-in: `ws_mail_rules_run()` asks `ws_mail_away_stand_in()` after that check and writes and tells the holder it gives, as a hand assignment does (#9669; before it the away person held it and the stand-in was never told).

## Where are they set?
- "Incoming mail rules" on the Mailboxes card (Site Settings › Mailboxes), under "Blocked senders" (`ws_mail_rules_html()`): each rule as a line ("When the sender is at @shop.test → give it to Ayşe", `ws_mail_rule_text()`) with its box and Delete, then the Add rule form: Mailbox (support boxes), When, Address, domain or words, Then (Close it, or Give it to each team member). Drawn only when a support box exists.
- The fields are flat (`ws_mail_rule_box`, `_field`, `_value`, `_action`): the settings screen refuses a value posted as a list. The buttons submit the pane's form; `mailboxes.save.php` calls `ws_mail_rule_add()` (`ws_mail_rule_input()` checks) and `ws_mail_rule_delete()` (value `<box id>:<rule id>`). Both need the Workspace settings right, as Allow again does. A second rule with the same condition in one box is refused.
- Each change is a Site Log line (`ws_mail_rule_log_line()`). No API route or MCP tool.

## Tests?
- `tests/workspace_mail_rules_test.php`: clean, input, match, text and log lines, which boxes, the card's HTML, the wiring. Built by IB11 (#9589).
