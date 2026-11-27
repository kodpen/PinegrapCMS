---
name: designer-publish-sheet-held-row
description: The Publish sheet's held row: where the hold comes from (an open experiment's variant, a release bundle's draft, a draft waiting for a prototype link's approval), the labels and texts, Publish anyway
module: experiments_sheet
---

## Which pages are held rows?
- A draft an open experiment keeps as its variant, a draft an open, scheduled or partly published release bundle holds (`pg_page_bundle_hold()`), and a draft a prototype link holds until approved (`pg_proto_approval_holds()`, `prototype-approval-hold.md`). The rows have state `held` and a disabled box (`designer-publish-sheet.md`).
- The experiment comes first, then the bundle, then the approval; a draft in several reads as the first.
- A live page's new version (a bundle of one replacement) is no held row: its row reads "Replaces <page>" and can be checked (`designer-new-version-publish.md`).

## Where does the hold come from?
- The page load: `_design.experiments.pages` from `pg_experiment_editor_config()`, one entry per tab page by `pg_experiment_editor_page_state()`, or `pg_proto_approval_editor_state()` for the approval.
- A bundled draft's entry: role `bundle`, `held`, `bundle_id`, `bundle_name`, `label` ("Held by the bundle {var:1}") and `hold_text` (`pg_page_bundle_hold_text()` with the page's stored name). It has no `status`, so the top bar shows no experiment chip.

## What does the row say?
- `_pgPublishRows()` hands a held row's `label` and `note` (the hold text) to `pg_designer_publish.js`: the badge shows the label, the row's tooltip the note. Without a label the badge reads the markup's "Held by an experiment".
- A draft waiting for approval: role `approval`, `held`, `label` "Waiting for approval", `hold_text` naming the approver and the link's hint (`pg_proto_approval_hold_text()`), `override` for role 0. No `status`, no chip.
- `_pgHeldText()` gives the draft settings box's title and the toast of a refused Publish the bundle's text, or the experiment's. The row's words never name an experiment for a bundled draft.
- A pill that does not fit beside the page name goes under it, starting where the name starts, and its text breaks into lines; the name is cut only when it is longer than the row alone. One rule in the sheet's block of `style_designer.css` (the box out of the flow in the row's start padding), for every state's pill. Architect (2026-11-17, agent channel message 8148): accepted at 1440, this change at 390.
- The hold is read when the editor opens; a bundle changed or an approval given meanwhile shows at the next load. The server refuses the publish with its own text either way.

## What is Publish anyway?
- On a row with `override` (role 0 only) a "Publish anyway" button; pressed, it checks the row and opens a required reason field under it (500 characters). `PgDesignerPublish.plan(rows, keys, anyway)` publishes it with `reasons[key]`; `missing` keeps Publish selected off while a checked one has no reason. Unchecking holds it again.
- The bridge sets `p._approvalOverride` for that save only; `_pgSetDraftRun()` lets such a page through and `_pgTabsSerialize()` sends `approval_override`. The server takes it only from role 0 (`pg_proto_approval_override_reason()`).

Built in #10191 (release bundle step RB2b) and #11359 (approval). Tests: `tests/experiments_editor_bundle_hold_test.php`, `tests/designer_publish_sheet_test.php`, `tests/prototype_approval_hold_test.php`.
