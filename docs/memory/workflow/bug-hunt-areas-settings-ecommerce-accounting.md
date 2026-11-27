---
name: bug-hunt-areas-settings-ecommerce-accounting
description: Bug hunt of Site Settings › Ecommerce and › Accounting after their tidy rounds (2026-11-27, PR #12037): what was read, the two faults fixed, what read clean
---

## What was the area?
- What the plans settings-ecommerce-tidy (#11868, #11885, #11899, #11919, #11938) and settings-accounting-tidy (#11875, #11887, #11908) changed: the folded rows, a field that moved row, rights per pane, search and links reaching a moved setting. The unchanged save of every pane was not read again (`bug-hunt-areas-settings-save.md`).

## What was fixed?
- Stock history (`view_stock_moves.php`) without a location: "Add a stock location" linked `settings.php#pgset-stock-locations`. There is no `settings.php`; the dialog read it as a link without a pane and opened the category last used. It now links `pg_settings_link('commerce', 'pgset-stock-locations')`.
- Texts on ERP screens sent the operator to "the ERP settings card", which the tidy cut into rows. They now name the row: Reminders and notices (invoice screen's reminder lines, the account form, the reminder e-mail's footer), Web orders (the account form's invoice e-mail), Sales (no walk-in account on Issue the Invoice, order screen and order flow), Documents (ERP Settings' seller note). Test: `tests/settings_moved_rows_links_test.php`.

## What read clean?
- Every field each save reads is drawn in its pane; the conditional rows (Customer accounts, notices, quote expiry, Labels, Photo reviews, Reference price, Stock locations) are drawn under the same column checks their save uses.
- The radio groups turned `pg-seg` or radio rows post the old names and values; the two gateway Mode groups keep distinct ids; the ticked product groups post `product_feed_excluded_groups[]` as the select did.
- Both panes stay manager-only, as before the tidy; ERP Settings keeps its role-3 path.
- Legacy anchors of moved fields land on the row that holds them; links from other screens into both panes name existing cards; the search index reads each row's card.

## What was left?
- A search hit or link to E-Invoice or e-Fatura threshold where they are drawn hidden (outside Turkey) lands on no visible card. Older than the tidy, left as it was.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/ui/settings-ecommerce.md, docs/memory/ui/settings-accounting.md
