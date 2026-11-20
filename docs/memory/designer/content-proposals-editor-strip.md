---
name: content-proposals-editor-strip
description: Waiting content changes on the Page Design Studio's page strip: the tab's mark, its sizes, a folded page's mark climbing to a chip or the Pages button, the mark in every list of pages
module: content_proposals
---

## What does the page's tab show?
- One mark after the page's name, before its three-dot menu (`_pgContentChangeBadge(p)` in `_pgTabHtml()`), as owner poll 132 drew it (`page-strip-narrow-approval.jpg`; PA3, #10188). With the switch off the server hands no rows and nothing is drawn.
- Which: the own waiting change, the hourglass pill "Waiting for approval" (its note with Withdraw, opened only by a click); else the newest answer not closed, for 30 days, as a 13 px icon: applied (check, success), dismissed with its reason (x, danger), cannot be applied (triangle, warning); else, for roles 0 and 1, the count as the same hourglass pill with the number, opening `content_changes.php`.
- From `pg_content_proposals_studio_rows()` (pure); nobody else's change reaches an editor; a Manager gets no count. An answer's note opens by itself once per load (`_pgContentAnswerAuto()`, called by `_pgTabsRender()`).
- Sizes: the pill 15 px high, corner 6, 9.5 px, the draft pill's shape; colours DP1's `--sd-warning`, `--sd-warning-soft`, `--sd-success`, `--sd-danger`; the tab's mark is a target of at least 24 by 24. A click on it never switches the tab.
- The wordless marks are named (`_pgContentMarkName()`): "Applied", "Dismissed", "Cannot be applied", "N waiting changes" (architect, message 8183).

## What happens on a narrow strip and in the lists?
- From step 4 on (`#sd-tabs-list[data-step]`) the pill keeps its hourglass only; its name stays in the tip, the aria-label and its note. The count stays.
- A folded page's mark climbs (`_pgContentClimb()`, pure): to its group's chip, "+N", "+N groups" / "N groups", and at step 6 only to the Pages button. A designer gets the sum of the counts; an editor the most important mark: cannot be applied, dismissed, applied, waiting.
- A climbed mark is part of its chip or button, not a target; the click opens the chip's list or the Pages window. On a chip it stands after the count, the pill without its word.
- Every list's row carries its page's mark: "+N", a group's list, the Pages window (`_pgContentChangeBadge(p, true)`); a row of "+N groups" carries its group's climbed mark.

Related: docs/memory/designer/content-proposals-editor-side.md, docs/memory/plans/designer-panels-measures-8.md
