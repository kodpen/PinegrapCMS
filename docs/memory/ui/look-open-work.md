---
name: look-open-work
description: Look A work still open after the plan panel-old-screens closed (2026-11-20): Add Filter on Edit Form List View and old buttons inside cards, the rich text editor's white page in dark, save bars left
---

## What did the architect note and leave for a later shared step?
- Architect (2026-11-17, looks of S3 #10314 and S4 #10316): old buttons inside a card, seen and left by the group steps. Example: Add Filter on Edit Form List View (`edit_form_list_view.php`, `button btn btn-solid theme-primary`). Not cut into a step; `tools/check_look.php` counts them under "old buttons".
- The rich text editor's white page in dark (CKEditor's editing area). Not cut into a step. What is dark already: `dark-theme-panel-css.md`.

## What did the shared steps leave?
- The bars in `includes/forms/*.php` draw the framed column by hand, not through `pg_save_bar()` (`save-bar-helper.md`).
- Old button order kept in `edit_comment.php`, `edit_erp_invoice.php` and Edit Product Group (`product_group_form.php`) (`save-bar-helper.md`).
- Older Summary cards not on `.pg-kv` (`pg_design_file_form_summary_card()`, `pg_order_view_row()`, `look-kv-list.md`).
- The kept screens' left parts: `look-kept-screens.md`.

## What counts did the plan close at?
- `tools/check_look.php` on 2026-11-20 (development with S6c #10547 and the close-out): 478 font sizes off the scale, 320 weights, 573 colours outside the tokens, 1,697 old buttons, 4 old tab strips. They only fall (`look-check.md`).
