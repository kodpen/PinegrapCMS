---
name: designer-options-search
description: Options panel "Search or add an option…" (DP8, #10658): three tabs by label, section, CSS property, class prefix; results with path; Enter opens the row; no result offers the class
---

## What does the box find?
- A row of any of the three tabs by its label, its section's name, the CSS property it writes and its class prefix: "margin" finds the outer spacing rows (`m-*`, `mt-*`, `mb-*`…), "mt-3" the top margin row, "box-shadow" the Shadow row.
- Letters folded one for one by `PGOptionsSearch.fold()`: lower case, İ I ı i alike, ş ğ ç ö ü â î û as the plain letter, both in the typed words and in the labels.
- Order (`match()`): the words at the label's start, at a word's start in it, inside it; then the class prefix or CSS property; then the section's name; then each word somewhere. Inside a rank Content, Style, Advanced, then the panel's order. At most 50 drawn.
- A card without rows of its own (Translation, Quick Layouts) is one entry named by its title.

## Where do the prefix and the property come from?
- The part reads the drawn panel (`_osIndex()`): a row's select carries `data-stylegroup` (its classes) or `data-util` (a spacing row's prefix); `prefixOf()` takes the shared part up to its last dash, a screen-size infix dropped. `cssOf()` maps the prefix to Bootstrap's property (`text-` by its values: align, transform or color); a row stored as a property (`data-prop`: zIndex, fontFamily…) by its name.
- The index is kept on the panel's first child, so it is built once per panel drawing.

## How is it shown and used?
- Results 120 ms after typing (`_SD_OPT_SEARCH_MS`) in `#sd-props-results` under the box (a combobox): the section's icon, the label with the words marked, the path ("Style › Shadow and effect"), the property or `prefix*` it was found by, and a foot "↑ ↓ move", "Enter open the row".
- Up and Down move round the ends; Enter or a click (`_sdOptSearchGo()`): the box empties, `_sdOptReveal()` shows the row's tab with its card open, the focus goes to the row's control (a selected segment first). A card closed before the search gets "just opened" in its head (`.sd-just-opened`) until the panel is drawn again.
- A spacing row is hidden under the spacing box: Enter focuses its side of the box (`boxSide()`) once the part 'spacing' drew it; a control not in view leaves the focus on the card's head.
- `/` focuses the box where no field, text or code editor takes typing and no dialog is open (no handler had `/` before); the first Esc empties it, the second leaves it; the × (24 px) empties it. Under 300 px the placeholder is "Search or add…".

## What happens when nothing is found?
- The line of before, "No option matches “word”." (`_sdPropsSearchEmpty()`, wording kept), and under it "+ Add the class “word”" (`#sd-props-search-add`): the words joined by dashes, letters, digits, dashes and underscores only, not offered when it would start with a digit, every selected element holds it already, the view is read-only or a content-level lock holds (K4).
- A click adds the class to every selected element lacking it as one undo step (one element `saveState()` and `_sdRenderNode()`, several `_sdMcCommit()`), empties the box and says so in a toast.

## How does it work with the label filter?
- `filterPropertiesPanel()` still filters (`designer-options-panel-search.md`); at its end `_sdOptSearchRefine()` runs the part, which shows the rows found by property or prefix again and decides the "nothing found" line.
- The part (`pg_options_search.js` then `style_designer_optsearch.js`) loads on the first search; a call whose words the box no longer holds does nothing (`host.live()`).

## Where is it tested?
- `tests/designer_options_search_test.php`: the fold both ways, "margin" finding the outer spacing rows, prefix and property found, the path, Enter's target (tab, card, row) for a row in a closed card of another tab, the class offered, the part's registration and the core's hooks.
- Not tested: the list's drawing, keys and focus in a browser.
