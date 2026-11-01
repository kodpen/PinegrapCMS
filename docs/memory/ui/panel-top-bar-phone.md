---
name: panel-top-bar-phone
description: Panel top bar (#header) below 768px: the logo item (li.pg-brand-item) and the page heading (.pg-navhead) are hidden, why, what stays, the test that keeps it
---

## What does the top bar show below 768px?
- The menu button and the right hand cluster (search, help, settings, notifications, account menu, site link) only. `li.pg-brand-item` (the logo link, private label logo too) and `.pg-navhead` (page title and description) are `display: none` in one `@media (max-width: 767.98px)` block beside the `.pinegrap-logo` rules in `assets/css/backend.src.css`.
- From 768px up the full lockup fits and nothing changed: before/after header pixels match at 768, 834 and 1440.

## Why 768 and not the panel's 1024?
- 768 is where the logo stops fitting: below it the old rule cropped the lockup to a 14px icon sliver and the title shrank to a few letters ("Kata…" at 390px), which the owner saw as cut off. The owner asked for both to be hidden at phone width and for nothing to change where the logo fits. The panel's own 1024/1280 breakpoints are untouched; at 320px the bar also stopped overflowing (scroll width 341 to 320).
- The icon-only crop (`--pg-logo-icon-w`, `fill: url(#pgLogoGradientIcon)`) was removed with it; `pg_logo_svg()` still carries the unused icon gradient.

## What must a screen do because the heading is hidden?
- Anything shown only in the `heading` of `pg_page_shell()` is gone on a phone. A document's status badge is printed in the page too (#4529, from #4435): the cheque's fact card has a Status field (`erp_cheque.php`), the stock count (`erp_stock_count.php`) and the purchase order (`edit_erp_purchase_order.php`) a Status line above their content. The heading badge stays for wide screens; the hiding rule is not touched.

## What keeps it?
- `tests/panel_header_phone_test.php`: the media block, no hiding rule outside it, the `pg-brand-item` class in `output_header()`'s markup (`includes/fn/output.php`).

Related: docs/memory/ui/main-menu-states.md, docs/memory/ui/bootstrap-6.md
