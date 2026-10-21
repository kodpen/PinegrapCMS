---
name: designer-audit-contrast
description: Designer audit's text contrast check (WCAG 1.4.3): measured on the canvas with PgContrast, what it leaves alone (pictures, hidden, disabled, editor chrome), findings on templates, cost
---

## What does it check?
- Every text the canvas draws against the colour behind it: 4.5:1, or 3:1 for large text (24px, or 18.66px and bold). Ratio = `_pg_theme_contrast()` of the palette builder (`includes/fn/design_themes.php`); its browser twin is `assets/js/pg_contrast.js` (`window.PgContrast`, loaded by `includes/designer_screen.php`), kept equal by `tests/contrast_test.php`, which runs the JS under Node (skipped where Node is missing). PR #628.
- One issue per element, its worst text: rule `low_contrast`, label with the ratio rounded down, the minimum and the text (locale decimals). It goes on the nearest element a tree knows, inside shared components and widgets too (like `cf_field_unnamed`); the badge and Go work as for every issue.

## Why on the canvas and not from the tree?
- The colours come from the design's CSS, the theme's look and palette and Bootstrap's classes; only the browser resolves them. So `_sdContrastIssues()` is one of the canvas passes of `_sdCanvasAuditIssues()`, which `validateTree()` calls beside `_validationRules`; each pass sits in a try/catch so a broken canvas does not stop the rest of the audit. The other passes: docs/memory/designer/designer-audit-targets-entry.md.

## What is left alone?
- An unknown backdrop: a background image or gradient on the way down, a colour it cannot read (`oklch()`, a colour serialised outside sRGB), or a picture (img, video, canvas, svg, frame, background image) inside the opaque backdrop overlapping the text's box: text over a hero image or a card image overlay. No warning, since it could be either.
- Texts not seen: not drawn, `visibility` hidden, opacity 0, visually hidden (clip `rect(0…)` or a box of 1px), and disabled controls (`:disabled`, `aria-disabled`, `.disabled`; WCAG exempts inactive components).
- Editor chrome (`_SD_CANVAS_AUDIT_SKIP`: toolbars, badges, placeholders, loop bands, ghost records, binding hints, switched-off elements). Editor boxes are looked through (`_sdCanvasEditorBox()`: `.sd-wrap` except table parts and summaries, which are the real element, `.sd-shared-inner`, `.sd-loop-area`, `.sd-system-drop-area`); the body's grid overlay image is the editor's; a repeated table row's cells carry the editor's tint, so their colour is read from `--bs-table-bg`.

## What does it find on the built-in templates?
- Store template in plain Bootstrap colours: `text-primary`/`link-primary` #0d6efd on `bg-light` #f8f9fa (4.27:1), light text on primary, #6c757d on `bg-light` (4.45:1). With its coral palette: #e11d48 on #f8f9fa (4.46:1). Cafe with cocoa: nothing. These are real: the palette builder makes primary readable on white (`_pg_theme_readable()` against white), not on `bg-light`.

## Does the canvas zoom change it?
- No. The canvas zoom is CSS `zoom` on the iframe's `<html>`, and Chromium reports rectangles zoomed (a 30px button reads 15px at 50%), so every size test divides by `PgContrast.zoom(el)` (`currentCSSZoom`). Same findings at 25%, 100% and 200%.

## What does it cost?
- The store home page (1,480 elements, 151 texts) takes about 12 ms in headless Chromium per audit pass (500 ms after a render). Backdrops are cached per element for the pass (WeakMap); pictures are collected once.
