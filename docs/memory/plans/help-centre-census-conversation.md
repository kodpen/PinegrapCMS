---
name: help-centre-census-conversation
description: Help centre census: what a Workspace mail conversation stores (state, holder, times, subject number) and what a support request needs beyond it: number, states, priority, answer time, own page
---

## What does a mail conversation store?
- `ws_mail_conversations` (`includes/migrations/2026.11.0.php:3233-3252`): box, channel, first message, subject, customer address and name, `contact_id`, `user_id`, `status` ENUM open/waiting/closed, holder `assignee_id`, times `first_in_at`, `first_reply_at`, `last_in_at`, `last_out_at`, `closed_at`.
- Each mail in or out is a `ws_mail_items` row with `direction` in or out and its text (`2026.11.0.php:3260`); the team's own talk under it is channel messages, apart from the mails.
- WhatsApp and SMS use the same tables with `transport` and `customer_phone` (`2026.11.0.php:6688-6708`).

## Which state, holder and times?
- States: Open, Waiting for the customer, Closed (`ws_mail_statuses()`, `includes/workspace/mail_status.php:37`); a new mail opens a waiting or closed one again (`includes/workspace/mail.php:1002-1004`).
- Only a support box keeps state, holder, first-answer time and the automatic answer (`ws_mail_box_is_support()`, `mail_status.php:55`; `workspace/mail-support-box.md`).
- Holder `assignee_id`: set by a person or by a mail rule; a rule's actions are assign and close only (`includes/workspace/mail_rules.php:56`).
- First-answer target per box, at most `WS_MAIL_FIRST_REPLY_MAX` hours (`mail_status.php:30`); due and overdue are computed from `first_in_at` (`ws_mail_first_reply_due()` `:449`, `ws_mail_first_reply_overdue()` `:467`), not stored.
- Closing announces `workspace.mail.closed` (`mail_status.php:215`), which queues a satisfaction survey (`includes/fn/feedback_sends.php:75`).

## What does a support request need beyond it?
- A number the customer sees: half there. Every answer's subject carries `[#T<id>]` (`ws_mail_reply_subject()`, `includes/workspace/mail_reply.php:58-67`), read back to thread the reply (`ws_mail_subject_tag()`, `mail.php:350-351`); no site page shows it.
- States: three today. "Solved" (answered, waiting to close) or a state the site names is not there; a new value changes the ENUM, which every reader of `ws_mail_statuses()` meets.
- A priority: none; no column, no rule action, no list filter.
- An answer time: the first answer only; no resolve-by time and no clock stopped while waiting for the customer.
- Where it came from (mail, form, chat, account): only the box and `transport` tell it.
- The customer's own page: none. A mail is tied to a site user only when the box's server vouched for the sender (`mail.php:1014-1015`), so `user_id` finds a user's requests only for those.
