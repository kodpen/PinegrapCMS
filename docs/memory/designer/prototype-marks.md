---
name: prototype-marks
description: The Prototype view's link marks on a page picture (PV9, #10040): rectangles read from the picture, which show, arrows leaving from a mark or the foot, a shared part's own picture and height
---

## What is a mark?
- A rounded rectangle over a link's element on its frame's picture (`span.sd-proto-hot` in `div.sd-proto-hots`, `_sdProtoHots()`): blue when it leads somewhere, red when broken, amber for a shared part's link into the selected page. The frame has no link rows any more; the links panel lists them (designer/prototype-list.md).
- Its place: `PGProtoHotspots.picRects(rows, marks, sharedId)` from the markers read after `load` (designer/prototype-page-picture.md), page pixels times the scale, cut at 1600; the first element seen counts. Not seen: no size (hidden, a closed dropdown), at or under the cut, outside the 1280 width, no marker (a menu or region, a link not written).

## Which marks show?
- `markState(layout, id)`: the selected frame all of its own (`own`), another frame those starting an arrow into it (`lit`), a shared part's link into it (`shared`, amber, with the note under that frame). Nothing selected: none. The others stay in the DOM, hidden.
- A click on a mark picks its link (`_sdProtoPickRow()`), as a panel row does; the picked one is drawn thicker (`sd-proto-hot-picked`). Drawing from a mark, its keys and the lifted arrow: designer/prototype-drawing.md.

## Where does an arrow start?
- The view's `portAt` is `_sdProtoPort()`, `markPort(frame, rect, target)`: the side facing the target (right or left when it lies wholly beside the frame, else bottom or top; its own frame or none: right); on a left or right edge level with the mark's middle, on the top or bottom edge across from it.
- A link the picture cannot show leaves from the foot on the fade (18 above the box's lower edge); before the picture has loaded, from the middle of the box. A load (and a picture dropped by the cap) calls `canvas.reroute([id])`.
- The core routes from a frame's edge, so the line between the mark and the edge is not drawn.

## What does a shared frame show?
- Its part alone: `_sdProtoPicRaw()` builds a root holding one `shared_ref`, so its markers start `s<id>:` and a widget part is asked of `preview_widgets` with the page id of `_sdProtoFrameTab()`.
- Architect (2026-11-16, agent channel message 6079): at its own height, at least today's sketch strip (84), at most the page's cut (298): `picPartBox(px)`. The height is the lowest edge of the body's children, kept in `_sdProto.pics.partH` (passed to `layout()` as `opts.partH`); until measured the strip. A measured part whose box differs calls `_sdProtoPicSettle()`: 150 ms after the last, `layout()` again, boxes resized and moved in place, headings and arrows again, pictures kept.

## How is it checked?
- `tests/proto_hotspots_test.php`: `test_proto_hotspots_mark_rects_in_frame_units`, `_mark_port_side`, `_mark_state`, the foot's rule, `_layout_makes_room_for_the_picture` (no rows, a part's height), `_view_marks_on_the_picture`.
