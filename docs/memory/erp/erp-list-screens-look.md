---
name: erp-list-screens-look
description: Expenses, Expense Categories, Cheques and Bank Statements in look A (E2): look cards and titles, the figures recipe, erp-list-card, cell width classes, field widths
---

## Which ERP lists are on look A?
- Expenses `erp_expenses.php`, Expense Categories `erp_expense_categories.php`, Cheques and notes `erp_cheques.php`, Bank statements `erp_bank_statements.php`: step E2 of the panel-old-screens plan. They keep `pg-measure-list` and their plain `table[data-pg-sort]`; none is on the list table driver.
- Purchase Orders, Quotes, Recurring Invoices, Stock Counts, the Audit Log and the driver lists' leftover cards are step E2b.

## How are cards and titles drawn?
- Every card is `card pg-card`; titles are `h2.card-header.bg-reset.border-0` with `pg-title-section` in the list column and `pg-title-status` in the status column (Summary, Take in). The old `text-uppercase h5 fg-primary fw-bold` header is gone.
- A card holding a table or a flush list also takes `erp-list-card` (`backend.src.css`, beside `.pg-cell-wrap`): `overflow: hidden` as `.pg-report-frame` has, so a table's header fill keeps the card's corners, and a `.list-group` inside sits on the card's surface. The cheque form card does not take it, so its combobox list is not clipped.

## What is the Expenses figures strip?
- The report figures recipe (`ui/look-report-screens.md`): `dl.pg-report-figs` of `div.pg-card.pg-report-fig`, each a `dt.pg-report-fig-name`, `dd.pg-report-fig-value`, `dd.pg-report-fig-note`, as `includes/fn/messages_sends.php` prints it. "Still to pay" keeps its `border-danger` card and `fg-danger` figure while an expense is past due.
- By category's bar is the report bar track: `div.pg-report-bar-track` with a `b` as wide as the share (the width is the row's data, inline). It keeps `role="progressbar"` and its `aria-*`.

## Where did the inline widths go?
- Into classes in `backend.src.css`, same values: `erp-expense-cell-category`, `-supplier`, `-text`; `erp-category-cell-order`, `-name`, `-code`, `-count`, `-tax`, `-on`; `erp-cheque-cell-account` (240 px).
- Left inline: the status column's sticky `top:3.3rem` (the shared pattern of the panel's status columns) and the save bar's `bottom:.5rem`.

## Which field widths?
- New cheque or note, by the field widths of DL5c (#9658): Direction, Type, Account 6; Amount, Date, Due Date 3; Serial number 4; Bank and Branch 6 + 6 on one row (two short names that belong together); Drawn by and Notes the whole row.
- Kept in is alone in its row: `col-12 pg-field-span-6`, so its help runs the row's width (architect, channel 25 message 4459). The span rule comes with #9658; before it lands the select is the whole row.
- The Expenses filter bar and the Take in form in the status column keep theirs.
