---
name: content-proposals-overview
description: Start file of waiting content changes (publish approval, card "Content Approval"): what it is, the owner's and architect's decisions that bind it, what is left out, which file holds each part
module: content_proposals
---

## What is it?
- With "Content changes wait for approval" on (Site Settings, Features, card "Content Approval"), a content editor's (roles 2, 3) save of a live page of a visual design is kept as a waiting change, not written. A designer (roles 0, 1) applies or dismisses it on Waiting Content Changes, or answers its Workspace approval request.
- Built by plan publish-approval: PA2 #9892, PA3 #10188, PA4a #9930, PA4b #9939, PA5 #9968; closed 2026-11-20 with all of its features A1 to A16 in the code.

## Which decisions bind it?
- Owner (2026-11-16, agent channel poll 64, message 5749): a site may choose by a switch that a content editor's change waits for a designer ("Evet, anahtarla seçilebilsin"). The switch starts off on every site; off, a save goes live at once as decided on 2026-10-06 (`workflow/decisions-made-product-59.md`).
- Architect (2026-11-16, agent channel message 5857): a `design_proposals` row with `agent = 'editor'` and the module's own operations, never `pg_design_ai_apply_ops()`. Apply puts them on the stored tree and passes `pg_designer_merge_restricted_tree()` again: the gate stays the one authority and stored operations are not trusted.
- Architect (2026-11-16, agent channel message 5857): no column (the reason in `steps`, withdrawn is `dismissed` by the editor). The assistant's readers only skip editor rows (`agent <> 'editor'`); the API and its schema, the Assistant panel and `designer_ai.js` stay; no design card on the approval request's message.
- Architect (2026-11-16, agent channel message 5857): one `function_exists()`-guarded hook in `ws_approval_settle()`; the module checks the closing person's role at that moment and every Apply check, and trusts no approvers list.
- Architect (2026-11-16, agent channel message 6485): the bell lines are addressed rows of the panel's own `notifications`, shown with the Workspace on or off; role 3 has no bell, so the Studio's page tab tells that editor.

## What is left out?
- Each needs a yes of its own: a change inside a shared component (saved by `content_update` in `includes/panel/shared_component.php`, live at once on every page using it; holding it needs a row naming the component, its own hash and lock on apply and take-back, the canvas laying it on each page); a second designer's yes on a designer's Publish; approval of a release bundle; collection records.
- No sweep deletes `design_proposals` rows: a closed change's 30 days are the list's window. A sweep would have to take only `agent = 'editor'` rows, or the assistant's history changes.

## Which file holds what?
- The switch, the held save, the operations, the assistant's readers: `content-proposals.md`.
- The editor's side in the Studio, Withdraw, the answer: `content-proposals-editor-side.md`; the tab's mark and the lists of pages: `content-proposals-editor-strip.md`.
- Waiting Content Changes, Compare, Apply, stale, Dismiss, take back, History: `content-proposals-designer-side.md`; markup in Compare: `content-proposals-compare-markup.md` (#10215); a deleted page: `content-proposals-deleted-page.md` (#10219).
- The bell lines and the count above the page list: `content-proposals-bell.md`. The Workspace request: `content-proposals-workspace.md`.
- Code: `includes/fn/content_proposals.php`, `includes/fn/content_proposals_compare.php`, `content_changes.php`; tests `tests/content_proposals*_test.php`.
