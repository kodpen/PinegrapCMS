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
- Plan publish-approval, PA3 (#10188): done after DP19 (#10291): its marks are drawn on the new strip and in the lists as `page-strip-narrow-approval.jpg` shows (`designer/content-proposals-editor-side.md`). Owner (agent channel message 9546): no picture look is asked for it.
- Plan click-scroll-map, CM4b: not started before DP22; its "Clicks" switch goes into the new bar's View menu.
- Plan prototype-comments, PN3 (#10042): waited for DP22 and DP2, both landed. It adds nothing to the four areas: the Comments button sits in the top bar's actions beside the Assistant, the list floats over the right column as the Assistant's panel does, its colours are DP1's tokens (`designer/designer-prototype-comments-list.md`).

## What goes on?
- PV11a, PV11b and PV12 (DP24 waits for that plan's end), RB2b (#10191, the Publish sheet's row), LS2, MP3 and the steps after it, fixes. None adds a part to the four areas; a step that finds it must, stops and asks.
- Once DP1 (#10179) has landed, every colour written for the editor's chrome comes from its tokens.

## Who goes first?
- Free places go to DP17, DP2, DP11 and DP22 first once DP1 has landed.
- In `assets/js/style_designer.src.js` a new step of another plan does not claim a function a designer-panels step names. A PR open on 2026-11-17 keeps what it holds until it lands.
- `includes/designer_screen.php` beside PN3: `workflow/held-file-registry-rows.md`.

## Which drawings are missing?
- None. The page strip on a narrow screen and PA3's five marks on it (waiting, applied, dismissed, cannot be applied, the designer's count) are one sheet, `page-strip-narrow-approval.jpg`: as text `designer-panels-measures-7.md` (DP18, DP19) and `designer-panels-measures-8.md` (PA3).

Related: docs/memory/plans/designer-panels.md, docs/memory/plans/designer-panels-rules.md, docs/memory/workflow/decisions-made-product-71.md
