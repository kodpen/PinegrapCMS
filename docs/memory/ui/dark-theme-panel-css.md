---
name: dark-theme-panel-css
description: backend.src.css swept for fixed light fills and dark text after Bootstrap 6: .pg-table-light on tokens (black head text in dark), tone rows' band under the card fill, what stays fixed
---

## What changed?
- `.pg-table-light` (table heads of `find_and_replace.php`, `view_parasut_inbox.php`, `erp_expense_categories.php`) (#2566) was v5's `.table-light` copied 1:1 by the codemod: black text and `#c6c7c8` lines in both themes. In the dark theme the panel's table rules paint the head cell near-black, so the head text was black on black. It reads `--pg-fg-emphasis` (text), `--pg-bg-1` (fill) and `light-dark(#c6c7c8, var(--pg-border-color))` (line) now.
- Computed colours (Playwright, Bootstrap 6 dist + backend.src.css): light unchanged (fill 245,245,245, text 0,0,0, line 198,199,200); dark text 0,0,0 to 255,255,255, line 198,199,200 to 73,80,87.
- `tests/dark_theme_css_test.php` pins the rule and that no rule reads a v5 neutral Bootstrap 6 dropped (`--bs-body-color`, `--bs-body-bg`, `--bs-secondary-color`, `--bs-tertiary-color`, `--bs-tertiary-bg`, `--bs-emphasis-color`).

## Why did tone rows show only coloured text?
- The panel paints every cell with the card fill (`.table > :not(caption) > * > *`, `@layer custom`), which beats Bootstrap 6's cell rule and DataTables' vendor rule, so `.table-warning` rows (ERP account sync comparison, `erp_account_sync_item.php`) and the `.table-info` Grand Total row (`view_shipping_report.php`, its `dt-scroll-foot` clone too) showed tone text on card grey in both themes.
- A rule after the `.table-info` block (#PR) paints tone rows' cells and tone cells with their own `--bs-table-bg`. The card-fill rule stays: changing it would start painting `.pg-table-light` heads and `.pg-sale-table`. `tests/table_tone_rows_test.php` pins it.
- Computed (Chromium, v6 dist + backend.src.css): warning cell 245,245,245 to 255,248,225 light, 12,13,15 to 42,34,16 dark; info 224,247,250 light, 13,36,40 dark; plain cells unchanged.

## Why `light-dark()` for the line?
- No token is `#c6c7c8` in the light theme (`--pg-border-color` is `#dee2e6`), and the light look must not move; token values are not tuned here.

## What was swept and left fixed on purpose?
- Every light fill (luminance over 0.85), light line and dark text in backend.src.css, one-line rules included. Kept:
- `.btn-outline-light`: light outline for the visual editor's dark chrome.
- Dashboard widget tints (`.widget[widget-id=…]`, `#system_status_card`), the calendar's today mark and event links, the glass welcome card, the CKEditor bar: each has a `html[data-bs-theme="dark"]` rule.
- `.ws-preview-frame` (white behind a previewed document: paper), `.ws-preview-video` (black), the toolbar shade, `.pg-preloader`'s `#fff` fallback, white text and lines on accent or dark fills.
- No other rule reads an undefined v5 neutral; `--bs-secondary-bg*` there are v6 theme names of the secondary colour.

## What is not covered?
- Inline `<style>` blocks of panel screens and other stylesheets; the fixed fills and text colours of #2564 (`dark-theme-fixed-fills.md`, `dark-theme-text-colour.md`).

Related: docs/memory/ui/panel-css-tokens.md, docs/memory/ui/bootstrap-6-codemod.md
