---
name: designer-prototype-comments-list
description: The designer's side of prototype comments: pins on the canvas, the list's threads and actions, numbers as the customer sees them, the presence stamp, the endpoints, the bell line
module: prototype_comments
---

## What does the designer see?
- The editor part `comments` (`assets/js/style_designer_comments.js`, #10042), roles 0 and 1 on an upgraded site (`includes/designer_screen.php` hands it over): numbered pins over the canvas of the open tab and the list; the core draws its button and tab from the count the part hands it (`_sdProtoCommentsMark(open, unread)`).
- Where the button and the list sit (the canvas bar's Prototype mode, the Prototype view's Comments tab, the View menu's item): designer/prototype-comments-place.md.
- The list covers the whole design, every link and draft page, grouped by page; **Open** and **Resolved** tabs; open threads first, newest first. A staff answer carries the Team badge the viewer shows.
- The pins show the open threads, plus the thread last opened in the editor whatever its state; they hide while the Prototype view is open. A pin whose point is out of the frame's view is not drawn. Clicking a pin opens Prototype mode with the Comments tab at its thread.
- "Not on the page any more": told on the canvas for the open tab, from the page's tree for other tabs; nothing is said for a page not open as a tab.
- **Answer** writes a staff answer under the designer's name (`pg_proto_comment_answer()`); **Resolve**/**Reopen** the whole thread; **Delete** asks inline and takes the answers with it; **Make a task**: `designer-prototype-comments-task.md`.
- Colours come from the chrome's tokens only (`designer-chrome-tokens.md`): a pin is `--sd-warning`, a resolved one `--sd-success`.

## Which number does a thread carry?
- The one the customer sees on its pin. The viewer reads one page of one link and numbers its threads by the first comment, oldest first, resolved ones counted (`pg_proto_comments.js`, `threads()`). So `pg_proto_comment_list_pages()` numbers per page and link (`n`), and the part only draws it.
- Two links' threads on one page can share a number: where a page has comments of more than one link (`links`), a thread shows its link's hint (`link_hint`, `prototype_links.token_hint`) as the Share prototype dialog lists it, and its pin's title names it.

## How does it hear of a new comment?
- The presence answer carries `proto_comments`: the newest comment id of the design (`pg_proto_comments_style_stamp()`, idx_style), to designers only. One line in `_collabBeatNow()` runs the part with it once it is above 0; the part fetches the list (`proto_comments` sub-action) only when it moves. No second loop.
- The pins live in a fixed layer over the canvas frame in the editor's document, moved on canvas mutations, scroll and resize; nothing is added to the canvas document.

## What are the endpoints?
- `api.php` designer sub-actions `proto_comments`, `proto_comment_answer`, `proto_comment_resolve`, `proto_comment_delete`: designers only (not on the content operator's list), the comment must belong to the design named; each answers the fresh list. "Clean" for the page cache. `proto_comment_delete` with `link_id` is the Share prototype dialog's Delete comments (`prototype-comments.md`).

## What is the bell line?
- `prototype_comment` to the link's maker (`created_by`) for a guest's comment or answer, written by `prototype_comment.php` (`pg_proto_comment_bell()`), addressed (`target_user_id`), reference the comment. It opens `edit_system_style.php?id=…&page=…&comments=1`, which opens Prototype mode at the Comments tab.
- At most one an hour per link. Architect (2026-11-16, agent channel message 6207): a `waf_rate_exceeded()` counter with the link id as subject, limit 1, window 3600, as `note_lock.php`; no column on the link.

Related: docs/memory/designer/prototype-comments.md, docs/memory/designer/designer-prototype-comments-door.md, docs/memory/designer/designer-prototype-comments-viewer.md, docs/memory/designer/designer-parts-on-demand.md
