---
name: application-review-steps
description: Plan application-review, steps AR1a to AR8: pure rules, store, programme settings, reviewers and assignment, juror and correction links, combining, decisions, report and API, close-out
---

## Which steps are there?
- AR1a (S) first, no schema. Done (#10809, `core/application-review-rules.md`): new `includes/fn/application_review.php`, pure: eligibility rules, weighted sums, the mean, the leniency correction, the spread flag, balanced assignment, the shortlist cut. `tests/application_review_test.php` red first: a strict and a lenient reviewer, a reviewer with 4 scores, a pool of one, a conflict, a tie at the cut-off. Features: V3, V5, V6, V11 to V13 (the rules).

- AR1b (M) after AR1a (the owner said yes to poll 199, message 10845). #10832, draft, columns asked: the four tables as a migration step in the open version, the columns of `decisions-made-schema-44.md`, in the PR body, asked of the architect before ready; the erase of the four tables; the export of state, decision, combined and criterion scores, never jurors' notes or names (poll 200, `decisions-made-product-91.md`); their tests; the `audit_log` entity type. Features: V16 (privacy).

- AR2 (M) after AR1b: the programme settings screen under Forms (`validate_forms_access()` and the folder rule): period, categories, criteria and templates, rounds, blind, decision texts; the period check in the form's submit path; eligibility and its override. Features: V1, V2, V3, V5.

- AR3 (M) after AR2: reviewers and pools, the assignment run, conflicts, "My reviews" opened by membership with no Forms right, blind, the reminders. Features: V6 to V9.

- AR4 (M) after AR3: the external juror's link and page, its `waf.php` entry; the correction link. Features: V4, V10.

- AR5 (M) after AR3: combining, the consensus flag, ranking and the shortlist, the next round. Features: V11 to V13.

- AR6 (M) after AR5: decisions, the decision e-mails with their preview, the result on the submitter's view, the waitlist call. Features: V14.

- AR7 (S) after AR6: the report and Excel, the API routes with their types, `php tools/check_api_schema.php`, the two events through `pg_announce()` and their catalogues, the MCP tools. Features: V15, V16.

- AR8 (S) close-out: every V number checked against the code with its PR; `core/application-review.md` written; this plan's three files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- AR1a can start before the polls' answers. After AR3: AR4 and AR5. AR2 enters `includes/fn/custom_form.php` (the submit path) and AR4 `waf.php`: check the open PRs before claiming.

Related: docs/memory/plans/application-review.md, docs/memory/plans/application-review-features.md
