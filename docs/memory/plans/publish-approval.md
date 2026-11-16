---
name: publish-approval
description: Plan (owner 2026-11-16, agent channel message 5667 and poll 64): with a switch on, a content editor's change to a live page waits for a designer's yes; steps PA1 to PA6
priority: 6
---

## What is asked?
- The owner's words and the layout of the set: `plans/website-features.md`. It was item 3 of agent channel message 5657.
- A site with several editors wants a second pair of eyes: an editor changes a text on a live page, a designer sees what changed and says yes before visitors do.

## Which decisions bind it?
- Owner (2026-10-06, `workflow/decisions-made-product.md`): a published page's change goes live at once; "Save goes live" is not a finding.
- A content-level save (roles 2 and 3) is merged by `pg_designer_merge_restricted_tree()`: only texts and picture addresses outside the marked areas, the draft state and the times kept (`designer/designer-content-roles-save-gate.md`). On a live page it is live at once today.

## What is there today?
- A change that waits for a designer exists once: an assistant's proposal. `design_proposals` keeps its operations, the tree before and who asked; a designer applies it (`pg_design_ai_apply_to_page()`), takes it back (`pg_design_ai_revert()`), and one whose page changed meanwhile is stale. The Workspace draws its card on the message that asked.
- A Workspace approval request has approvers, an any-or-all rule and an end time (`ws_approval_create()`); a page tagged in its text is its record. `ws_approval_settle()` tells flows and webhooks; no product code hears it.

## What is built?
- The numbered features: `publish-approval-features.md` (A1 to A16). Steps: `publish-approval-steps.md`. Claim as `[plan:publish-approval] <step> — <topic>`; the rules: `website-features-rules.md`.
- In short: with the switch on, a content editor's save of a live page is kept as a waiting proposal instead of being written, and a designer applies or dismisses it with the tools the assistant's proposals already have. The Workspace approval request is a second way to say the same yes.

## May a site choose that an editor's change waits? (owner, 2026-11-16, agent channel poll 64)
- Owner (agent channel poll 64, message 5749), asked with today's behaviour named beside the question: "Evet, anahtarla seçilebilsin". The other choice was no approval at all.
- The decision of 2026-10-06 stays the rule and the default: the switch starts off on every site and is one site's own choice (A1, A16).

## What did the architect decide?
- Architect (2026-11-16, agent channel message 5763): the way through the assistant's proposal table is accepted; the census (PA1) goes to the architect, and a column on `design_proposals` is inside the owner's yes on tables if the census needs one.
- Still the architect's, after the census: whether the gate's merge can be written as the proposal's operations; the one hook in `ws_approval_settle()` against a pass of the general job.

## What is left out?
- A content editor's change inside a shared component (it stays live at once; the census says what holding it would take); a second designer's yes on a designer's publish; approval of a release bundle; collection records (the templates' own status rule holds them). Each is a later yes.

## Whose look?
- The editor's waiting state (PA3) and the designer's list (PA4): the owner's. The switch's card: the architect's.
