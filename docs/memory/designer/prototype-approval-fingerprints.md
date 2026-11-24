---
name: prototype-approval-fingerprints
description: Approve this page and Request changes on a prototype share link, pure rules (PA1a): draft and node fingerprints, what changed, a verdict's state, Answer by, mail timing
module: prototype_approval
---

## Where are the rules?
- `includes/fn/prototype_approval.php`, pure (no database, no request), tested by `tests/prototype_approval_test.php`. The store (`prototype_verdicts`, the link's approval columns): `prototype-approval-store.md`; its columns carry the row keys below (`verdict`, `fingerprint`, `page_id`, `created_at`, `id`).

## What is the draft's fingerprint?
- `pg_proto_draft_fingerprint($tree_json, $settings)`: sha1 (40 hex, the `page_revisions.tree_hash` shape) over sha1 of the stored tree JSON and the settings a revision records (`pg_page_revision_settings_from_row()`) in key order. `pg_proto_draft_fingerprint_of_row($row)` reads both from a page row.
- Any tree change moves it, a moved node and a settings-only change included. It is worked out on read; nothing is written when the draft moves. Revisions record publishes only, so a draft has no revision row to point to.

## What are node fingerprints, and what changed?
- `pg_proto_node_fingerprints($tree)`: node id => sha1 of the node's own values (every key but `children`, keys sorted at every depth, lists kept in order), depth first. Ids not of the comment node shape (`pg_proto_comment_node_shape()`) are skipped; a repeated id keeps its first node; depth bounded at 200.
- `pg_proto_nodes_diff($before, $after)`: changed and added (in the later draft's order), removed (in the earlier's). A text change marks only that node, not its parents. A moved node is not listed: its own values did not change, while the draft's fingerprint does.

## How is a verdict checked and read?
- `pg_proto_verdict_kinds()`: `approved`, `changes_requested`. `pg_proto_verdict_check()`: name and note plain as a comment (`pg_proto_comment_plain()`), within `pg_proto_comment_limits()`'s name and text; Request changes needs a note, Approve does not. Errors: `verdict`, `name`, `note`.
- `pg_proto_verdicts_newest($rows)`: one standing verdict a page per link, the newest by `created_at`, then `id`.
- `pg_proto_verdict_state($verdict, $fingerprint)`: `not_reviewed`, `approved` or `changes_requested`, with `older` when the sealed fingerprint is not the draft's now ("for an older version"). `pg_proto_verdict_approves()` is the publish rule's test: approved and not older.

## How are "Answer by" and the mails timed?
- `pg_proto_answer_by_check($value, $expires_at, $now)`: empty (no date) or a real `YYYY-MM-DD` from today on and no later than the link's expiry day (0: never expires). Errors: `date`, `past`, `after_expiry`. Days are the site's (`date()`).
- `pg_proto_answer_by_reminder_due()`: the day before the date, pages undecided, not sent that day.
- `pg_proto_answer_mail_rate()`: one answer mail per link an hour, a `waf_rate_exceeded()` counter with the link id as subject, as the bell line (`designer-prototype-comments-list.md`). `pg_proto_answer_mail_due()`: a valid approver address, answers to tell of, the hour's mail not gone.

Related: docs/memory/plans/prototype-approval.md, docs/memory/designer/designer-prototype-comments-door.md
