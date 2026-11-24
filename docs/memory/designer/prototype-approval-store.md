---
name: prototype-approval-store
description: Prototype link approval store (11.0.276): five prototype_links columns, prototype_verdicts, deleted with the design and by Delete comments, privacy erase and export
module: prototype_verdicts
---

## What does the step add?
- `upgrade_2026_11_0_prototype_approval()` (11.0.276), approved by poll 206; the columns as the architect agreed them: the decisions list, schema part 49.
- `prototype_links`: `approval`, `publish_rule` (TINYINT 0/1), `approver_name` VARCHAR(60), `approver_email` VARCHAR(255), `answer_by` DATE NULL (no date). Every link written before asks nothing.
- `prototype_verdicts`: `link_id`, `style_id`, `page_id`, `verdict` (`approved` | `changes_requested`), `author_name` (typed by the guest), `note` ('' for none), `fingerprint` (40 hex), `node_fingerprints` (JSON id => sha1, MEDIUMTEXT), `created_at`. Keys `idx_link_page (link_id, page_id, id)`, `idx_page (page_id, id)`, `idx_style`. No address, IP or contact of the guest.
- Older verdicts stay; the standing one per page and link is the newest (`pg_proto_verdicts_newest()`, `prototype-approval-fingerprints.md`).

## Which functions read and write it?
- `includes/fn/prototype_verdicts.php`: `pg_proto_approval_ready()`, `pg_proto_link_approval($link)` (a row's five settings; `publish_rule` only with `approval`; a zero or NULL date is ''), `pg_proto_link_asks_approval()`, `pg_proto_verdict_row()` (from `pg_proto_verdict_check()`; null when a part is missing), `pg_proto_verdict_save()`, `pg_proto_verdicts_of_link()`, `pg_proto_verdicts_of_page()`, `pg_proto_verdict_nodes()`.
- A node map keeps only ids of the comment node shape and 40-hex values; an empty map is stored as `{}`.

## When are verdicts deleted?
- With their design: `pg_proto_comments_delete_design()` calls `pg_proto_verdicts_delete_design()` first, so every style delete path takes them.
- With a link's comments: `pg_proto_comments_delete_link()` (the Share prototype dialog's Delete comments) calls `pg_proto_verdicts_delete_link()`; its Site Log line still counts comments only.

## What does a person's erasure and export do?
- The scope's `prototype_link_ids` are the links whose `approver_email` is one of the person's addresses. Entry `prototype_verdicts` (found by those links) keeps the verdict and blanks `author_name` ({name}) and `note`; entry `prototype_link_approvers` (table `prototype_links`, by `approver_email`) blanks the name and the address, so no review mail goes any more. Verdicts run first.
- Export section `prototype_reviews`: the links naming the person with each verdict (design name, page, verdict, name, note, fingerprint, times); token, hint, password and node fingerprints left out.
- Inventory: `prototype_links` (person `approver_email`) and `prototype_verdicts`, both `contract`, `until_erased`.
- No contact id is kept, so `pg_contact_holders()` has no entry, and there is no retention rule: verdicts are kept as prototype comments are (the decisions list, schema part 49).

Related: docs/memory/designer/prototype-approval-fingerprints.md, docs/memory/designer/designer-prototype-comments-door.md, docs/memory/designer/prototype-approval.md
