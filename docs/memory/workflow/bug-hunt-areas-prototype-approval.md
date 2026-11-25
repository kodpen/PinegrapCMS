---
name: bug-hunt-areas-prototype-approval
description: The bug hunt of prototype approval (2026-11-25, hunt 56, PA1 to PA6): the four faults fixed in #11701, the issue opened, what was read clean
---

## What was hunted?
- Hunt 56, named by the architect: prototype approval (`designer/prototype-approval.md`): the verdict door, "Publish only after approval" and "Publish anyway", round two's "what changed", the answer mail and the "Answer by" reminder, the two read-only API routes and the events, erase and export of `prototype_verdicts`.
- Left alone: prototype comments' guest side (hunted 2026-11-20), `style_designer.src.js`, `pg_sketch_builder.js`, the list of `tests/designer_parts_test.php`.

## What did #11701 fix?
- A verdict was sealed to the draft read when it was posted: a guest whose viewer still showed an older draft approved the designer's later changes unseen, and that approval released the publish hold. The viewer now posts the fingerprint it was drawn with (`seen`); a draft changed since is refused, 409 `changed` (`pg_proto_verdict_seen_ok()`). Test: `tests/prototype_approval_link_test.php`.
- A guest's note or comment starting a line with `::::::` or six backticks kept a second block start after `pg_proto_comment_task_words()` took one, so a flow writing it into a channel drew a block or a fence. Every block start at a line's head is taken. Test: `tests/prototype_events_test.php`.
- The same verdict sent twice (a second tab, a page drawn again) wrote a second row: a second bell line, receipt mail and event. A repeat of the standing verdict (verdict, name, note, draft) now writes nothing (`pg_proto_verdict_repeats()`).
- "Approved n of m pages" in the Share prototype dialog and the viewer's page marks counted drafts a link for anyone never shows (going live behind a sign-in), so the count never reached m of m while the hold held nothing. Both now count only shown pages (`pg_proto_review_pages_shown()`).

## What was left as an issue?
- #11702: the file explorer's Move and the page properties' Folder write `page_folder` directly, so a draft moved out of the Drafts folder goes live past the approval, experiment and bundle holds, keeping its `page_drafts` row. Outside the area, and whether they refuse or publish through `pg_page_draft_move()` needs a decision.

## What read clean?
- Door gates before a verdict (closed, expired, revoked or deleted link answer `link`; a link asking nothing `approval_off`); viewer page changes reload the shell, so the page posted is the page drawn.
- The hold's writers: the save (including `publish_from_draft`), the folder move, the scheduled job, bundle, experiment winner, content proposals, the API save (fields read from the stored page, so no `approval_override` from outside); role 0's reason and its Site Log line.
- Mails: the answer mail's counter is touched only when a mail could go; the reminder's once-a-day claim and 09:00-20:00 window. The answer mail counts answers since the guests last wrote, so it may count an answer an earlier mail told of (as documented).
- API routes: owner role, design check, cursor, filters cast to int; names and notes through `pg_proto_event_words()`. Erase blanks the name and note and the link's approver; export leaves out token, password and node fingerprints.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/designer/prototype-approval.md, docs/memory/workflow/bug-hunt-areas-prototype-comments.md
