---
name: designer-panels-differences
description: Differences between the Page Design Studio as built and its accepted designer-panels drawings: one list for the owner, fixes cut from it later
---

## What is this list for?
- Owner (2026-11-17, agent channel message 8922): the Page Design Studio looks and behaves differently from the pictures, in many settings; noted, not started (`workflow/decisions-made-product-73.md`).
- Architect (2026-11-17, message 8933): the close-out DP25 compares every landed step with its accepted picture, screenshot beside drawing, and brings the owner the differences as one list; fixes are cut from that list. Nothing below is work before he has seen the list.
- The close-out (#10760, 2026-11-20) checked every feature against the code and its tests (`designer-panels-numbers.md`) but took no screenshots: its cloud session had no database to open the studio. The comparison, step by step on dev.pinegrap.com beside each drawing (git history, `designer-panels-overview.md`), is still owed; its lines go below.
- Owner (2026-11-17, message 9287): when the higher-priority steps run out, the sessions may go into the page studio, "çizimlerdekilere göre bugün çok eksikleri var". Until then the list only grows; then it is cut into steps and given out, the connections' merges first (`workflow/plan-order-begun-first.md`).

## How is a line added?
- Whoever meets a difference adds one line under the next heading: the screen and its width, what the accepted picture shows, what the build shows, the step and the picture or message that shows it.
- A screenshot the owner drops in the agent channel goes onto the list too and opens no work by itself.
- A point an architect's look settled inside a step's own PR is not a difference: it was fixed before the step landed.

## Which differences are known?
- Page tabs at 390 px with a saved draft (DP19, #10291, the picture of message 9088): the open tab stands at its 60 px floor and its name reads one letter ("o…"), where sheet f (`page-strip-narrow-approval.jpg`) keeps seven ("kargo-v…"). The top bar holds seven buttons there (Pages, presence, undo, redo, Save, Publish, Preview) beside the sheet's five.
