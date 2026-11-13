---
name: legacy-layouts-left
description: After the page types' move to Bootstrap 5.3.8 names (LL1-LL6): what the trial design's compatibility sheet still draws, what no rename reaches and the way out
module: legacy_bootstrap
---

## What is left of the compatibility sheet?
- `assets/css/themes/legacy-bootstrap3.css`, loaded on a trial design only, keeps what the pass leaves as it was: a bare `checkbox` and the glyphicons inside a theme widget (`.unchecked`'s tick), drawn with Bootstrap Icons' code points. The rules for the names the pass renames everywhere (form-group, text-right, help-block, img-responsive, btn-default, input-group-addon/-btn, visible-xs-inline) left it; `test_lbst_compat_sheet` fails if one comes back or the pass stops renaming it.
- Not mapped, so not covered: a secondary region's header and footer (rich text) and a region the pass could not finish.

## What does no rename reach?
- Left by hand: `form-inline` (its children are laid out more than one way, [[legacy-layouts-shapes]]).
- Looks of the theme's classic sheet, not names: bare inputs and selects without `form-control` (sign-in, Country), the cart's `mobile_stacked` table overflowing a phone, photos without `img-responsive` 500 px wide on a phone ([[legacy-layouts-output-pass]]). The system widget of the page type is the way out; the report names it.
- No schema was needed; a layout that belongs to one design would need a column (an `architect` question).
