---
name: designer-prototype-comments-door
description: Prototype comments (prototype_comments): what a row keeps, the guest's door prototype_comment.php and its gates, the limits, plain text, delete with the design
module: prototype_comments
---

## What is a prototype comment?
- A `prototype_comments` row (`includes/fn/prototype_comments.php`), never a note in the page's tree: link, design (`style_id`), draft page, node (`_id` shape, at most 64), place inside the node (two percentages kept in hundredths, 0-10000), name, plain text, `parent_id` (0: first of a thread), `user_id` (0: a guest; a staff answer carries its id), created, resolved at and by. No address of the guest.
- A link takes comments only with "Allow comments" on (`prototype_links.allow_comments`, `pg_proto_link_comments_allowed()`); before the step runs `pg_proto_comments_ready()` is false and every write is refused.

## What does the guest's door check?
- `prototype_comment.php`, POST only, JSON answer. The link is named by id (`l`), never by its token (P9). Gates in order (`pg_proto_comment_door()`): method, same origin (`pg_consent_answer_same_origin()`), the session's form token, the link opens now, this session opened the link (`pg_proto_link_session_opens()`) with its password cookie valid and, for a team link, still role 0-2, "Allow comments", a draft page of the design the link shows (`pg_proto_link_page_shown()`).
- Revoked, expired, gone and unknown share one answer ('link', 404), as the viewer gives.

## Which limits apply?
- `pg_proto_comment_limits()`: name 1-60, text 1-1,000 characters; 10 comments in 10 minutes per address (`waf_rate_exceeded()` scope `prototype_comment`; past it a burst offence, 429); 200 open guest comments a link (409). The burst is counted only for a post that passed the door and the field check.

## How is the text kept?
- Plain (`pg_proto_comment_plain()`): valid UTF-8 only, line breaks `\n` (the name on one line), control characters and direction overrides taken out, trimmed. Markup stays as the characters typed and is escaped wherever it is shown.
- A place outside 0-100, not a number, or written otherwise than digits with an optional decimal part is refused.

## How do answers and resolving work?
- A guest's answer names `parent`: the first comment of an open thread of the same link and page; node and place are taken from it, never from the post.
- Resolve and Reopen change the thread's first comment and its answers together, so the open count leaves the whole thread. Deleting a first comment deletes its answers.

## When are comments deleted?
- With their design: every style delete path calls `pg_proto_comments_delete_design()` beside `pg_proto_links_revoke_design()` (a later design may get the id). The Recycle Bin's delete is one of them, so a restored design comes back without its links and comments. A revoked or expired link keeps its comments; `pg_proto_comments_delete_link()` is the links list's "Delete this link's comments".

## What does the designer's side read?
- `pg_proto_comments_of_page()` (all links), `pg_proto_comments_of_link()`, and `pg_proto_comments_page_stamp()`: the newest `created_at` over the open pages, for the presence heartbeat.
