---
name: designer-panels-additions
description: Plan designer-panels: additions of other plans to the Options column, the Overview, the page strip or the canvas bar wait for that area's new shape (owner 2026-11-17); which steps wait
---

## Do additions to the designer wait for its new interface?
- Owner (2026-11-17, agent channel poll 127, message 8168): "Dört alana dokunanlar beklesin". He had asked whether the new interface should not come before the additions (message 8167).
- Until an area's new shape has landed, no step of another plan adds a new control, mark, tab or list to it: the Options column, the Overview tree, the page strip, the canvas bar.
- The step that counts as the new shape: DP19 for the page strip (tab states), DP22 for the canvas bar (the zones and the View menu), DP5 for the Options column (the three tabs), DP11 for the Overview.
- Not chosen: "Hepsi beklesin" (no addition at all until the plan ends) and "Bugünkü gibi yan yana sürsün".

## Which steps wait?
- PA3 (#10188, publish-approval): a draft. The tab's pill, the three answer marks, the designer's count and their notes are drawn on the new strip after DP19, as `page-strip-narrow-approval.jpg` shows (`designer-panels-measures-8.md`); the owner's look comes then. Asked meanwhile in the same PR: agent channel message 8183.
  - Until DP19 it holds nothing on the strip: its lines in `_pgTabsRender()` and the all-pages list come out, its own block moves away from that function's head, and DP18 starts when that push is in (`workflow/held-file-parked-draft.md`, agent channel message 8619).
- CM4b (click-scroll-map): not started before DP22; its "Clicks" switch goes into the new bar's View menu.
- PN3 (#10042, prototype-comments): a draft. The Comments button and the list beside the canvas get their place from the architect once DP22 and DP2 have landed; the owner's look after that.

## What goes on?
- PV11a, PV11b and PV12 (DP24 waits for that plan's end), RB2b (#10191, the Publish sheet's row), LS2, MP3 and the steps after it, fixes. None adds a part to the four areas; a step that finds it must, stops and asks.
- Once DP1 (#10179) has landed, every colour written for the editor's chrome comes from its tokens.

## Who goes first?
- Free places go to DP17, DP2, DP11 and DP22 first once DP1 has landed.
- In `assets/js/style_designer.src.js` a new step of another plan does not claim a function a designer-panels step names. A PR open on 2026-11-17 keeps what it holds until it lands.
- `includes/designer_screen.php` beside PN3: `workflow/held-file-registry-rows.md`.

## Which drawings are missing?
- None. The page strip on a narrow screen and PA3's five marks on it (waiting, applied, dismissed, cannot be applied, the designer's count) are one sheet, `page-strip-narrow-approval.jpg`: as text `designer-panels-measures-7.md` (DP18, DP19) and `designer-panels-measures-8.md` (PA3).
- What PA3 takes from the sheet that it does not draw today: the designer's count as the hourglass pill, cannot be applied as the amber triangle, the pill without its words on a narrow strip, a folded page's mark on the chip that hides it and at the narrowest step on the Pages button, the mark on the page's row in every list.

Related: docs/memory/plans/designer-panels.md, docs/memory/plans/designer-panels-rules.md, docs/memory/workflow/decisions-made-product-71.md
