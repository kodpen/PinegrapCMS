---
name: mail-blocks
description: A mailbox's block list (IB4): Block sender under a mail, who may block where, what a blocked mail keeps, the list with Allow again on the Mailboxes card, the 30-day sweep, the schema step
---

## What is blocked?
- An address, or a whole domain with its subdomains (`ws_mail_block_domains()`: a.mail.x.com, mail.x.com, x.com), for one box or, with `box_id` 0, for every box. Values are lower case (`ws_mail_block_value()`). Phone numbers (WhatsApp, SMS boxes) stay out: no offer, and `ws_mail_block_decide()` refuses a phone box.
- Owner (2026-11-14, `workflow/decisions-made-workspace-27.md`): the list belongs to a box or to every box, not to a person, because a box is shared.

## What happens to a blocked sender's mail?
- `ws_mail_ingest()` asks `ws_mail_block_find()` after the loop gate and before the hourly hold and the conversation: a match goes to `ws_mail_block_store()`, status `blocked`. The item keeps its head (sender, To, Cc, subject, times) with `blocked_at`, `conversation_id` 0, no text, files or HTML, and its `.eml`. No card, notice, flow, customer line or automatic answer; `ws_mail_fetch_outcome()` takes it as done.
- The hourly hold counts only `blocked_at = 0` items, so a blocked sender let in again is not held by their blocked mail.
- An `.eml` brought in by hand from a blocked sender is told so (`ws_mail_ingest_message()`).

## Who may block, and where?
- "Block sender" under an incoming mail of the E-mails reading pane (`ws_mail_block_pane()`, `ws_mail_block_item_html()`, beside Forward): a form opened in place with two segmented choices (the address or "Everyone at <domain>"; This mailbox or Every mailbox). Action `ws_mail_block` (`ws_mail_block_add()`).
- This mailbox: a team member who may answer in the conversation's place (`ws_mail_can_answer()`); Every mailbox: the Workspace settings right too. A guest blocks nothing; a delivery failure is not offered.
- Each block and each Allow again writes a Site Log line (`ws_mail_block_log_line()`).

## Where is the list?
- The Mailboxes card (Site Settings › Mailboxes) lists "Blocked senders" under the boxes (`ws_mail_blocks_html()`), newest first, at most 500. Allow again is a submit button `ws_mail_allow` (value the block id) of the pane's form, handled by `mailboxes.save.php` through `ws_mail_block_allow()`; the card is behind the settings right.

## How long is a blocked mail kept?
- `ws_mail_block_sweep()` in `workspace_recurring_job.php`, before `ws_mail_eml_sweep()`, deletes items with `blocked_at > 0` older than `WS_MAIL_BLOCK_DAYS` (30), row and `.eml` together, 500 a run. The `.eml` sweep skips blocked items.
- The "Blocked" view and "Not blocked" are IB4b of `plans/mail-inbox-features.md`.

## Which schema?
- Step `upgrade_2026_11_0_ws_mail_blocks()` (11.0.202): table `ws_mail_blocks` (id, box_id, kind address|domain, value VARCHAR(255), created_by, created_at, UNIQUE (box_id, kind, value(191))) and `ws_mail_items.blocked_at` INT UNSIGNED. Approved by the owner in the architect's window (channel 25, 2955). Readiness: `ws_mail_blocks_ready()`.

## Tests?
- `tests/workspace_mail_blocks_test.php`: the step run twice in memory, value, domains, match, decide, log lines, the form and the list, the wiring. No database or browser run.
