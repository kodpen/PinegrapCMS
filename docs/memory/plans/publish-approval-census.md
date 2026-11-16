---
name: publish-approval-census
description: Plan publish-approval PA1 census, start file: the two questions left to the architect with facts and recommendations, and the parts
---

## Where are the parts of the census?
- The gate's merge as operations: `publish-approval-census-gate.md`.
- What `design_proposals`, apply, take-back and stale need for a person's proposal: `publish-approval-census-table.md`.
- Every place that lists or draws a proposal, the editor's save today, a shared component: `publish-approval-census-places.md`.
- A1 to A16, one line each: `publish-approval-census-features.md`.

## Can the gate's merge be written as the proposal's operations?
- Facts: `pg_designer_merge_restricted_tree()` returns a whole tree. What it takes from the editor is per node: `props.text`, the image address (`_pg_dm_take_image_src()`), `props._notes`, and inside an `_editable` area the whole subtree. The save also writes page fields outside the tree (title, description, keywords, meta tags, JSON-LD, canonical, comments, sitemap).
- The assistant's operations (`_pg_dai_op()` in `includes/designer_ai.php`) cannot carry these unchanged: `update` text runs `pg_design_ai_clean_inline()` or `strip_tags()`, refuses bound nodes, islands and a semantic node with children; `replace` takes HTML, so an area's props, bindings and settings are rebuilt or dropped; no operation sets a note or a page field.
- Recommendation: yes, as operations kept in `design_proposals.ops`, but an operation set of the new module's own (`text`, `src`, `note`, `area` with the subtree as JSON, `fields`), each with the value it replaces, applied by the module with the gate's own rules. Never through `pg_design_ai_apply_ops()`. Nothing is lost then; the cost is that every reader of `ops` branches on `agent`.

## One hook in ws_approval_settle() or a pass of the general job?
- Facts: `ws_approval_settle()` (`includes/workspace/approvals.php`) runs once, in the request of the answer that closed the rule, with `$actor_id` (0 for expiry). It already calls one optional function the same way (`ws_approval_task_note()`). A write there meets the page lock (`pg_collab_may_edit_page()` in `pg_design_ai_write_page()`) and can be refused while the approval stays "approved".
- A job pass would see the settled row later, run as `closed_by`, and could try a locked page again; it adds a state to watch and a delay the editor sees.
- Recommendation: the one hook, `function_exists()`-guarded, looking the change up by `design_proposals.message_id` = the approval's `message_id`. A refused write leaves the change waiting in the designers' list and says so in the bell; no retry loop. Expiry and "withdrawn" change nothing on the proposal.
