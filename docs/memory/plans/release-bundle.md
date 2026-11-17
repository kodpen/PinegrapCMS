---
name: release-bundle
description: Plan (owner 2026-11-16, agent channel message 5667 and poll 63): several pages prepared as drafts, previewed, published and taken back together; steps RB1 to RB8
priority: 6
---

## What is asked?
- The owner's words and the layout of the set: `plans/website-features.md`. The proposal is agent channel message 5662.
- A campaign or a rearrangement changes the home page, three inner pages and adds two new ones. They are prepared over days while the site stays as it is, looked at together, go live at one moment, and can be taken back together.

## Which decisions bind it?
- Owner (2026-10-06, `workflow/decisions-made-product.md`): a published page's one button is Publish and the change goes live at once; a separate working copy for a live page was deliberately not built.
- Owner (2026-10-19, `designer/page-revisions.md`): a revision records what was published, never a draft; a restore is a new publish.
- Owner (2026-11-02, `designer/page-experiments-model.md`): an experiment's variant is a draft page edited with the designer as it is; no copy of the tree; it cannot be published or binned while its experiment is open.

## What is there today?
- A draft is a whole page in the Drafts folder (`designer/page-drafts.md`); a time publishes one page (`designer/page-schedule.md`); the Publish sheet holds an unchecked page out of one save, only while the editor is open (`designer/designer-publish-sheet.md`).
- A draft standing for a live page exists once: an experiment's variant, copied by `duplicate_page()`, put onto the live page by "Publish the winner" through `pg_designer_save_page()` (`pg_experiment_sheet_publish_winner()`).
- `prototype.php` plays a design's drafts with their links rewritten. An assistant's proposal keeps the tree before and refuses a take-back once the page was saved again (`pg_design_ai_revert()`).

## What is built?
- The numbered features: `release-bundle-features.md` (B1 to B18). Steps: `release-bundle-steps.md`. Claim as `[plan:release-bundle] <step> — <topic>`; the rules: `website-features-rules.md`.
- In short: nothing is staged inside a page. A bundle is a named list of drafts, some standing for a live page as a variant does; publishing writes each through the page save that exists and remembers how to take it back.

## May a draft stand for a live page outside an experiment? (owner, 2026-11-16, agent channel poll 63)
- Owner (agent channel poll 63, message 5748), asked with the decision of 2026-10-06 named beside the question: "Evet, geçebilsin". The other choices were a bundle of new pages only, and no bundle.
- The decision of 2026-10-06 stays whole (B17: no working copy, a live page's Publish goes live at once); a bundle uses the draft an experiment's variant already is for a second purpose.

## What did the architect decide?
- Architect (2026-11-16, agent channel message 5763): a failure while writing takes back what was written and leaves the bundle open (B9); no copy for a page whose experiment is open (B3); the used replacement goes to the Recycle Bin (B8); the share link stays the last step.
- Still the architect's: the two tables' columns, in RB1's draft PR.

## What is left out?
- Shared components, menus and collection records in a bundle (B18). Each is a later yes.

## Whose look?
- Owner (2026-11-16, agent channel poll 107, message 7134): "Evet, böyle çıksın" to the Bundles screen (RB2 #9998).
- Owner (2026-11-16, agent channel poll 106, message 7125): "Evet, böyle çıksın" to the publish and take-back dialogs (RB3 #9999); their words: `workflow/decisions-made-product-67.md`.
