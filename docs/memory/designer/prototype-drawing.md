---
name: prototype-drawing
description: The Prototype view's drawing from a mark (PV10, #10101): a mark's drag draws its arrow, an arrow head's drag re-points it, the marks' keys, the pointer lifting an arrow, a link named by its children
---

## How is an arrow drawn or re-pointed?
- (F21, PV10 #10101) A drag of a drawable mark of the selected frame draws its arrow: the press is kind `row`, and past 4 px the view's `move` turns it into the core's `connect`, so the frames and chips that may take it light up (`pgc-target`) and `connected()` writes it (designer/prototype-view.md).
- (F22) A press on the canvas or an arrow within 14 screen pixels of a shown arrow's head takes the nearest head (`_sdProtoHeadAt()`, `nearHead()`): a drag points the link elsewhere, a click picks it. A press on a frame stays the frame's: heads crowd on a frame's head bar, and a click there picked a link instead of selecting the frame.
- What is written: `_sdProtoConnect()` as before (the tab opened, the lock, `_sdMayEdit()`, undo, the shared and live page warnings, once per opening; designer/prototype-view.md). K1 to K4 are unchanged.

## What does the keyboard do?
- (F23) Tab reaches the selected frame's marks right after its frame (`tabIndex` 0 for `own` marks only, -1 for the rest). On a mark `markKey()`: Enter or Space picks, L, the menu key or Shift+F10 opens the target menu at the mark; Escape closes the menu, then the view. The panel rows keep the same keys. "Change target" and "Open in the editor" find the link by frame and port, so a link under the cut works too.

## What else helps to follow an arrow?
- The pointer on a mark or a panel row lifts its arrow (`_sdProtoLift()`): drawn last, 3 wide, the others faded to .25.
- A link without words of its own is named by what its children show (`nodeLabel(props, node)`, designer/prototype-hotspots.md): the shared header's logo link reads "Müşteri Hizmetleri", not "/anasayfa".

## How is it checked?
- `tests/proto_hotspots_test.php`: `test_proto_hotspots_head_and_mark_keys`, `_link_named_by_its_children`, `_view_draws_from_a_mark`.
- In headless Chromium on the Clinic template: a mark dragged onto a frame, a head dragged onto another, L on a shared part's mark and a choice; each wrote the link and showed its warning.
