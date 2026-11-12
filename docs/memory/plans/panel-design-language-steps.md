---
name: panel-design-language-steps
description: Steps DL1-DL6 of the panel's design language (look A, #8842): typeface and recipes, the Design examples screen, the bell's drawer as the first screen, the check, the screen families, close-out
---

## What is decided, and where is the look?
- The panel's screens are built from one written look, A "the quiet list" (owner, 2026-11-12, #8842; `workflow/decisions-made-panel-6.md`). The tokens for colour, spacing and radius stay (`ui/panel-css-tokens.md`); this plan adds the type, the recipes and the rule that a screen is built from them.
- The reference is `plans/panel-design-language/bell-drawer.jpg` (dark and light); its numbers are in `panel-design-language-look.md`, every value in `bell-drawer.html`.

## Which steps are there?
- S under 400 product lines, M about 1,000; each step with its tests and its topic file `ui/look-<part>.md`.

- DL1 (M) Type and the recipes. Inter (variable, OFL, self-hosted under `assets/fonts/`, the system fonts behind it) as the panel's typeface; the type scale and weights as `--pg-*` tokens; the recipes as classes in `backend.src.css`: row, icon tile and avatar, tones, button levels, chip, count, day label, unread dot, gathered rows. No screen changes in this step but by the typeface.

- DL2 (M) The Design examples screen: a panel page for the Designer role and above that shows every recipe live in both themes, with its class names and what is not done. A step copies from it; the owner looks at it.

- DL3 (M) The bell's drawer in look A: rows from the recipes, the fold drawn as gathered rows with the count beside the title, titles that say what happened and with what (`ui/bell-row.md` rewritten). Approved from a real panel's screenshots: dark, light, phone width.

- DL4 (S) The check and the rule. `tools/check_look.php` in CI counts panel rules outside the recipes (a font size or weight off the scale, a colour outside the tokens) and lets the count only fall. Key rule in `ui/README.md`: a panel screen is built from the recipes; a new recipe is drawn first and asked on an `architect` issue.

- DL5a to DL5e (M each) The screen families, one step each, in this order: dashboard cards and rows; list screens (toolbar, table, row actions); settings (section cards, forms, the modal); report screens; Workspace (channel, lists). Each starts with the family's drawing in `plans/panel-design-language/`, approved by the owner, then the code.

- DL6 (S) Close-out: `ui/look-overview.md`, the lasting rules in `ui/`, this plan and its folder deleted but for what the overview names.

## What holds for every step?
- A recipe lives in one place and is never copied into a screen's own CSS; a screen's own class only places things.
- User texts through `lang()`, the Turkish with `php tools/tr_add.php`; `php tools/build_css.php` after `backend.src.css`.
- A step that changes how a screen looks is approved from a real panel's screenshot, never from a drawing; the drawing is only where it starts.
- DL3 and each DL5 step wait for DL1; DL2 and DL4 may run beside them.

## Open questions
- None now.
