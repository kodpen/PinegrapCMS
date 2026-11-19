---
name: designer-panels-measures-8
description: Plan designer-panels, publish approval's marks on the new page strip as text (`page-strip-narrow-approval.jpg`, lower half, owner 2026-11-17): the waiting pill, answer icons, the count, folded pages
---

## What is decided?
- Owner (2026-11-17, agent channel poll 132, message 8315): "Evet, böyle çıksın" on the sheet of discussion message 8298, `plans/designer-panels/page-strip-narrow-approval.jpg`, its lower half. PA3 (#10188, plan publish-approval) draws its marks from it once DP19 has landed (`designer-panels-additions.md`).
- With "Content changes wait for approval" off (Site Settings › Features) the strip draws none of them.

## Which marks are there?
- A tab carries one approval mark at most: after the page's name, before the three-dot menu. Which one it is and the notes it opens are PA3's. A click on a mark never switches the tab.
- The editor's own waiting change: the "Waiting for approval" pill ("Onay Bekleyen") with the hourglass: the draft pill's shape, the warning-soft ground, warning text. A draft's save never waits, so the two pills never meet on a tab.
- An answer is an icon: applied a check in a circle (success); dismissed an x in a circle (danger); cannot be applied a triangle (warning: it asks the editor to act, it is not a refusal).
- The designer's count: the same pill with the hourglass and the number. On the new strip the accent dot means changed and a chip's number means pages, so one sign says "waits for approval" in both roles.
- Sizes: the pill 15 px high, corner 6, text 9.5 px; an answer's icon 13 px; the target at least 24 by 24. Colours: `--sd-warning`, `--sd-warning-soft`, `--sd-success`, `--sd-danger`.

## What happens on a narrow strip?
- From step 4 of `designer-panels-measures-7.md` on, the pill loses its words and keeps the hourglass; its name is in the tip and in its note. The count stays as it is.
- A folded page's mark climbs to the chip that hides it ("+N", a group's chip, "+N grup"): for a designer the sum of the counts; for an editor the most important mark, in the order cannot be applied, dismissed, applied, waiting. An editor has no bell: without this an answer on a folded page is never seen.
- Where a chip shows its page count, the pill stands after the count.
- At step 6 no chip is left: the mark of the pages that are not open climbs to the top bar's Pages button by the same rule. The button carries a mark at step 6 only.
- A mark that climbed is part of its chip or button, not a target of its own: the click opens the chip's list or the Pages window. A mark is clicked only on its own tab.
- The mark stands on the page's row in every list: "+N", a chip's list, the Pages window.

## What is left out?
- A "waiting" filter in the Pages window is not drawn and not built with these marks (architect, agent channel message 8267).
