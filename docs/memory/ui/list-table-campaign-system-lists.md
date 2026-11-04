---
name: list-table-campaign-system-lists
description: E-mail campaigns and campaign profiles, SMS campaigns, visitor reports, the 404 log, backups, DNS records and database tables on the list table driver; gift card redemptions a plain ledger
module: email_campaign_list
---

## Which screens and how are they drawn?
- `view_email_campaigns.php`, `view_email_campaign_history.php`, `view_sms_campaigns.php`, `view_visitor_reports.php`, `view_not_found_log.php`, `backups.php`, `cloudflare.php` (DNS records, list `cloudflare_dns`), `database_engine.php` (All tables): printed whole in browser mode (`ui/list-table-printed.md`), each in `pg_list_table_printed_lists()` under its `pg_list_state()` name. Column keys are the sort codes, so a heading click and the toolbar sort menu agree.
- The SMS campaigns, backups, DNS records and database tables had no kept sort before; they now have one (`pg_list_state()`), defaults: last change newest first, newest backup first, type, largest table first.

## How do the two e-mail campaign lists share their code?
- `includes/fn/email_campaign_list.php`: `pg_email_campaign_list()` reads the campaigns of two statuses the user may manage (role 3 only their own), builds the rows and returns `pg_list_table_printed()`; `pg_email_campaign_list_sort_columns()`, `_sort_default()` and `_columns()` depend on the e-mail campaign job (Scheduled Time only while it is on). Queries go through `db_q()` (`tests/db_q_campaign_screens_test.php`).
- The subject is the first column and opens the campaign (`data-pg-open`); To, Progress and Delivery report have no sort code, so they do not sort. Delete Selected is the bar's button; `ids_field` `email_campaigns[]` keeps `delete_email_campaigns.php` unchanged. The SMS list does the same with `sms_campaigns[]` to its own POST handler.

## What changed in the row actions?
- The Edit/View buttons of the campaign, SMS and visitor report lists are gone: the name opens the record.
- Rows whose buttons do something (404 log, backups, DNS, database tables) keep them in an `actions` column at the row's end. The backup buttons still submit the form the list sits in.
- A DNS row held a `<form>` for Delete; it is now a `.pg-cf-delete` button that asks and posts the one `#pg-cf-delete-form` below the list (no form inside a row). Its listener and the 404 log's are delegated to `document`, since the driver draws rows again.

## What stays as it was?
- The 404 log keeps its server search box and Who asked filter (they find addresses past the 1,000 rows read); it has no driver search box.
- The gift card redemption history (`edit_gift_card.php`) is a ledger: newest first, each row with the balance it left. It is a plain `table table-hover` in a `table-responsive`, no sorting; the order number links to the order.
- `view_email_campaign_profiles.php` (TD12b): printed whole, list `view_email_campaign_profiles`; the name opens the profile (a disabled one in the danger colour), Action Item and Schedule do not sort. Its select boxes were disabled and its form posted to the campaign delete endpoint; there is no bulk delete for profiles, so the list has no selection.

Tests: `tests/campaign_system_lists_td12_test.php`.

Related: docs/memory/ui/list-table-printed.md, docs/memory/ui/list-table-settings-lists.md
