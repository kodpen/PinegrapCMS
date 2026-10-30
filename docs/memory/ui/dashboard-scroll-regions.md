---
name: dashboard-scroll-regions
description: Dashboard card lists that scroll inside the square card become named keyboard regions (pg_widget_scroll_regions() in welcome.php)
---

## Why did a scrolling card list need a Tab stop?
- `.card.widget` is square with `overflow: hidden`, so a body or a split half scrolls on its own (Google Search, Google Analytics, Performance, Subscriptions, both Firewall halves, Ecommerce Summary's left half). With only text rows there was no Tab stop inside: Safari could not scroll to the rows below the fold, and Chrome and Firefox focused the box by themselves without a name.

## What does pg_widget_scroll_regions() do?
- It runs on `pg:widget-drawn` (after the filter listener) and on a 200 ms debounced window resize, over `.card-body`, `.pg-split`, `.pg-split-half`, `.pg-split-scroll`, `.pg-ops-body` and `.pg-vs-pages`, inner areas first.
- An area whose `overflow-y` or `overflow-x` is auto/scroll, that overflows and holds no `a[href]`, button, field or `[tabindex]` gets `tabindex="0"`, `role="region"`, an `aria-label` and `data-pg-focus-key="scroll-N"`. All four come off when it stops qualifying (the card got wider, a control appeared). An area with a tabindex of its own is left alone.
- The name is the card header's text without its buttons and links; a half or split scroller adds its first `.pg-row-heading` text (`Ecommerce Summary – Best Sellers`).
- The focus key lets `pg_widget_focus_restore()` give focus back after a refresh.

## How is its focus shown?
- The card clips an outer ring, so welcome.php's own `<style>` draws an inset box-shadow on `--pg-focus-ring-width` / `--pg-focus-ring-color` for `[data-pg-focus-key^="scroll-"]:focus-visible`.

## What was not checked in a browser?
- Nothing was rendered; pinned by `tests/dashboard_keyboard_test.php` as source checks.

Related: docs/memory/ui/dashboard-keyboard.md, docs/memory/ui/dashboard-widget-files.md
