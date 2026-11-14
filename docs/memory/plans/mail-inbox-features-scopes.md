---
name: mail-inbox-features-scopes
description: Exact scope of IB3-IB7 of plans/mail-inbox-features.md after the IB1 census: the functions each step changes, what waits on a schema answer, what stays out
---

## IB3: a person's own signature?
- Where: "My e-mail settings" on the E-mails screen toolbar, its own action beside `ws_mail_compose`; the rules of `ws_mail_signature_input()` (1,000 characters, 12 lines).
- Use: `ws_mail_send_body()` and `ws_mail_answer_body()` put it in place of the writer's name, the box's Signature under it; the New e-mail and reply previews from `ws_mail_compose` the same. Built when the mail leaves, as today.
- Schema asked of the owner (architect, 2863): `ws_profiles.mail_signature` TEXT NULL, written as `notes_pinned` is. HTML only after MA10b, asked again then.

## IB4: the block list?
- Check in `ws_mail_ingest()` after the loop gate, before the conversation is found: a match keeps the item with no conversation and no card, so `ws_mail_auto_reply()`, notices, flows and the customer line never run; the fetch takes it as done.
- Menu "Block sender" (address or domain, this box or every box): who may answer in the box (`ws_mail_can_answer()`) for this box; every box needs the Workspace settings right. The list with "Allow again" on the Mailboxes card. Site Log line per change.
- "Blocked" view of the E-mails screen; "Not blocked" makes the conversation; a sweep in `workspace_recurring_job.php` beside `ws_mail_eml_sweep()` deletes items older than 30 days.
- Schema asked of the owner (2863): table `ws_mail_blocks` (id, box_id 0 = every box, kind address|domain, value lower case, created_by, created_at, UNIQUE box_id+kind+value(191)); `ws_mail_items.blocked_at` INT UNSIGNED, `send_state` untouched.
- Architect (2863): a blocked mail keeps its `.eml` and the item's head only, no files, no HTML; "Not blocked" deletes the row, then takes the `.eml` through `ws_mail_ingest()`; the sweep removes row and `.eml` together. Phone numbers stay out.

## IB5: recipient suggestions?
- One read action, a prefix of at least 2 characters, at most 10 suggestions: contacts the person may open, then addresses of the boxes from `ws_mail_send_boxes()` (`from_email` by `idx_sender`, `to_list` / `cc_list` of the last 500 items), bounce-listed ones marked. To, Cc and Bcc of New e-mail and Reply by e-mail; a guest gets nothing. No schema, no API route.

## IB6: Send later?
- After MT3. Not through `mail_outbox.send_after` as it is (census): the item waits as `send_state = 'later'` with its time and a job queues it when due; time typed in `user.timezone`. "Send now" and "Cancel" under Not sent. Where the time is kept is asked when the step starts, after MT3's draft row.

## IB7: a box's Sent folder?
- In `ws_mail_fetch_box()`, after the box's folder, while the budget holds: the Sent folder (`settings.sent_folder`, else `pg_imap_sent_folder()`), headers only, Message-IDs already kept skipped, the rest ingested as out items into the conversation found by threading; a mail that answers no conversation is passed over. Never queued, so never sent again.
- Schema asked of the owner (2863): `ws_mail_boxes.sent_uidvalidity`, `sent_last_uid` INT UNSIGNED. Mark `send_state = 'external'`, read as `sent` everywhere, never "Send again", named in the API schema.
- Architect (2863): a box switch "Read the Sent folder", off by default; the first read starts at the present.

## What does every step leave alone?
- `ws_mail_fetch_run()`, the hold at 20 an hour and the loop gate keep their order; no API route or MCP tool unless the step above names one.
