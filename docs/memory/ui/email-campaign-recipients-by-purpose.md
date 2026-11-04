---
name: email-campaign-recipients-by-purpose
description: Create Campaign picks recipients by purpose; transactional skips opt-in, the group list shows both counts
module: email_campaign_recipients
---

## Who does a new campaign go to?
- `add_email_campaign.php` builds each included group's query with `pg_email_campaign_recipient_query($purpose, $subscription_group)` (`includes/fn/email_campaign_recipients.php`).
- Commercial: `contacts.opt_in = 1` and, for a subscription group, the group's `opt_in` row is 1 or missing. Transactional: every contact of the group with a non-empty address. Only an exact `transactional` skips opt-in; any other value is treated as commercial.
- Excluded groups and the entered address are unchanged.

## Why?
- Owner decision for #5441 (`workflow/decisions-made-product-20.md`): a relationship notice must reach every customer, as the purpose label and the automatic profiles (`includes/fn/mail.php`) already do. Before #6413 Create filtered on opt-in for every purpose.

## What does the group list show?
- One query per group (`pg_email_campaign_group_count_query()`) counts contacts with an address (`total`) and those the commercial rule takes (`opted_in`). `pg_email_campaign_group_label()` prints "Orders (23, opted in 3)", or "News (5)" when the two counts are equal, so a change of purpose needs no reload. A group is listed when `total > 0`.
- The purpose card's transactional help warns: real transaction and relationship notices only; promotional text is sent as commercial.

## What stays per purpose at sending?
- The bounce block applies to every purpose (`mail-deliverability.md`); the İYS refusal and the unsubscribe footer stay commercial-only (`send_email_campaign.php`, `email_campaign_job.php`).
- Tests: `tests/email_campaign_recipients_test.php`. `db_q_campaigns_visitor_report_test.php` skips the builder calls in its placeholder count.
