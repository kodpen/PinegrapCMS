---
name: prototype-approval-hold
description: "Publish only after approval" on a prototype link (PA4): which link holds a draft, every writer of a draft going live reading the hold, role 0's Publish anyway with its reason in the Site Log
module: prototype_verdicts
---

## When is a draft held?
- `pg_proto_approval_hold_pick()` (pure): a link of the draft's design, no bundle link, asking approval with `publish_rule`, not revoked and not expired, showing the page (`pg_proto_link_page_shown()`: a link for anyone shows only drafts going live in a public or guest folder), whose standing verdict (`pg_proto_verdicts_newest()`) does not approve the draft now (`pg_proto_verdict_approves()`). Every such link must approve; the first by id still waiting is named.
- `pg_proto_approval_holds($page_ids)` reads drafts only, the links and verdicts in a few queries; `pg_proto_approval_hold($id)`, `pg_proto_approval_hold_message()`. The fingerprint is the stored draft's (`pg_proto_draft_fingerprint_of_row()`).
- A third hold beside the experiment's and the bundle's, read only where a draft goes live: it never stops a bin or an API delete, so it is not inside `pg_experiment_variant_hold_message()`.

## Where is it read?
- `pg_designer_save_page()`, beside the experiment's hold: a stored draft saved as not a draft, or a save naming `publish_from_draft` (a writer copying a draft into a live page). Refused with `pg_proto_approval_hold_text()` in the dry run too.
- `pg_page_draft_move($id, false)` returns false for a held draft.
- Writers through them: the Publish sheet and every editor save; the API's `POST /design/pages/{id}` times and its MCP tool (the save, then the job); the scheduled job (`pg_page_schedule_run()`: stays a draft, time cleared, own Site Log line); content proposals' approval (`_pg_cp_write()`, the save).
- A release bundle's publish (`pg_page_bundle_check_item()`, `pg_page_bundle_write_item()`) and an experiment's winner (`pg_experiment_sheet_publish_winner()`) name the draft as `publish_from_draft`; the designs list's "publish every page" (`pg_designer_design_set_draft()`) counts `held` and its message says how many wait.
- `tests/prototype_approval_hold_test.php` lists them and fails for a new `pg_page_draft_move(…, false)` or `DELETE FROM page_drafts` outside the list.

## Who may publish anyway?
- Role 0 only, with a reason: the save's `approval_override`, one line, at most 500 characters (`pg_proto_approval_override_reason()`). Site Log: `pg_proto_approval_override_log()` with the page, the link `#id (hint…)` and the reason. No new right. Only the editor's Publish sheet sends it (`designer-publish-sheet-held-row.md`); the other writers refuse.

## What is left open?
- The hold reads the stored draft: a draft approved, then changed in the editor and published in the same save, is not caught by the server; the sheet reads the hold at load. The editor shows a verdict given meanwhile at the next load.

Related: docs/memory/designer/prototype-approval-store.md, docs/memory/designer/designer-publish-sheet-held-row.md, docs/memory/designer/prototype-approval-fingerprints.md
