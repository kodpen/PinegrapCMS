---
name: designer-prototype-comments-viewer
description: The prototype viewer's comment mode for a share link with "Allow comments": node marks in the frame, the Comment button, the box, pins and threads, "Not on the page any more", what the frame may say
---

## Which links get the comment mode?
- Only a share link with "Allow comments" (`pg_proto_frame_comment_mode()`: the link row's flag and `pg_proto_comments_ready()`). The staff viewer, a link without it and every served page draw as before: no node marks, no comment script (tests/prototype_comment_mode_test.php).
- "Allow comments" is set when the link is made: the Share prototype dialog's checkbox (shown once the site keeps comments, `comments_ready` in the `prototype_links` answer), the designer's `prototype_link_create` and the API create route (`allow_comments`, a site before the step answers 422). Not changed later. Lists show it (`comments`, API `allow_comments`).

## How does the frame get its node marks?
- The stored page body has none. In comment mode `prototype.php` draws the saved tree again with `pg_designer_audit_render(true)` and hands it to the render as the SEO check does (`pg_seo_draft_override()`, read only under `pg_seo_rendering()`); widgets drawn in the render are marked too. Switched off in `finally`.

## What may the frame say?
- `pg_proto_frame_comment_script()`: in the mode a click follows nothing and is posted as `place` (nearest `data-pg-node`, the click inside it as two percentages, and where in the frame for the box). A pin click posts `pin` {id}; `missing` lists the pins whose node is not drawn (absent or zero size).
- The viewer (`assets/js/pg_proto_comments.js`, bundle `pg_proto_comments.min.js`) takes `place` only as node (the door's shape), x, y in 0-100 and two pixel numbers (`PGProtoComments.place()`); `pin`/`missing` only name threads it holds. Page, link, name and text never come from the frame. The frame gets pins as numbers and places, never a name or text.

## What does the guest see?
- The Comment button (count of open threads), its bar: the hint and "Not on the page any more" with each lost thread. The box opens beside the spot (a sheet at the bottom on a phone): Name (kept in the browser's localStorage) and Comment; a pin opens its thread with names, times, a Team badge on staff answers, and an answer form while it is open ("This comment was resolved." otherwise). Resolved pins are grey.
- Comments come with the viewer for the page drawn (`pg_proto_viewer_comments()`, `pg_proto_comments_of_link_page()`), never the link's whole list; a sent one is added from the door's answer. Each page change reloads the viewer.

Related: docs/memory/designer/designer-prototype-comments-door.md, docs/memory/designer/designer-prototype-share-link-frame.md
