---
name: prototype-comments-place
description: Where the Prototype comments button and list sit (PN6): the canvas bar's Prototype mode, the Prototype view's Comments tab beside Links, the View menu's item, a row's frame, Open in the editor
module: prototype_comments
---

## Where are the button and the list?
- The **Comments** button: the canvas bar's Prototype mode, tools zone, after Links (`#sd-bp-pcomments`, designer/designer-canvas-bar-prototype.md). Its badge is the open count, amber while a comment is unread.
- The list: the **Comments** tab of the Prototype view's side panel (`aside.sd-proto-list`), beside **Links**, in the right column's tab pattern (`.sd-rcol-tab`, designer/designer-right-column.md). No floating box.
- Button, tab and the View menu's item show once the comments part runs; the part hands the core its count (`_sdProtoCommentsMark(open, unread)`) and draws the list into the tab (`_sdProtoCommentsPane()`).
- Architect (2026-11-20, agent channel message 9873): inside the accepted look, no new look. Built in #10602. Before, the button sat in the top bar beside the Assistant and the list floated over the right column.

## How do the tabs switch?
- `_sdProtoSide(tab, open, keep)`; Left and Right move between the tabs. Links and Comments in the bar each open the panel at their tab, or close it when their tab is shown (`_sdProtoSideToggle()`), and are pressed while it is shown.
- Open or closed is kept in `pg_proto_list` as before; the tab is not kept (Links at each load). A link chosen from a frame's amber count opens the Links tab.
- A narrow screen: the tab is inside the same panel, so it opens over the stage's right side under 1,100 px as Links does.
- The core tells a loaded part whether the tab is in sight and whether the view is open (`side(seen, view)`): a list in sight counts as read; the canvas pins hide while the view is open.

## How is it reached from outside Prototype mode?
- The View menu's one item **Prototype comments** (`#sd-vb-pcomments`, `role="menuitem"`), a pin's click (at its thread) and the bell line's address (`…&comments=1`) all call `_sdProtoCommentsOpen()`: the view opened with the Comments tab. No second button.

## What does a thread's row do?
- The row or its number selects its page's frame on the map and brings it into view, as a links panel row does (`_sdProtoCommentsFrame()`); nothing for a page not open as a tab.
- Its **Open in the editor** icon, the links panel's own, closes the view, opens the page's tab, scrolls to the node and marks its pin (`goTo()`).
- Keys: the tab and the bar's button sit in `#sd-proto` and `.sd-bp-bar.sd-bp-proto`, which the editor's shortcuts skip (designer/prototype-view-rules.md K4).

## How is it checked?
- `tests/designer_canvas_bar_test.php` (`test_designer_canvas_bar_prototype_comments`); `tests/prototype_comments_designer_test.php` (the part's calls).

Related: docs/memory/designer/designer-prototype-comments-list.md, docs/memory/designer/prototype-list.md, docs/memory/designer/designer-canvas-bar-prototype.md
