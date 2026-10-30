---
name: erp-back-targets
description: ERP detail screens go back to the filtered list they were opened from through send_to (row link, drawer Open in full, reloads, forms); which screens, which are left
---

## Where does an ERP detail screen go back to?
- To the list address it was opened from, filters and all, through `send_to` (`ui/back-arrow-targets.md`, `core/send-to-url-safety.md`).
- The back arrow is `pg_send_to_url('<list>.php')`; Save (Edit Account) and Delete (a draft invoice) `go(pg_send_to_url(<list url>))`; the breadcrumb is `pg_send_to_list_url($list_url)`, so it keeps `send_to` only when that is the same list.
- The saved/deleted notice stays on the list's own liveform (`erp_accounts`, `erp_invoices`), so it shows on the filtered list too.

## How does send_to get there and stay?
- Each list's row link adds `'&send_to=' . urlencode(REQUEST_URL)` (escaped with `h()`): Accounts, Invoices (draft and issued), Expenses (also the recurring source link), Quotes, Purchase orders, Cheques and notes.
- The drawer's Open in full (`assets/js/erp_lists.js`, not bundled) appends `send_to = location.pathname + location.search` to the url the drawer endpoint returns, so `includes/erp/drawer.php` keeps returning the bare screen.
- Each detail screen reads `$send_to = pg_send_to_url('')` once and builds `$send_to_query` (`&send_to=…`) and `$self_action` (`<screen>.php?send_to=…`, escaped). Every `go(...?id=)` self-reload appends `$send_to_query`; every POST form posts to `$self_action`, so `pg_send_to_url()` reads it from `$_REQUEST` on the POST. A closure that prints a form takes `$self_action` in its `use`.
- A draft invoice that is issued, and an issued invoice opened as a draft, keep `send_to` on the hop.

## What was the symptom?
- Accounts filtered to "not ready for e-documents" or "over their credit limit", the Aging links (`erp_invoices.php?account_id=&bucket=`), an expense period, and the Quotes, Purchase orders and Cheques filters were all lost after opening one row: the arrow, breadcrumb and Save went to the bare list.

## Which ERP links still go to the bare list?
- Waybills, the e-invoice inbox, `add_order.js`'s own drawer, the account card's and the drawer body's other links, `includes/erp/audit.php`, a cheque created on the Cheques list, and the invoice draft a quote or purchase order turns into.

## Which test pins it?
- `tests/erp_back_targets_test.php`: every list passes `send_to`, every detail screen's arrow and breadcrumb use it, no form action or `?id=` reload drops it.
