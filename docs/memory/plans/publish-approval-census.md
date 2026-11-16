---
name: publish-approval-census
description: Plan publish-approval PA1 census, start file: the architect's decisions on the census (operations, the approval hook, no column, the list, the bell) and where the parts are
---

## Where are the parts of the census?
- The gate's merge as operations: `publish-approval-census-gate.md`.
- What `design_proposals`, apply, take-back and stale need for a person's proposal: `publish-approval-census-table.md`.
- Every place that lists or draws a proposal, the editor's save today, a shared component: `publish-approval-census-places.md`.
- A1 to A16, one line each: `publish-approval-census-features.md`.

## How is the gate's merge kept as the proposal's operations?
- Architect (2026-11-16, agent channel message 5857): the module's own operations (`text`, `src`, `note`, `area`, `fields`) in `design_proposals.ops`, `agent = 'editor'`, each with its `was`. Never through `pg_design_ai_apply_ops()`.
- Architect (2026-11-16, agent channel message 5857): apply writes no second copy of the gate. After the `was` check it puts the operations on the stored tree as it is then, passes the result through `pg_designer_merge_restricted_tree()` at content level, then writes as the designer. The stored operations are not trusted; the gate stays the one authority.
- Architect (2026-11-16, agent channel message 5857): a tree without `_id`s does not fall back to today's save (that would break the switch's promise): one operation `{op:"tree", tree, base_hash}`, stale when the page changed since.

## Who reads an editor's row?
- Architect (2026-11-16, agent channel message 5857): the assistant's readers never see it. The panel, `pg_design_ai_proposals_map()`, the API's proposal routes and the `pending_proposals` count, the dry run and the conversation cleanup take one shared condition, `agent <> 'editor'`; only the module reads editor rows. The readers do not branch on `agent`; the API and its schema do not change in this plan. Test: none of these readers returns an editor row.
- Architect (2026-11-16, agent channel message 5857): no design card is drawn on the approval request's message; the approval card's yes and no are enough.

## How does the Workspace answer apply it?
- Architect (2026-11-16, agent channel message 5857): one hook in `ws_approval_settle()`, `function_exists()`-guarded, finding the change by `message_id`. The module's function makes every check of Apply itself (the closing person is role 0 or 1 at that moment, stale, the page lock) and does not trust the approvers list. A refused lock leaves the change waiting, a bell line says so, no retry. A14: rule "any"; only a designer's yes applies, anyone else's is noted.

## Which column is added?
- Architect (2026-11-16, agent channel message 5857): none. The dismiss reason (A10, 300 characters) is its own key in the row's `steps` JSON: read only with the row, never searched. Withdrawn is `dismissed` with `decided_by = requested_by`; the ENUM stays. A new label in `PG_PAGE_REVISION_SOURCES` is not schema (`page_revisions.source` is VARCHAR(16)). The 30-day sweep takes only `agent = 'editor'` rows.

## Where are the waiting changes listed, and who is told?
- Architect (2026-11-16, agent channel message 5857): A7's list is the module's own, independent of the assistant and the Workspace: opened from the count on the page's tab, always shown to roles 0 and 1. The Assistant panel and `designer_ai.js` are not touched. Its look goes to the owner in PA4.
- Architect (2026-11-16, agent channel message 5857): A12 with the Workspace off: a third set in `pg_notification_off_kinds()` while the switch is on, counted by `pg_notification_off_lines_on()` too.
- A change inside a shared component stays out of the plan; it needs a yes of its own.
