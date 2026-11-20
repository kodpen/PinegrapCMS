---
name: prototype-approval-steps
description: Plan prototype-approval, steps PA1a to PA7: pure rules, store, the link's approval settings, the viewer's verdict, round two, the publish rule, mails, events and API, close-out
---

## Which steps are there?
- PA1a (S) first, no schema: new `includes/fn/prototype_approval.php`, pure: the draft's fingerprint (tree and settings, `sha1` as `page_revisions.tree_hash`), node fingerprints (a node's own values), changed, added and removed nodes, a verdict's state, the "Answer by" check, the hourly mail rule. `tests/prototype_approval_test.php` red first: a moved node, a text change, a settings-only change, a removed node. Features: A2, A3, A4.

- PA1b (M) after PA1a: the five `prototype_links` columns and `prototype_verdicts` (poll 206, `workflow/decisions-made-schema-46.md`) as a migration step in the open version, their columns brought to the architect before ready; the five privacy registrations and `pg_contact_holders()`; verdicts deleted with their design and with a link's comments (`pg_proto_comments_delete_design()`, Delete comments); their tests. Features: A1, A2 (the store).

- PA2 (M) after PA1b: the Share prototype dialog's approval fields and the API create route's (`designer/designer-prototype-share-links.md`); the viewer's "Review this page" and its door (the comment door's gates); the verdict's receipt mail; the bell line to the link's maker. Features: A1, A2, A3, A6 (receipt), A7 (bell).

- PA3 (M) after PA2: round two in the viewer ("Show what changed" outlines from the node marks comment mode already draws, `designer/designer-prototype-comments-viewer.md`); the verdicts in the designer's Comments list and the dialog's links list. Features: A4, A7.

- PA4 (M) after PA2: the approval hold beside `pg_experiment_variant_hold_message()` and `pg_page_bundle_hold()`, read where the server already refuses a held draft; a test listing every writer of a draft going live (`prototype-approval.md`) that fails for one without the hold; the held row "Waiting for approval" (`designer/designer-publish-sheet-held-row.md`); role 0's override with its reason in the Site Log. Features: A5.

- PA5 (S) after PA2: the answer mail (from `pg_proto_comment_answer()`, hourly per link by a `waf_rate_exceeded()` counter as the bell's) and the "Answer by" reminder in the general job. Features: A6.

- PA6 (S) after PA3: the three events through `pg_announce()`, their catalogues and payload fixtures (`workspace/workspace-flows-adding.md`), the guest's words quoted; the read-only API routes with their types, `php tools/check_api_schema.php`; the read-only MCP tools (verdicts, comments). Features: A8.

- PA7 (S) close-out: every A number checked against the code with its PR; `designer/prototype-approval.md` written and named in `designer/prototype-comments.md`; this plan's three files deleted; no `[NEW]` line (an addition to prototype comments), the `[SCHEME]` item named in PA1b.

## Which steps run side by side?
- PA1a first. After PA2: PA3, PA4 and PA5. PA2 and PA3 enter `style_designer.src.js` and `pg_proto_comments.js`, PA4 `includes/fn/designer.php` and the Publish sheet: check the open PRs before claiming.

Related: docs/memory/plans/prototype-approval.md, docs/memory/plans/prototype-approval-screens.md
