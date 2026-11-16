---
name: designer-prototype-comments-list
description: The designer's side of prototype comments: the Comments button, pins on the canvas, the Prototype comments list, the presence stamp, the bell line
module: prototype_comments
---

## What does the designer see?
- The editor part `comments` (`assets/js/style_designer_comments.js`, #10042), roles 0 and 1 on an upgraded site (`includes/designer_screen.php` hands it over). It adds the **Comments** toolbar button (open count), numbered pins over the canvas of the open tab, and the **Prototype comments** list beside the canvas.
- The list covers the whole design, every link and draft page, grouped by page; **Open** and **Resolved** tabs; open threads first, newest first. A page's numbers are by first comment, oldest first, as the customer sees them.
- The pins show the open threads, or the resolved ones while the Resolved tab is on. A pin whose point is out of the frame's view is not drawn. Clicking a pin opens the list at its thread; clicking a thread's number switches to its tab and scrolls to the node.
- "Not on the page any more": told on the canvas for the open tab, from the page's tree for other tabs; nothing is said for a page not open as a tab.
- **Answer** writes a staff answer under the designer's name (`pg_proto_comment_answer()`); **Resolve**/**Reopen** the whole thread; **Delete** asks inline and takes the answers with it.

## How does it hear of a new comment?
- The presence answer carries `proto_comments`: the newest comment id of the design (`pg_proto_comments_style_stamp()`, idx_style), to designers only. One line in `_collabBeatNow()` runs the part with it once it is above 0; the part fetches the list (`proto_comments` sub-action) only when it moves. No second loop.
- The pins live in a fixed layer over the canvas frame in the editor's document, moved on canvas mutations, scroll and resize; nothing is added to the canvas document.

## What are the endpoints?
- `api.php` designer sub-actions `proto_comments`, `proto_comment_answer`, `proto_comment_resolve`, `proto_comment_delete`: designers only (not on the content operator's list), the comment must belong to the design named; each answers the fresh list. "Clean" for the page cache.

## What is the bell line?
- `prototype_comment` to the link's maker (`created_by`) for a guest's comment or answer, written by `prototype_comment.php` (`pg_proto_comment_bell()`), addressed (`target_user_id`), reference the comment. It opens `edit_system_style.php?id=…&page=…&comments=1`, which opens the list.
- At most one an hour per link. Architect (2026-11-16, agent channel message 6207): a `waf_rate_exceeded()` counter with the link id as subject, limit 1, window 3600, as `note_lock.php`; no column on the link.

## What is not here?
- "Delete this link's comments" (P11) belongs in the Share prototype dialog's links list (PN5); `pg_proto_comments_delete_link()` is ready.

Related: docs/memory/designer/designer-prototype-comments-door.md, docs/memory/designer/designer-parts-on-demand.md
