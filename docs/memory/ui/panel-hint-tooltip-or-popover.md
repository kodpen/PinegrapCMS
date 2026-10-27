---
name: panel-hint-tooltip-or-popover
description: Panel hover hints: why a bare title turns into a large header-only popover, when to use a compact tooltip (no-popover + data-bs-toggle="tooltip") and how to keep a details popover short
---

## What does a `title` become on a panel screen?
- `pgBindTitlePopovers()` (`backend.src.js`) turns every `[title]` (not `.no-popover`, not a toggle) into a hover popover with `html: true`, through `pgUI.popover.get()`. A popover with only a title is all header: grey band, large padding and type, up to 280px wide (Bootstrap 6's `--bs-popover-max-width`). Over a row of small controls (palette swatches, look buttons) it covers its neighbours; with a long text (a template's every page) it ran the height of the screen. Both were the template gallery's, made tooltips and a short card in PR #584.

## When is a compact tooltip right?
- For a short label on an icon-only control, or a word on a swatch: `class="… no-popover" data-bs-toggle="tooltip" title="…"` (plain text, escape once). The ready block of `backend.src.js` binds them once (`pgUI.tooltip.get(this, {placement: 'auto'})` over `[data-bs-toggle="tooltip"]`; Bootstrap 6 has no jQuery plugins); markup drawn later gets none unless its script binds it. Hide the tooltip (`pgUI.tooltip.hide(el)`) before opening a menu from the same button.
- A control whose text is visible needs no hint at all.

## And a details popover?
- Name in `title` (escaped twice: html), body in `data-bs-content`, `data-bs-custom-class` for a smaller font and width (Bootstrap reads both from the element). Keep it to a few lines: the first pages and "+n more", two highlights; facts already on the card stay off it. Example: the template gallery's info button (`sd-tpl-info`, docs/memory/designer/designer-home-screen.md).
