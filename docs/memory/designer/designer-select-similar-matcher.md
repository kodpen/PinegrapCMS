---
name: designer-select-similar-matcher
description: Why "Select similar" exists in the designer, what is never chosen, the pure matcher PGSelectSimilar.find() (ways of alike, places, left out), not across pages; SE1 #11530, SE2 #11649
---

## Why is there "Select similar"?
- Owner (2026-11-24): one of the ten level-1 features that keep the designer simple and quick (`workflow/decisions-made-product-108.md`, `designer-light-and-simple.md`): twelve cards changed with one choice, not twelve clicks with Shift or Ctrl.
- After the press it is the several-elements panel as it is (`designer-options-multiple.md`): one change is written to all, one undo step. The rows, the key and the line above the panel: `designer-select-similar-part.md`; the look: `designer-select-similar-drawing.md`.

## What is never chosen?
- An element the operator may not edit, the inside of a placed shared component, a system widget's kept parts (a slot the server fills). They are counted per reason and the line says how many were left out and why; never in the count.

## How does the matcher work?
- `assets/js/pg_select_similar.js`, `window.PGSelectSimilar.find(tree, target, { by, where, cls, mayEdit, keepsChildren })`, loaded by the `similar` part. Pure: a walk of a plain node tree, no element touched, no editor state read; a few milliseconds on the fixture page, nothing run before the row is pressed.
- `by`: `kindClasses` (start), `kind`, `oneClass` (`cls`, else any of the chosen one's classes), `component` (same `sharedId`). `ways(node)` gives the ones that apply, in menu order.
- `where`: `siblings` (beside it), `section` (nearest section, header, footer or aside; start), `page`.
- Open slots (children printed as designed): the editor's `_SD_SECTION_KEEPS_CHILDREN` and `_SD_BOOKING_KEEPS_CHILDREN` (guest, booking steps and form), copied in `KEEPS_CHILDREN`; the test holds them equal (PR #12242).
- Returns `{ ids, nodes, by, where, leftOut: { locked, shared, kept }, leftOutTotal, wider }`; `wider` is the next place that covers more, offered when nothing was found.

## What is it not?
- A change across pages: that is a variable or Find & Replace. No table or column was added.

## Where are the tests?
- `tests/designer_select_similar_test.php` runs the matcher and the part in Node.

Related: docs/memory/designer/designer-select-similar-part.md, docs/memory/designer/designer-select-similar-drawing.md
