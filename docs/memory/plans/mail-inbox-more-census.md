---
name: mail-inbox-more-census
description: IB9 census of plans/mail-inbox-more.md (2026-11-14): per-person conversation state, My e-mail settings, Send later, ingest order, labels, notices and the job, Not sent
---

## Where is a conversation's state per person kept?
- Only in `ws_mail_reads` (conversation_id, user_id, item_id; a row with conversation 0 is the floor): the newest incoming mail the person has read (`ws_mail_unread_sql()` in `mail_reads.php`). A mark never moves back (`GREATEST`), so nothing there can hide a conversation or make it unread again. Status and holder belong to the conversation, the same for everyone (`ws_mail_conversations`).
- The list (`ws_mail_list_where($filters, $viewer_id, …)`) already takes the viewer, so a per-person condition fits there.

## Where are "My e-mail settings" kept?
- `ws_profiles.mail_signature` (11.0.203), the only mail column of the person; button `ws_mail_list_my_settings_html()` (mail_list.php), drawer `mySettings()` in `workspace_mail.js`, actions `ws_mail_my_settings` / `_save` (`ws_mail_own_signature_save()`, mail_signature.php). Time zone: `user.timezone`.

## What does Send later (IB6) keep?
- In flight: draft #9547 (`includes/workspace/mail_later.php`, `ws_mail_later_parse()`: a time in `user.timezone`, 5 minutes to 60 days ahead). The item waits as `send_state = 'later'`, a job queues it when due; where its time is kept (`ws_mail_items.send_at`, the item's `sent_at`, or an outbox hold) is asked in channel message 3414. `ws_mail_send()` today writes conversation, card and item, then dispatches at once.

## What runs at ingest, in which order? (`ws_mail_ingest()`, mail.php)
- Parse, de-duplication, the loop gate, the block check (`ws_mail_block_find()`, unless `past_block`), the hold at 20 an hour, threading, the channel (an answer stays; a new conversation by `ws_mail_route_channel()`), item row, card or thread message, files, `mail_customer` to the holder (ongoing conversation only), `workspace.mail.received`, the automatic answer (new conversation only), triage.
- A rule fits after the conversation is written and before the notices: a new conversation only, as routing and the automatic answer.
- Rules have a home without schema: routes already live in `ws_mail_boxes.settings` JSON (`routes`, at most 10, `ws_mail_routes()`).
- Nothing gives a conversation a holder at ingest today: a holder comes from sending (the writer), by hand (`ws_mail_conversation_set()`, action `ws_mail_conversation`, the API's update).

## Does a conversation carry labels?
- No: no label or tag column or table on conversations or items (`mail-tables.md`); the Workspace's only tag table is `ws_agent_tags`. IB11's action "a label" drops out.

## How does a notice and the general job wake a person at a time?
- `ws_notify($user_id, $kind, …)` (notify.php): an `ws_inbox` row (kind VARCHAR(20), no step for a new kind), the bell, a push. Read back by `ws_mail_inbox_describe()` for mail kinds.
- Timed wakes run in `job.php` through `ws_task_reminders_waiting()` / `ws_task_reminders_run()` (reminders.php); personal plans hook in there (`ws_personal_plan_reminders_*`, claimed by `reminded_at` with `KEY idx_remind (reminded_at, remind_at)`). A mail wake hooks in the same way: no new job.

## How does "Not sent" hold a mail?
- View `unsent` = an out item with `send_state` `queued` or `failed` (`ws_mail_list_where()`); badges "Waiting to be sent" / "Not sent" (`ws_mail_send_badge_html()`); Send again only on the card. A mail that waits and has not been queued has no state yet: IB6 brings `later`.

## How is My calendar reached for "I am away"?
- `ws_personal_plans` (11.0.201) with `visibility` `team` is a plan the whole team sees (`workspace-personal-plan-sharing.md`); all-day, `starts_at` / `ends_at` end exclusive. An away range can be such a row; only `personal_plans.php` may name the table.
