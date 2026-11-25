---
name: designer-select-similar-part
description: "Select similar" in the designer: the menu rows, the S key, the palette row, the `similar` part and the line above the Options panel with its two lists; what ends the line
---

## Where is it offered?
- Canvas menu: under "Select Parent". Overview row menu: first before "Move…", "Duplicate", "Delete". Command palette (`act_select_similar`) with the element's kind at the right. Key S on the canvas and in the editor (`_sdSimKey()`); Keyboard shortcuts row "Select the elements like the selected one" under Selection.
- Only for one selected element that is not the page (`_sdSimOffered()`); with several selected there is no row and S does nothing. No bar button, no row in the Options panel's three-dot menu.
- A context menu row may carry `key`: drawn as a `kbd` at the row's end and set as `aria-keyshortcuts` (`showCtxMenu()`). A palette row may carry `meta`, a muted word before its key.

## How does it run?
- The part `similar` (`includes/designer_parts/similar.php`, `when` always): `pg_select_similar.js` (the matcher, `window.PGSelectSimilar`) then `style_designer_similar.js`. Loaded on the first press (`_sdSimOpen()`); nothing is counted before it.
- The core hands a host (`_sdSimHost()`): the tree the element lives in (the page's, or its shared component's), `mayEdit` (not locked, no locked ancestor, at content level `_sdCanEditNode()`), `select(nodes)` and `say` (`_sdStateSay()`).
- The press chooses "same kind and classes · in this section", the pressed one first and keeping its name tag. Choosing is no undo step. Nothing is remembered between presses.

## When does the line stand?
- `_sdSim` holds the chosen ids. `_sdPropsHead()` calls `_sdSimHead()`, which asks the part to put the line first in the head while `_sdSimHolds()`: every selected element is one the press chose. Ctrl and a click takes one out ("11 of 12 similar"); a plain click, a cleared selection or an element it did not choose ends it.
- Nothing found: the element stays chosen alone with its own panel, the line says "No other element like this one here" with a button to the matcher's `wider` place, or "… on this page" with none.
- Left out: a second row "3 left out: 1 you may not edit, 2 inside a shared component." (third reason "a system widget’s own parts"), zero ones not named.

## What do the lists do?
- The two words open a list under them (`.sd-sim-menu`, at most 252 px, kept in the window): Alike by (the matcher's `ways()`), Where (beside it, in this section, on this page). Each row's count is counted when the list opens, the chosen one counted; "none" rows are dimmed. "One of its classes" with several classes opens a second list of the classes.
- Keys: the words are Tab stops; Enter, Space or Down opens; arrows move; Enter chooses; Right opens the class list, Left closes it; Escape closes and keeps the selection.
- First use: one line under it with "Got it", gone after "Got it" or the first change (`localStorage` `sd_similar_tip_seen`).

## Narrow panel?
- `.sd-sim-wrap` is a size container: under 300 px the count takes its own row and the dots go.

Test: `tests/designer_select_similar_test.php` (the matcher, the part under Node, the core's rows and key).

Related: docs/memory/designer/designer-select-similar-matcher.md, docs/memory/designer/designer-select-similar-drawing.md, docs/memory/designer/designer-options-multiple.md, docs/memory/designer/designer-parts-on-demand.md
