---
name: prototype-search
description: The Prototype view's search (PX4, #10561): Ctrl+F or the bar's magnifier finds frames by name and links by text, Enter and the arrows zoom to each match, Esc goes back
---

## What does Ctrl+F do in the Prototype view?
- Architect (2026-11-19, plan prototype-next): "Ctrl+F searches page names and link texts."
- (PX4 #10561) Ctrl+F (Cmd+F) opens a row at the top of the view, over the map: the box, the count, the match before and after, Close. The magnifier first in the canvas bar's Prototype tools ("Search pages and links (Ctrl+F)", DP24's `.sd-bp-ptools`) opens and closes it too, the way on a phone.
- It is taken only while the view is open and the focus is in it or in its zones of the canvas bar (`.sd-bp-pv`), or the pointer is over one of them and no other text field is typed in (`_sdProtoSearchKey()`). The Overview's tree search keeps its Ctrl+F: its listener runs first and the view's skips a key already taken (`defaultPrevented`). Elsewhere the browser's own find. In the view's own box Ctrl+F selects the words again; no rule sends a second Ctrl+F to the browser (none existed).

## What does it find?
- `PGProtoHotspots.searchOf(layout, query, label)`: a frame by the name it shows (`_sdProtoFrameName()`: "Untitled page", "Shared component 5"), each link by its row's text (a menu item as "Menu › Item"), a chip by its address ("Hidden page" for a hidden one). Frames in reading order (top to bottom, then left), each frame's name before its links, then the chips.
- Folded by `foldWord()`, the fold Suggest targets uses in the same file: case, the marks (ş s, ü u, é e) and the Turkish İ I ı i alike. No second fold.
- The frames with a match get a yellow ring (`.sd-proto-found`), the one gone to a wider one (`-found-on`); the links panel's matching rows a yellow edge. The count reads "5 matches", "2 of 5" or "No match" (the box's edge amber).

## How does one go through the matches?
- Enter or ArrowDown: the next; Shift+Enter or ArrowUp: the one before, round at either end (`searchKey()`, `searchStep()`). Typing alone moves nothing.
- A frame or a link is zoomed to with PX1's `_sdProtoZoomTo()` and selected; a link is picked in place (its row marked, the panel scrolled to it), not through `_sdProtoPick()`, which would move the view to the link's other end. A chip is brought into view (`view.reveal()`); PX1 never zooms to a chip.
- Esc in the box or Close: the marks go and the view from before the first match comes back, the zoom to a frame included; the focus goes to the map. The words stay for the next opening. Closing the view closes the search.
- A redraw (an arrow drawn, "Read the links again") searches again and stays on the match gone to while it is found.

## How is it checked?
- `tests/proto_hotspots_test.php`: `test_proto_hotspots_search_of_a_design`, `_search_step`, `_search_key` (pure), `_view_search` (the wiring and the CSS).
- Not tried in a browser when it was built.
