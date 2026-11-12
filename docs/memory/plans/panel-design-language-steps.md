---
name: panel-design-language-steps
description: Steps DL1-DL6 of the panel's design language (look A, #8842, #8860): typeface and recipes, the page widths, the Design examples screen, the bell's drawer, the check, the screen families, close-out
---

## What is decided, and where is the look?
- The panel's screens are built from one written look, A "the quiet list" (owner, 2026-11-12, #8842 and #8860; `workflow/decisions-made-panel-6.md`, `-7.md`). The tokens for colour, spacing and radius stay (`ui/panel-css-tokens.md`); this plan adds the type, the recipes, the page widths and the rule that a screen is built from them.
- The reference is the sheet in `plans/panel-design-language/`: its pages and the page widths in `panel-design-language-sheet.md`, its numbers in `panel-design-language-look.md`, every value in `sheet.css`.

## Which steps are there?
- S under 400 product lines, M about 1,000; each step with its tests and its topic file `ui/look-<part>.md`.

- DL0 (no product code) The reference sheet, made by the architect with the owner in the architect's window (#8842): each part in both themes, the source beside the picture. Pages: `panel-design-language-sheet.md`.

- DL1 (M) Type and the recipes. Inter (variable, its optical size axis gives headings the Display cut; OFL; self-hosted under `assets/fonts/`); the type scale and weights as `--pg-*` tokens; the recipes as classes in `backend.src.css`. No screen changes but by the typeface. Done in #8879 (`ui/look-type-and-recipes.md`).

- DL1b (M) The page widths (#8860) as `panel-design-language-sheet.md` sets them: the measures as tokens, an add or edit screen's centred content, the list frame's width rule. First screens `product_builder.php` and the products list, approved from a real panel's screenshots at 1920 and 1366 px. Rewrites the width lines of `ui/admin-screen-toolbar.md`, `ui/edit-screen-wide-layout.md`, `ui/list-table-layout.md`. Done in #8892 (`ui/look-page-widths.md`).

- DL2 (M) The Design examples screen: a panel page for the Designer role and above that shows every recipe live in both themes, with its class names and what is not done. A step copies from it; the owner looks at it. Done in #8907 (`ui/look-design-examples.md`).

- DL3 (M) The bell's drawer in look A: rows from the recipes, the fold drawn as gathered rows with the count beside the title, titles that say what happened and with what (`ui/bell-row.md` rewritten). Approved from a real panel's screenshots: dark, light, phone width. Done in #8897.

- DL4 (S) The check and the rule. `tools/check_look.php` in CI counts panel rules outside the recipes (a font size or weight off the scale, a colour outside the tokens) and lets the count only fall. Key rule in `ui/README.md`: a panel screen is built from the recipes; a new recipe is drawn first and asked on an `architect` issue. Done in #8905 (`ui/look-check.md`).

- DL5a to DL5f (M each) The screen families, one step each, in this order: dashboard cards and rows; list screens (toolbar, table, row actions); add and edit screens (cards, field widths, the save bar); settings (section cards, the modal); report screens; Workspace (channel, lists). Each waits for its family's part of the sheet; lists and add screens have theirs. DL5a waits for its drawing. DL5b done in #8921; DL5c in progress.

- DL6 (S) Close-out: `ui/look-overview.md`, the lasting rules in `ui/`, this plan and its folder deleted but for what the overview names.

## What holds for every step?
- A step copies what the sheet shows, value for value; it does not design. Where the sheet is silent it asks on an `architect` issue.
- A recipe lives in one place and is never copied into a screen's own CSS; a screen's own class only places things.
- User texts through `lang()`, the Turkish with `php tools/tr_add.php`; `php tools/build_css.php` after `backend.src.css`.
- A step that changes a screen's look is approved from a real panel's screenshot; the drawing is only where it starts.
- DL3 and each DL5 step wait for DL1; DL1b, DL2 and DL4 may run beside them.

## Open questions
- None now.
