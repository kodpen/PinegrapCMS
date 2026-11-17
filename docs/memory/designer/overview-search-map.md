---
name: overview-search-map
description: Designer Overview (DP13): the tree search part (name, text, class, id; Turkish letters folded; results with their path; Ctrl+F) and the page map at the tree's right edge
---

## What does the tree search find?
- "Search the tree…" (`#sd-ov-search-input`) looks in every element's name (the row's label), designer label, text (markup stripped), class (`props.cssClass`) and id, shared components' cached trees included once. It replaced the row filter `filterOverviewTree()`.
- Letters are folded one for one (`_tsFold()`): lower case, İ I ı i alike, ş ğ ç ö ü â î û as the plain letter; "ikon" finds "İkon". The plan's DP8 fold function had not landed, so this is the step's own copy; the close-out (DP25) may merge the two.
- Order (`_tsMatch()`): a name or label that starts with the words, then one that holds them, then id, class, text; the page's order inside each. At most 100 drawn; the count of all sits at the box's right end, where "Ctrl F" (⌘ F on a Mac) stands while it is empty. In a box under 230 px (the left column of a 1024 px window, a container query) the key hint gives way and the count stays; the input has `min-width: 0`, else it pushed the Overview wider than its column.

## How are the results drawn and picked?
- 120 ms after typing, `_sdTreeSearchSet(q)` puts `.sd-ov-searching` on `#sd-overview-section`: the path, the tree and the map hide (the tree is then drawn later, `_sdPanelDefer()`), `#sd-ov-results` shows.
- Each result: the row's icon (`_sdTreeIconHtml()`), the name (designer label, else name) with the words marked, after it the row hint (`_sdTreeRowHint()`) when more than one element shows that name, labels included, so three "App option" rows read "· Tally", "· Stride"; or the matching id, class or text cut around the words; and its path (section down to the parent; longer than four, the section, "…" and the last two).
- ArrowUp, ArrowDown move the active result (`aria-activedescendant` on the box, a combobox); Enter or a click selects the element (`_sdTreeSearchPick()`): the search ends, the canvas scrolls to it and its row is opened and shown. The first Esc clears, the second leaves the box.
- Ctrl+F (Cmd+F) goes to the box when the focus is in the Overview or the pointer is over it and no field outside it is being typed in; otherwise the browser's find. No handler had Ctrl+F before (the Find & Replace window opens only from its button).

## Where does the code live?
- The part `treesearch` (`assets/js/style_designer_treesearch.js`, `pg_designer_parts()`), loaded the first time the box searches; it reads the page through `_sdTreeSearchHost()` (root, children, fields, hint, icon, pick, count) and takes only `_sdT` and `esc` from the kit.

## What is the page map?
- `#sd-ov-map`, 14 px at the tree's right edge inside `.sd-ov-body`; drawn with two sections or more by `_sdTreeMapRender()` at each `renderTree()`. Places count the page in order: a section, then what it holds (a shared component placed as a section, its cached tree), as the section rows count.
- Blocks by size in percent of the map (`_sdTreeMapRanges(sizes, 3)`: none under 3 % while all have room); the selection's section is lit and a dot marks the selection (its nearest listed ancestor); a frame marks the range in view (`_sdTreeMapView()`: the first row in view to the first row under it), updated once a frame on scroll and on a selection in place.
- A click (`_sdTreeMapGo()`): the place at that height (`_sdTreeMapAt()`); its section opens if closed and the tree scrolls its drawn row, or its nearest drawn ancestor's, to the top. The map is `aria-hidden`: the section rows are the keyboard way.

## Where is the test?
- `tests/designer_tree_search_test.php`: the fold both ways and in place; name, label, text, class and id found and ordered; a result's path and its cut; the hint of a name shown twice; the map's blocks from the sections' sizes, the floor, a place and a height back to a place; the part's registration.
