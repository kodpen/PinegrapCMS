---
name: workspace-composer-touch
description: The writing box's tips, windows and menus on a touch screen: 16px fields so iOS does not zoom (tipFieldClass(), --pg-text-field-touch), the table size picker's 2.5rem cells
---

## Why are the box's fields 16px on a touch screen?
- iOS Safari zooms the page in when a field under 16px takes the focus and leaves it zoomed; `form-control-sm` is 14px. `tipFieldClass(touchScreen())` in `workspace_editor.js` gives the link tip, the find tip and the insert menu's windows (block title, table formula, code language, calculation decimals and unit) the full-size field on a touch screen, the small one under a mouse. Test: `tests/workspace_editor_tip_fields_test.php`.
- On a touch screen (`@media (pointer: coarse)`) the emoji search, the poll window's fields (`#ws-poll-form .form-control`) and the code and calculation areas (`.ws-code-edit`) read `--pg-text-field-touch` (16px) (#10552, PR #10592).

## How big are the table size picker's cells?
- On a touch screen 2.5rem (6 columns fit 320 px), as the emoji items; 1.4rem under a mouse (#10551, PR #10592). Test for this file's touch rules: `tests/workspace_phone_touch_test.php`.

Related: docs/memory/workspace/workspace-channel-composer.md
