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

## Why does a form field never carry a hint in `title`?
- Bootstrap 6's `Tooltip._fixTitle()` copies the title into `aria-label` on an element with no `aria-label` and no text, then removes the title. On a labelled field that `aria-label` replaces the label's name ("Leave it empty for one day." instead of "Last day"), and the hint shows only on mouse hover. A field hint is `<div class="form-text" id="…_hint">` under the field with `aria-describedby` on it (holiday form, `includes/workspace/workdays.php`, #2668).
- A popover sits at z-index 1050, under a fixed sheet at 1055 (`.pg-open-sheet`); an icon button inside one uses the tooltip recipe (1060) and its own `aria-label` (the Copy buttons of the Claude card's secret sheet).

## Can a screen heading carry its instructions in a popover?
- No. An `h2` (or a navbar title span) cannot take focus, so a `title` + `data-bs-content` there reached only mouse users; `_fixTitle()` drops the title and nothing exposes the content to screen readers. A real instruction prints as `<p class="form-text mt-0">` under the heading (or at the top of `main` under a picker's navbar) with the same `lang()` key; a text that only repeats the heading or the shell description is dropped. Not `heading_description`: `pg_page_shell` clips it to one line and hides it below lg. Fixed on Duplicate Folder, Create/Edit Auto Dialog and Key Code, Generate Layout, Edit Custom Page Style, Add Submitted Form, Preview Form and the link and image pickers; `tests/screen_help_visible_test.php` keeps every panel `h2` free of `data-bs-content` (view_fields.php and router.php exempt).
- A focusable element whose details must open from the keyboard too gets `no-popover` and its own binding, since `pgBindTitlePopovers()` forces `trigger: 'hover'`: `pgUI.popover.get(el, {trigger: "hover focus", html: true, placement: "auto", container: "body"})` (image picker tiles, `editor_select_image.php`; the missing-file tile takes `tabindex="0"`).
