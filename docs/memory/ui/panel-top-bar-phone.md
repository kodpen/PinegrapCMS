---
name: panel-top-bar-phone
description: Panel top bar (#header) on a phone: below 768px the mascot alone (no wordmark, version pill or page heading), below 390px no logo, why, the test that keeps it
---

## What does the top bar show below 768px?
- The menu button, the square mascot (the logo link `li.pg-brand-item`, its padding 4px) and the right hand cluster (search, quick note, settings, notifications, account menu, site link). The wordmark (`.pinegrap-logo-word`), the version pill and `.pg-navhead` (page title and description) are `display: none` in one `@media (max-width: 767.98px)` block beside the `.pinegrap-logo` rules in `assets/css/backend.src.css`.
- Below 390px a second block hides `li.pg-brand-item` too: the controls need 348px and the mascot 40 more, so a narrower bar would push the site link off it. The bar still overflows below 348px, as before the logo came back.
- From 768px up the full logo shows and nothing hides it.

## Why these widths?
- 768 is where the full logo stops fitting: the old rule cropped the old lockup to a 14px sliver and the title to a few letters, which the owner saw as cut off, and asked for both to go at phone width (#4529). Since the new logo (owner, 2026-11-04, `ui/brand-logo.md`) a narrow place shows the square mascot, so the mascot came back where the bar has room. The panel's own 1024/1280 breakpoints are untouched.

## What must a screen do because the heading is hidden?
- Anything shown only in the `heading` of `pg_page_shell()` is gone on a phone. A document's status badge is printed in the page too (#4529, from #4435): the cheque's fact card has a Status field (`erp_cheque.php`), the stock count (`erp_stock_count.php`) and the purchase order (`edit_erp_purchase_order.php`) a Status line above their content, the sales quote (`edit_erp_quote.php`) a line with status, signature, account and valid-until date (#5464). The heading badge stays for wide screens; the hiding rule is not touched.

## What keeps it?
- `tests/panel_header_phone_test.php`: both media blocks, no rule hiding the logo item or the mascot outside them, the `pg-brand-item` class in `output_header()`'s markup (`includes/fn/output.php`).

Related: docs/memory/ui/main-menu-states.md, docs/memory/ui/bootstrap-6.md
