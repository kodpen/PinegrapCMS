---
name: look-erp-lists
description: SH3: ERP lists in the look's list recipe: the plain list tables' heading and rows (erp-list-table), status badges as .pg-pill through pg_pill(), the Expenses filter card's labels
module: output
---

## Which ERP lists take the list recipe, and how?
- The plain `table[data-pg-sort]` lists off the driver carry `erp-list-table`: Expenses, Expense Categories, Cheques, Bank Statements, Purchase Orders, Quotes, Recurring Invoices, Stock Counts, the Audit Log and both tables of Stock (low stock and the movements ledger, whose cards now take `erp-list-card`).
- The rule sits beside `.erp-list-card` in `backend.src.css`; head and rows lie on the card's ground (transparent cells: the panel's `.table` cell rule painted the card colour, a slab on `pg-card`'s gradient; architect, channel 25 message 4969). Copied from `.pg-list-table-grid`: heading 38 px, 12 px (`--pg-text-meta`), weight 550, `--pg-fg-3`, the sorted heading (`aria-sort`) in the text colour; rows at least 52 px, cells 14 px from the edges under a translucent line.
- Rows keep 6 px above and below (the driver has none) because these rows wrap; the driver's one-line ellipsis is not copied.
- On a phone (under 576 px) the sides are 8 px and a cell keeps at least 6rem: the small heading no longer held the name column open, and `text-break` names broke every few letters.

## How are the statuses drawn?
- As `.pg-pill` through `pg_pill($text, $theme, $attributes)` (`includes/fn/output.php`), which escapes the text. `pg_pill_tone()` maps the theme names the status arrays carry: success teal, warning amber, danger rose, primary blue, info violet, everything else gray; a tone name passes through.
- The driver lists' badges and coloured status words became pills too: Accounts (Over limit), Invoices (status, due, e-document), Delivery notes (status), Incoming e-invoices, Account sync, Minimum stock.
- Left as they are: the e-document menu's count badges on Invoices (counts, not statuses), and record screens (Edit Expense, Cheque, Quote …), which keep their badges.

## Which filter card?
- Expenses: its six labels are `.pg-field-label`. The Audit Log's had it already. The forms on list screens (New cheque, the bank statement upload, New count, the account prices discount) are not filter cards and keep `form-label`.

PR: #9744
