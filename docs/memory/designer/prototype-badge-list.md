---
name: prototype-badge-list
description: The Prototype view's amber count on a frame's head as a button (PX3, #PXNUM): it lists the frame's links without a target, each opened in the links panel
---

## What does a press on a frame's amber count do?
- Architect (2026-11-19, plans/prototype-next.md): "the 'No link here' badge becomes a button listing the target-less links". Read as the amber count (F29, designer/prototype-problems.md): only it stands for links; "No link leads here" (F27) is about a page, has no links to list and stays a label.
- (PX3) The count is a button (`aria-haspopup="dialog"`). A press opens `.sd-proto-problems` under it (above it when the view has no room below) with only that frame's links without a target (`PGProtoHotspots.emptyOf(problems, frameId)`); a second press, Esc, a press elsewhere or a wheel zoom closes it.
- A row picks its link (`_sdProtoPick()`), opens the links panel when it is closed (not kept in `pg_proto_list`) and puts the focus on the row's "Choose a page" box, where Enter opens the target menu; a link that may not be drawn gets the focus on its row (`_sdProtoEmptyGo()`).

## How do the keyboard and a phone reach it?
- The canvas leaves the button its clicks and keys (the core's `ownClicks`). Tab reaches it on the selected frame, after the frame and before its marks; on other frames a press reaches it.
- Its reach is 6 px wider each way for a finger (`button.sd-proto-badge-empty::after`).

## How is it checked?
- `tests/proto_hotspots_test.php`: `test_proto_hotspots_empty_links_of_a_frame` (the pure `emptyOf()`), `_view_badge_lists_links_without_a_target` (the wiring and the CSS).
- Not tried in a browser when it was built.
