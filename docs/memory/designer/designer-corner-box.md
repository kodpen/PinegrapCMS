---
name: designer-corner-box
description: Style › Border and corner's corners as one drawing (DP7b, #10633): sides, all corners, each corner as the browser rounds it, Shift and Alt, what it writes (today's radius classes) and reads
---

## What does Style › Border and corner show?
- Border, Color and Width rows as before, then one drawing of the corners: a number on each edge (the side's radius class, its two corners), the all-corners value in the shape's middle, each of the four corners numbered and drawn with the radius it gets. Today's five rows (Radius, Top Radius, Right Radius, Bottom Radius, Left Radius) stay in the body, hidden (`.sd-rd-rows`), for the count, the row reset and the search.
- No screen-size chooser: radius classes have no size forms.

## Why sides, not one number per corner?
- Bootstrap 5.3 has no class for one corner; D18 keeps today's classes (K1). A side writes `rounded-top-*`, `rounded-end-*`, `rounded-bottom-*`, `rounded-start-*` (0 to 5, as today's rows); the corners show the result.

## How is a value changed?
- As on the spacing box (`designer-spacing-box.md`): click and type, drag (8 px a step), Arrow Up / Down, Delete. The middle takes 0 to 5, "circle" or "pill" ("daire", "hap" are read too) and writes `rounded-*`; a side takes 0 to 5.
- Shift writes the opposite side too; Alt writes the one all-corners class and takes away every side class. The middle without Alt changes only the all-corners class. Each change is one undo step.
- Grey: shown from the all-corners class ("from all corners") or the default ("–"); filled accent: the side's own class; "~": the selected elements differ. Clearing a grey side writes nothing.

## How is each corner read?
- In the stylesheet's order (rounded, -top, -end, -bottom, -start; a later class wins a corner): top start = start, top, all; top end = end, top, all; bottom end = bottom, end, all; bottom start = start, bottom, all.
- The bare `rounded` / `rounded-top` and side `-circle` / `-pill` classes a page holds are read (bare = 2) and kept until that side is written; writing all-corners or clearing it takes the bare `rounded` too.

## Where is it?
- `assets/js/pg_corner_box.js` (window.PGCornerBox), pure, second source of the part `spacing` (`style_designer_spacing.min.js`: spacing module, corner module, part). The part's `corners(host)` draws into `<div class="sd-spb-host sd-rdb-host sd-bm-wrap" data-sd-rdbox>`; `_sdSpBoxBind(nodes)` binds both boxes with the same host (`_sdSpBoxHost()`), so several elements and content level work as on the spacing box. A design without Bootstrap removes it (`.sd-bm-wrap`).
- `tests/designer_corner_box_test.php`: every side and value written as today's row's class and read back; each corner as the browser; Shift, Alt, the middle; clearing; an unchanged list kept. Not unit-tested: the drawing, drag and focus in a browser.
