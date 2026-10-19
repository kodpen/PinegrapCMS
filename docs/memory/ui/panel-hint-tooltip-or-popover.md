---
name: panel-hint-tooltip-or-popover
description: Panel hover hints: why a bare title turns into a large header-only popover, when to use a compact tooltip (no-popover + data-bs-toggle="tooltip") and how to keep a details popover short
---

## What does a `title` become on a panel screen?
- `pgBindTitlePopovers()` (`backend.src.js`) turns every `[title]` (not `.no-popover`, not a toggle) into a hover popover with `html: true`. A popover with only a title is all header: grey band, large padding and type, up to 276px wide. Over a row of small controls (palette swatches, look buttons) it covers its neighbours; with a long text (a template's every page) it ran the height of the screen.

## When is a compact tooltip right?
- For a short label on an icon-only control, or a word on a swatch: `class="… no-popover" data-bs-toggle="tooltip" title="…"` (plain text, escape once). The ready block binds them once (`$('[data-bs-toggle="tooltip"]').tooltip()`); markup drawn later gets none unless its script binds it. Hide the tooltip before opening a menu from the same button.
- A control whose text is visible needs no hint at all.

## And a details popover?
- Name in `title` (escaped twice: html), body in `data-bs-content`, `data-bs-custom-class` for a smaller font and width (Bootstrap reads both from the element). Keep it to a few lines: the first pages and "+n more", two highlights; facts already on the card stay off it. Example: the template gallery's info button (`sd-tpl-info`, docs/memory/designer/designer-home-screen.md).
