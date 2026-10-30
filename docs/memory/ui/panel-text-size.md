---
name: panel-text-size
description: Panel text in rem so it follows the browser's text size: the screens converted, the columns that grow with their text, what stays px, what waits for backend.src.css
---

## What is the rule?
- Panel text is sized in `rem` (px / 16), never `px`: the panel body is `1rem` and most of `backend.src.css` is rem, so a px size stays put while everything around it grows with the browser's default text size.
- At the 16 px default the rem value equals the old px exactly (10.5px is `0.65625rem`, never a rounded `0.6562rem`), so nothing moves at 100%.
- A grid or table column that holds text moves to rem with it, or the grown text wraps into a column that kept its width. Layout lengths that do not hold text (gaps, paddings, `minmax(168px, …)` card grids, checkbox columns, a scroll box's `max-height`) stay px.

## What symptom did it fix?
- With the browser's text size at 32 px, headings, buttons and fields doubled while these blocks stayed at 9–14 px: Marketplace product matching (`marketplace_products.php`), the Marketplaces account screen (`marketplace_settings.php`), API Documentation (`includes/api/console_view.php`, `api_console_css()`, also printed by the public console) and the rows of Update Search Index (`update_search_index.php`).

## What was converted?
- Every px `font-size` in the `<style>@layer custom` blocks of the two marketplace screens and in `api_console_css()`, the Marketplaces empty-state icon's inline size, and the five row templates of Update Search Index (page, event, form, form record, file): counter, title, address, type badge.
- Columns: `.mpp-row` score/action columns `7rem` / `16.25rem` (`6rem` below 1280 px); `.doc-params` first two cells `10.625rem` / `5.625rem`; the index counter's `min-width: 1.375rem`.
- `tests/panel_text_size_test.php` keeps px font sizes out of these blocks.

## What is left?
- The 81 px font sizes in `backend.src.css`, `api_settings.php` and `includes/api/console.php`'s own rules are not converted yet; convert them the same way, one screen per PR.
