---
name: prototype-comments-place
description: Plan (architect 2026-11-20, agent channel message 9873): the Prototype comments button moves into the canvas bar, the list becomes a tab beside the links panel; one step, PN6
priority: 6
---

## What is asked?
- The open point of `designer/prototype-comments.md`: where the Comments button and the list sit for good. Today the button is in the top bar's actions beside the Assistant and the list floats over the right column (`designer/designer-prototype-comments-list.md`).
- Architect (2026-11-20, agent channel message 9873): a place inside the look the owner accepted, no new look.

## What is decided?
- The button: in the canvas bar's Prototype mode, in the tools zone (`.sd-bp-ptools`, `designer/designer-canvas-bar-prototype.md`), shown only in Prototype mode, with the unread mark as a badge. It leaves the top bar.
- The list: docked in the Prototype view's side panel as a second tab beside the links panel, "Links" | "Comments", drawn with the right column's own tab pattern (`designer/designer-right-column.md`). The floating box goes. The button opens that tab.
- A thread's row selects its page's frame on the map, as a links panel row does. Its "Open in the editor" icon, the links panel's own, closes the view, opens the page's tab, scrolls to the node and marks its pin (today's `goTo()`).
- The pins over the Design canvas stay as they are. A pin's click opens Prototype mode with the Comments tab at its thread.
- Reach from outside Prototype mode: one item "Prototype comments" in the View menu, which switches to Prototype mode with the tab open. No second button. The bell line's address (`…&comments=1`) opens the same way.
- A narrow screen: the tab follows what the links panel does there.
- Nothing else changes: Open and Resolved, Answer, Resolve, Reopen, Delete, "Make a task", the numbers, the heartbeat's stamp and the endpoints stay.

## What is built?
- PN6 (M) after DP24 (#10507) has landed: the move, one PR.
  - Existing files: `assets/js/style_designer_comments.js` (`drawButton()`, `buildPanel()`, `toggle()`, `goTo()`, the pin's click), `assets/js/style_designer.src.js` (the Prototype view's side panel and its tab, the tools zone of `_sdProtoBuild()`, the View menu's item), the designer's stylesheet for the `sd-pc-` classes.
  - `style_designer.src.js` is held by designer-next (DX2, DX3) and other open steps: never open beside a PR on its Prototype part.
  - Texts through `_sdT()`, the Turkish with `php tools/tr_add.php`. Keys checked against `designer-panels-rules.md` K4 and K9: the bar's buttons in Prototype mode never reach the canvas's shortcuts.
  - Tests: the comments' own tests keep passing; `tests/designer_canvas_bar_test.php` gains the button's place and its badge; the tab's switch and the View menu's item where the designer tests reach them. `node --check` on both scripts.
  - No schema, no endpoint, no picture approval (`workflow/look-pictures-none.md`).

## How does the plan close?
- The step's own PR: `designer/designer-prototype-comments-list.md` and `designer/designer-canvas-bar-prototype.md` say the new place, `designer/prototype-comments.md` loses its open point, `designer/prototype-list.md` names the tab, and this file is deleted.
