---
name: designer-compare-live
description: Plan (owner 2026-11-24, level 2): a draft compared with the live page as two pictures under a slider, with what changed listed in plain words; steps CL1 to CL4
priority: 6
---

## What is asked?
- Owner (2026-11-24): ten more features, in levels, the designer kept simple and quick (the decision part 108; `designer/designer-light-and-simple.md`). This one: before publishing, what will change on the site is seen, not guessed.

## What is there today?
- History's Compare puts two versions' markup side by side with the changed lines (`designer/page-revisions-history-panel.md`). A designer reads it; a site owner does not, and neither sees the page.

## What is built?
- Compare gets a "Look" tab before its markup tab: the live page and the draft as two pictures of the whole page, one over the other with a slider handle between them; "Side by side" puts them next to each other. A chooser gives phone or desktop width.
- "What changed" beside the pictures, in plain words, most visible first: "Heading text changed", "Picture replaced", "Section added: Prices", "Section removed", "Moved up", "Look changed (3 elements)". A row scrolls both pictures to the place and outlines it; a row of a text shows the old and new words.
- "Compare with the live page" opens it from a page's row in the Publish sheet and from the page's tab menu. From History, any two versions are compared the same way.
- A page never on the site shows the draft alone and says so. A draft equal to the live page says "Nothing has changed".

## What must it keep?
- Both pictures are drawn by the server as a signed-out visitor sees the page, in frames with no script (`designer/design-live-pictures.md`): the live side from the version that is on the site, the draft as the Preview draws it. No visit is counted, nothing is written.
- The list comes from the two trees, matched by element id, by one pure reader; a change it cannot name is counted under "Other changes", never left out.
- Drawn only when opened; the second picture loads after the first is seen.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- CL1 (M) The reader, pure: two trees in, changes out (added, removed, moved, words, picture, link, look), each with its sentence and place. Done (#PRNUM).
- CL2 (M) after CL1 and DD17: the Look tab: two frames, the slider, side by side, the width chooser.
- CL3 (S) after CL2: "What changed" joined to the pictures; the rows that open it (the Publish sheet's row once PC4 of the publish check plan has landed).
- CL4 (S) Close-out: memory in `designer/`, this file deleted, the `[NEW]` line named.

## What does every step keep?
- No table or column. The drawing: `designer-drawings-levels.md`. Claim as `[plan:designer-compare-live] <step> — <topic>`.
