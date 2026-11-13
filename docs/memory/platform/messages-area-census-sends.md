---
name: messages-area-census-sends
description: MA0 proposal (2026-11-13, open on #9081): the one Sends list reads the queues and campaigns with no new table (a UNION source on the list table driver): ids, period, states, rights
---

## Is this settled?
- No: step MA0's proposal of 2026-11-13, waiting for the architect on #9081 (questions 2 to 5). MA2 builds what the answer keeps. What each table holds: [[messages-area-census-sources]].

## How would one list read them?
- The list table driver concatenates a source's `from` into its SQL (`includes/fn/list_table.php`), so `from` is a derived table: `UNION ALL` of one SELECT per source with the same columns (sid, at, channel, row kind, to, what, source, state, done, total, owner, error). No driver change and no table.
- Branches: e-mail queue, SMS queue, WhatsApp queue (once WA2 lands), manual e-mail campaigns, SMS campaigns, automatic e-mail campaigns (a single message with its profile as source).
- sid = id * 8 + the branch's number (1 to 6): a whole number, unique across branches, so the keyset and the selection resolver (`pg_list_table_selected_ids()`) work as they are.
- The period is always set (24 hours, 7 days by default, 30, 90 days) and sits in every branch's own WHERE on an indexed time (`created_at`, `created_timestamp`, `start_at`), so a batch sorts only the period's rows. Its bound values lead the source's `params`: `from` comes first in the SQL.
- A campaign row opens to its recipients in a fold read by a second call, as the audit trail's changes (`ui/audit-log-paging.md`): `email_recipients` by campaign with the bounce record per address, or the SMS queue by reference prefix (gone after the sweep; the campaign's counts stay).
- The tiles count with indexed queries: queued and sending on `idx_due`, failed and outcome unknown, sent in 24 hours from the queues; a campaign's waiting recipients count as queued.

## Which states and sources would show?
- One State filter mapped per branch: Queued, Sending, Sent, Delivered and Read (WhatsApp only), Failed, Blocked (e-mail), Outcome unknown (SMS, WhatsApp); campaigns Draft, Scheduled, Sending, Paused, Completed, Cancelled.
- Source: SMS from kind and reference; campaigns and profiles by their row; e-mail from mail_type. Callers that queue add a `source` key to properties (`order:1044`; kept like `on_outcome`, `core/mail-queue-outcome.md`) and `pg_mail_enqueue()` writes its kind into mail_type (order, form, account, comment, flow, workspace, campaign, system): a copy, so no schema and no change to the connection.
- Flow e-mails are listed once the E-mail box queues with source `flow:<id>` (product code in MA2, no schema).
- Delivered, opened and clicked are not shown for e-mail and SMS: nothing records them. The drawing's open and click rates wait for a product question of their own (tracking pixel and consent).

## Who would see what, and which buttons?
- The tab for `validate_email_access()`. Queue branches only for role <= 2, as the mail queue today; a role 3 user gets only their own campaigns' branches (`created_user_id`, `created_by` in the branch's WHERE).
- Each row keeps its own rules: e-mail Retry for failed, Delete telling the outcome hook; SMS Retry for failed and outcome unknown rows not expired (an unknown one may have reached the phone); campaign rows Pause, Resume, Cancel as their screens; Run now as the mail queue's (10 messages, 20 seconds). Rows of a campaign have no Retry today.

## What would need schema, if asked?
- None for MA2. Two columns would make it exact, each for the owner to decide: `email_recipients.sent_at` (a campaign's sends in the 24-hour tile and a recipient's time) and `mail_outbox.source` (the record behind an e-mail, such as its order, searched without reading properties).

Related: [[messages-area-census-tabs]], [[messages-area-census-addresses]]
