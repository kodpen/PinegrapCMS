---
name: panel-text-size
description: Panel text in rem so it follows the browser's text size: the screens and dashboard cards converted, the boxes that grow with their text, what stays px on purpose
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

## What did the dashboard, the bell and Sales Map convert?
- `backend.src.css` from `/* Dashboard list row` to `/* ══ Dashboard welcome card`: every text size (row names, meta lines, headline and visitor figures, System Status score and chips, job lines, Ecommerce head, empty states) is rem. Only icon glyphs keep px (`.pg-row-go`, `.pg-eg-stat .bi`, `.pg-head-dir`, `.pg-check > i`, carets, `.pg-health-dot`, `.pg-job-icon`, `.pg-job-btn i`, `.pg-widget-empty > .bi`, the `.pg-filter-ico` tiles); `tests/panel_text_size_test.php` lists them.
- A px box that holds text moves with it, or the grown text clips: `.pg-row-badge` (`2.125rem`, small `1.375rem`; it holds initials, a currency sign or `.pg-rank` under `overflow:hidden`) and its `--bs-avatar-size`, `.pg-health-dial` `max-width: 12.5rem` (the score sits inside the arc), the calendar card's day cells, day-name header and today circle (`.widget table.monthly_calendar`, `overflow:hidden` + `nowrap`; the day number gets its own `1.25rem` since the Calendars-screen rule it inherits from stays px), the bell's `#notifications` width `21.875rem` and `.notification-description` line-height `0.9375rem` (a px line-height under a 4-line clamp overlaps grown lines), `.pg-smap-tip` / `.pg-smap-menu`.
- `welcome.php` loading skeleton: tiles, bars, strip and chart heights, the Ecommerce line boxes and the System Status placeholders print rem (`$pg_ph_rem()`, locale-safe `sprintf('%.5F')`). The 28px filter tile stays: it mirrors the px `.pg-filter-ico`.
- Symptom: at a 32 px browser text size the cards doubled (track `15.5rem`) while their figures and lines stayed at 9-15 px, and a 29px skeleton strip holding two `1em` bars spilled onto the next one.

## What stays px on purpose?
- The Calendars screen (`table.monthly_calendar .calendar-day-wrapper` 20px, 200/100 px cells) waits for its own pass. `api_settings.php` and `includes/api/console.php`'s own rules are not converted yet.
- Workspace composer fields keep 16px inside touch media queries: below 16px iOS zooms the field. The Flows canvas (`.wsf-*`) belongs to another team.
