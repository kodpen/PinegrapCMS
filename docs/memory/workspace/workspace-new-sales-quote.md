---
name: workspace-new-sales-quote
description: "New sales quote" in a Workspace channel's menu: the quote editor opened on the current account the channel's customer is or is tied to; who sees it, how the account is found, what is never done
---

## What does it do?
- The channel's menu (the three dots, `channelMenu()` in workspace.js) offers "New sales quote" / "Yeni satış teklifi". It opens add_erp_quote.php `?account_id=<id>`, the prefill the account card's "New quote" already used: the id only chooses the account box's option and saving checks the account again. The Workspace writes nothing.
- The address comes with the channel: `sales_quote_url` in `ws_channel_detail()` (includes/workspace/channels.php), empty when nothing is offered. It is built on the server; workspace.js never puts an account id together.

## Which account does it open on?
- `ws_channel_sales_quote_account()` (includes/workspace/customer.php): the customer (`ws_channel_customer_of()`) when it is a current account, else the first account tied to it through its contact (`ws_customer_link_tokens()`, active accounts first).
- The pure `ws_sales_quote_account_pick()` takes an account only when it is active (the quote editor lists no other) and not `kind = 'supplier'` (the account screen offers a supplier alone no quote either).
- None found: the item is not shown. `erp_account_for_contact()` is never called: it opens an account for a contact that has none.

## Who is offered it?
- `ws_sales_quote_may_start()`: the ERP right (`$viewer['erp']`; staff hold it) without the accountant's read-only right (`USER_ERP_READONLY`, refused by the editor's save): the people the quote chip's "Copy as a new sales quote" is offered to. The quotes table must be there too (`ws_erp_quotes_readable()`).
- The right is asked before any query, so a reader without it never learns whether the customer has an account: the item is missing the same way either way.
- The read-only flag is the session's constant: `ws_channel_detail()` is called only from the panel's `ws_*` actions, whose viewer is the signed-in person.

## What guards it?
- tests/workspace_sales_quote_test.php: the pick (customer, ties, suppliers, passive and deleted accounts, other kinds of tie), the rights rule, no lookup without the right (the runner has no database, so a query errors), and the lookup's source asks the right before it reads and never names `erp_account_for_contact` or an INSERT.
