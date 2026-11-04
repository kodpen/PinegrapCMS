---
name: css-has-whole-document-restyle
description: Chromium restyles the whole document on every DOM insertion while a rule styles every child of a :has() element; the settings card rewrite, the Bootstrap 6 dist rewrite, the guard test
---

## What is the trap?
- A selector whose `:has()` sits on an element and whose subject is every child or descendant of it: `X:has(…) > *`, `X:has(…) > :not(.a)`, `X:has(…) > [class]`, `X:has(…) > .b > *`, `X:has(…) *`. One such rule in a loaded stylesheet is enough, even when no X exists on the page.
- Chromium (measured November 2026) then restyles the WHOLE document whenever any element is added or removed anywhere. A bare `<div>` appended to `<html>` cost the same as one appended deep inside a panel.
- The cost is elements x rules. On a small panel screen it is a few ms; on the visual editor (about 18,000 elements in the editor document, about 12,000 rules) it was 250-450 ms per redraw, and a click on the canvas redraws three panels: selecting an element on design 834 (Otel) took 0.9-1.6 s.

## What is not slow?
- `:has()` on the subject itself, including sibling forms: `.r > [class] > :is(.a, .a ~ *, :has(~ .a))`.
- A sibling combinator right after the `:has()` compound: `X:has(…) ~ .f > *`, `X:has(…) + *`.
- A named subject: `X:has(…) > .form-text`, `X:has(…) > label`. A tag subject costs in proportion to how many elements of that tag the page has (`> span` about 100 ms on the editor).

## How was the settings card rule rewritten?
- `backend.src.css`, the full-row catch-all of the settings card rows (`grid-column: 1 / -1`) was `….row > [class]:has(…):has(…) > *` and `….row > [class]:has(> .form-field > SW) > .form-field > *`.
- Now the predicate is on the child: the child is, or has a sibling that is, the label and the control (`:is(.form-label, .form-label ~ *, :has(~ .form-label))`), a field child the same way with the switch, and a field after a switch field (`.form-field:has(> SW) ~ .form-field > *`). Weight kept at (0,6,1) so the placements still win (`settings-card-layout-pitfalls.md`).
- Not reachable without the slow form: a field BEFORE a switch field in one cell. No settings card has a switch field beside another field (all 14 panes checked).
- Checked: computed grid placement, margins, paddings and box rectangles of every element of the 14 settings panes at 900 px and 420 px wide, old against new: no difference.

## What guards it?
- `tests/css_has_restyle_test.php` parses `backend.src.css`, `style_designer.css` and the Bootstrap 6 dist (`bootstrap.css`, `bootstrap.min.css`) and fails on any such selector. Known and accepted: `.ws-strip:has(.ws-catchup) .ws-pin-bar > :not(.ws-pin-text)`, inside the Workspace phone media query.

## How was the Bootstrap 6 rule removed?
- The dist (`assets/lib/bootstrap-6`, 6.0.0-alpha.1) had one such rule: `.form-field:has(> .check, > .radio, > .switch) > :not(.check, .radio, .switch) { grid-column: 2 }`. While loaded, every insertion on a panel page restyled the whole document; the editor's gain needed both rules gone (each alone: no gain).
- Since PR for issue #6619 the dist carries one local change: that selector became `.form-field > :not(.check, .radio, .switch):is(:has(~ :is(.check, .radio, .switch)), :is(.check, .radio, .switch) ~ *)` in `bootstrap.css` and `bootstrap.min.css`: same elements (a sibling of a check, radio or switch inside one `.form-field`), same weight (0,3,0), so the later `> .form-label` rule still wins. The `.map` files are untouched; nothing else in the dist changes.
- `document.txt` of the library holds a "Local changes" block (old rule, new rule, reason) so an update re-applies it; the guard test fails if an update brings the old form back. Only this library may carry a local change; the other vendored libraries stay copied unchanged.
- Measured with both rules replaced: an insertion 250-450 ms to about 5-10 ms; selecting on the canvas of design 834 0.9-1.3 s to 0.26-0.32 s.
