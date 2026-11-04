---
name: erp-quote-screen-layout
description: edit_erp_quote.php toolbar, two-column layout and where the messages of the signature, guest link and e-mail cards are drawn.
---

## How is the quote screen's toolbar built?
- With `pg_screen_toolbar()`: primary is "Turn into an invoice" while the quote can be invoiced, else the PDF; secondary the PDF and E-mail; grow the facts (status, account, valid until); the three-dots menu holds Download, Copy, Audit trail, Workspace and, after a divider, the status changes (accepted, rejected, cancelled, open again) as `menu-item` forms. Up to eleven buttons side by side made a seven-row bar on a phone.

## Where do the cards sit?
- Form column (`2xl:col-9`, plus `lg:col-8` when the quote is not open): the quote. Status column after it: signature, guest link, e-mail cards. An open quote splits only from 2xl because the line editor needs about 1,060px; at 1280px it scrolled sideways in a `lg:col-8`.
- The status column is not sticky: it holds forms, is taller than the window and is the target of `#erp-*` jumps.

## Where does a card action's message appear?
- Sign, guest link and e-mail actions redirect to their card (`$go_card()`), which remembers the card in `$_SESSION['software']['erp_quote_msg_card'][$id]`. The next draw prints the errors and notices inside that card (`$card_slot()`), not at the top, where they sat about 2,000px above the viewport. A card no longer drawn leaves them at the top.

## Is a typed unit price checked?
- `erp_typed_price_ok()` (`includes/erp/money.php`) refuses 'abc' or '12abc' in `erp_invoice_form_read()`; a currency sign or an upper-case code (TL, TRY) around the figure is allowed. Before, `erp_kurus()` saved 'abc' as 0. The invoice draft editor shares the reader.
