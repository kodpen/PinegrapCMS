---
name: prototype-comments
description: Prototype comments start file: which file covers each part, Delete comments in the Share prototype dialog, P1 to P16 checked, where the Comments button sits still open
module: prototype_comments
---

## What is it?
- An agency sends its customer the share link of a design with "Allow comments" on. The customer clicks a spot of a page and writes what to change; the designer finds each comment pinned on that spot in the editor, answers, resolves, deletes it or makes a task of it.
- Built in five steps: the table and the guest's door #9915, the viewer's comment mode #10025, the designer's pins and list #10042, "Make a task" #10459, Delete comments and this file #10486.
- The owner's two answers (customers on one link see each other's comments with their names; a resolved comment is kept until the designer deletes it or the design is deleted): `workflow/decisions-made-product-59.md`.

## Which file covers which part?
- The `prototype_comments` row, the guest's door `prototype_comment.php`, its gates, limits and plain text: `designer-prototype-comments-door.md`.
- The viewer's comment mode (the Comment button, the box, pins, threads, "Not on the page any more"): `designer-prototype-comments-viewer.md`.
- The editor's Comments button, pins on the canvas, the Prototype comments list, the heartbeat's stamp, the bell line: `designer-prototype-comments-list.md`.
- "Make a task": `designer-prototype-comments-task.md`. What a later change must keep and the schema answer: `designer-prototype-comments-rules.md`. The share links: `designer-prototype-share-links.md`.

## How are a link's comments deleted?
- The Share prototype dialog's links list shows "Comments: n" (answers counted, `pg_proto_comments_link_counts()`) and **Delete comments** on a link that has any. It asks first; then every comment of that link goes, open and resolved, with their answers.
- A revoked link stays in the list while it has comments, after the others, with a Revoked badge and that button alone (`pg_proto_comments_unlisted_links()`, `pg_proto_links_revoked_of_design()`).
- No action of its own: the designer action `proto_comment_delete` takes `link_id` instead of `comment_id` (`pg_proto_comments_delete_link_of_design()`). The link must be one of the design named; a Site Log line gives the count and names the link `#id (hint…)`.
- The editor's Comments list, once loaded, is read again (the part's `reload()`): a deletion need not move the heartbeat's stamp.

## Were P1 to P16 checked against the code?
- Yes, at close-out (#10486): P1 #9915 and #10025 (the flag; the dialog and the API's create route), P2 to P6 #10025, P7 to P10 #9915, P11 #9915 and #10486, P12 to P15 #10042, P16 #10459.
- P3 said the frame tells the viewer the page: the viewer uses the page it drew and takes only the node and the place from the frame (P9).
- P11's "Delete this link's comments" is the button **Delete comments**; its question names the link's count.

## What is still open?
- Where the Comments button and the list sit for good. They keep their first place: the button in the top bar's actions beside the Assistant, the list floating over the right column. Decided (architect, 2026-11-20, agent channel message 9873): the button into the canvas bar's Prototype mode, the list as a Comments tab beside the links panel; step PN6 of `plans/prototype-comments-place.md` moves them after DP24 (#10507). Until it lands nothing moves.

## What was left out?
- A picture attached to a comment, comments on a live page, an e-mail to the customer when the designer answers: each waits for a later yes of the owner.

Related: docs/memory/designer/designer-prototype-comments-door.md, docs/memory/designer/designer-prototype-comments-list.md, docs/memory/designer/designer-prototype-share-links.md
