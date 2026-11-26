---
name: prototype-approval-round-two
description: Prototype approval round two (PA3): "Show what changed" outlines, the viewer page list marks, verdicts in the Comments list and the Share prototype dialog count
module: prototype_verdicts
---

## What does "Show what changed" draw?
- Only when the verdict that stands on the page was given to an older version of the draft (`older`). `pg_proto_viewer_review()` sends `changes` {marks, removed}: `pg_proto_viewer_changes()` (pure) diffs the verdict's node map (`pg_proto_verdict_nodes()`) with the draft's tree now; marks are changed and added nodes in the draft's order, at most 500 each.
- The viewer's button "Show what changed" / "Hide what changed" sits before "Review this page". On: the comment message to the frame carries `marks` (node ids only); the frame script draws `.pg-proto-mark` outlines in the pins layer, under the pins, whatever the comment mode, and redraws on scroll and every 700 ms.
- The comment bar opens with "Outlined: changed since your last review." and "Not on the page any more (n)": n is the removed nodes; the row lists the threads left on removed nodes (`removedThreads()`) and, with the comment mode on, the lost ones. A removed node has no label stored, so only its threads are listed.
- Pins, their numbers and states stay as they are. A new verdict drops `changes` and the button.

## What does the viewer's page list say?
- On a link asking approval, each option ends in " · Approved", " · Changes requested", " · Not reviewed" or " · Changed since": `pg_proto_link_review_states()` → `pg_proto_review_page_states()` (pure), the newest verdict of this link per draft page against the draft's fingerprint now; only pages the link shows (`pg_proto_review_pages_shown()`), so a page a link for anyone does not show gets no mark (#11701).

## What does the designer see?
- Comments list (`style_designer_comments.js`): `pg_proto_comments_list_of_style()` adds `verdicts` per page group (`pg_proto_comment_list_verdicts()`, pure): the standing verdict of each link, link order, with `link_hint` and `older`. A page with verdicts and no comment gets a group. The head shows a chip per verdict (hint, Approved / Changes requested, day, "older version"); a Request changes note is the page's first thread on the Open tab, with a pencil instead of a number and no pin.
- The presence stamp is the newest comment id plus the newest verdict id (`pg_proto_verdicts_style_stamp()`): both only grow, so a verdict refreshes the list.
- Share prototype dialog: a link asking approval carries `review` {approved, pages, changes} (`pg_proto_review_tally()` over the design's drafts the link shows, as the hold and the reminder count them); the row says "Approved n of m pages" and a "Changes requested" badge when a page has that verdict on the draft as it is.

Related: docs/memory/designer/prototype-approval-review.md, docs/memory/designer/designer-prototype-comments-viewer.md, docs/memory/designer/designer-prototype-comments-list.md
