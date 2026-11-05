---
name: list-table-erp-lists
description: ERP accounts, invoices, delivery notes, tills, incoming e-invoices and account sync on the list table driver (TD13a): the drawer's Quick look button, bulk ids, the aging report a plain table
---

## Which screens and how are they drawn?
- `erp_accounts.php`, `erp_invoices.php`, `erp_waybills.php`, `erp_cash.php`, `erp_inbox.php`, `erp_account_sync.php`: printed whole in browser mode (`ui/list-table-printed.md`), each in `pg_list_table_printed_lists()`; account sync keeps one sort per view (`erp_account_sync_<tab>`, its columns differ per view). A search box and the sort menu sit in the toolbar; the sort menu keeps the screen's filter (`filter`, `state`, `tab`, the invoice view).
- `pg_list_table_printed_sort_columns($columns)` gives the sort codes (every column but actions, image and `'sortable' => false`), so the screens do not repeat the loop.
- Defaults: accounts and tills by name, invoices, delivery notes and incoming e-invoices newest date first. Tills were in their `sort_order` with the passive ones last; they now sort by a column like every list.
- The name or number opens the record (`data-pg-open`); the Edit/View buttons are gone. A draft invoice's link opens the draft editor and sorts after the numbered ones (`~id`). A passive account or till keeps its struck-through name; a cancelled note and an ignored e-invoice are grey rows (row `class`).
- Server filters (credit limit, e-document readiness, due-date and e-document views, note status, inbox state, sync view) stay server filters; the invoice register still reads at most 500 rows with its cap note.

## How does the side drawer open now?
- A row click selects in the driver, so the drawer opens from a Quick look button in the row's `actions` column (`erp_drawer_button($id)`, `includes/erp/drawer.php`), on accounts, invoices and delivery notes. `assets/js/erp_lists.js` (not bundled) listens to `button[data-erp-drawer-id]` on `document`, since the driver draws rows again; the row of the open drawer is marked `table-active`. `erp_drawer_row_attributes()` is gone.

## How do the bulk actions reach their handlers?
- Invoices: the e-Document menu (Send selected, Hand to GİB, Ask status) is the selection bar's action; `ids_field` `invoice_ids[]` fills the `erp_edoc_bulk` form the list sits in. Only with a provider and not for the read-only accountant. Every row can be selected; a document the action does not apply to is counted as skipped, as before (25 per click).
- Account sync: provider-only cards post `card_ids[]`, ERP-only accounts `account_ids[]`. The put-aside view lists cards and accounts together, which can share a number, so its row ids are `card-12` / `account-5` in `sync_rows[]`. `erp_edoc_accounts_bulk_ids($_POST)` (`includes/erp/edoc/account_sync.php`) reads all three into `card` and `account` id lists, each id once, nothing but a whole number above 0.

## What stays a plain table?
- The aging report (`erp_aging.php`): one row per account with a totals `<tfoot>`, a `table[data-pg-sort]` report; the account name links to the account. The stock movements (`erp_stock.php`, the stock list itself moved in TD8b) stay a ledger.

Tests: `tests/erp_lists_td13_test.php`, `tests/erp_list_names_wrap_test.php`.

Related: docs/memory/ui/list-table-printed.md, docs/memory/erp/erp-list-edit-screen-pattern.md
