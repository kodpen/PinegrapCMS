---
name: prototype-approval-review
description: A prototype link asking approval (PA2): the Share prototype dialog's and the API's approval fields, the viewer's "Review this page" box, the verdict door, the approver's receipt mail, the bell line
module: prototype_verdicts
---

## How is a link made to ask approval?
- Share prototype dialog, under "Allow comments": "Ask for approval" (shown when the list answers `approval_ready`); checked, it shows Approver's name, Approver's e-mail (optional, "Sent the answers and a reminder"), "Answer by" (a date, min today, max the expiry day) and "Publish only after approval", and ticks "Allow comments".
- API `POST /design/designs/{id}/prototype-links`: `approval`, `approver_name`, `approver_email`, `answer_by`, `publish_rule`; a site before 11.0.276 answers 422 on `approval`. Lists and the API's link carry `approval`, `publish_rule`, `approver_name`, `answer_by`, never the address.
- One check for both: `pg_proto_link_plan_approval()` after `pg_proto_link_plan()`: name 1-60 on one line, e-mail optional and valid (255), `pg_proto_answer_by_check()` (errors `answer_by`, `answer_by_past`, `answer_by_after_expiry`), switches 0/1. Off: every field ignored. On: `allow_comments` 1. `pg_proto_link_issue()` writes the five columns only with comments, no bundle and `pg_proto_approval_ready()`.

## What does the approver see?
- Only on a link asking approval: `pg_proto_viewer_review()` adds `review` to the comment mode's settings (page label, the standing verdict of this link and page with `older`, limits, words); the shell draws "Review this page" beside Comment and a status line before it.
- The box takes the comment box's place (`frameBox()`): two choices "Approve this page" and "Request changes" (radios drawn as the bar's button), Note (optional; needed for Request changes, refused under the field), Your name (the comment box's stored name), Send.
- After a verdict the line reads "You approved this page on {date}" or "You asked for changes on {date}"; a verdict whose fingerprint is not the draft's now reads "Changed since your last review" (`reviewLine()`, pure). "Show what changed" and the page list's marks: `prototype-approval-round-two.md`.
- The address of the approver never reaches the viewer.

## What does the door do?
- `prototype_comment.php` takes a post with `verdict` after the comment door's gates and the ping (`pg_proto_verdict_is_post()`), before a comment's fields: `pg_proto_verdict_take()`.
- Refused: `approval_off` (403, the link asks nothing), `verdict`, `name`, `note` (400), `changed` (409: the `seen` fingerprint the viewer was drawn with, `review.fingerprint`, is not the draft's now, `pg_proto_verdict_seen_ok()`; the guest opens the page again), `rate` (the comments' burst per address, 429). Written: sealed with the draft's fingerprint and node fingerprints; answer 201 `{verdict}` in `pg_proto_verdict_public()` shape.
- The same verdict again (verdict, name, note on the same draft, `pg_proto_verdict_repeats()`) answers the standing one and writes nothing: no second bell line, receipt or event (#11701).

## Who hears of a verdict?
- Bell: `prototype_verdict` to the link's maker for every verdict (the comments' burst bounds a guest), reference the verdict, title the page's name; module `content`; opens the design at the Comments tab as the comment's line does.
- Receipt: `pg_proto_verdict_receipt()` mails the approver's typed address, queued, from the site's address: the verdict, the page, the design, the day, the note, the link's address when the site key reads it back. Never the password; no address, no mail. The answer mail and the "Answer by" reminder: `prototype-approval-mails.md`.

Related: docs/memory/designer/prototype-approval-store.md, docs/memory/designer/prototype-approval-fingerprints.md, docs/memory/designer/designer-prototype-comments-door.md
