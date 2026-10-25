---
name: designer-prototype-share-link-frame
description: A prototype share link's frame: signed by link id (never the token), bound to the viewer's session; clicks go back through the viewer, new tabs by ?l=<id>; a failing page shows no reason
---

## How is the frame admitted?
- `pg_proto_link_frame_sign()` (prefix `prototype-link-frame:`) over design, page, sub, record, link id, expiry. Never the token: the design's scripts (an analytics tag) read the frame's address and links. The frame reads the link by `l` and checks it still opens.
- Bound like a person's (`pg_proto_frame_bind()`, `b=`, `pg_proto_link_frame_state()` with the kept session cookie) to the session the viewer ran in, signed out too: every viewer request starts one (init.php). An address read off the page opens nothing in another browser ('unbound': a 403 note, no offence). With cookies refused the frame is refused the same way.

## Where do its links go?
- A plain click on a prototype link is posted to the viewer (`go`), which opens it on its own `?link=<token>&page=N` address: no earlier session needed (gone after 24 idle minutes); that viewer binds a new frame. Such a move counts no use (designer-prototype-share-links.md).
- A new tab takes the href, `prototype.php?l=<id>` (`pg_proto_link_frame_base()`), opened only for a session marked past the gates (`pg_proto_link_session_mark()`: the token's hash): 303 to `?link=<token>` when the token reads back, else served by its id; unmarked: a 403 note, no offence.

## What does it show when a page cannot be drawn?
- Only "The page could not be drawn." (500, `pg_proto_frame_names_failure()`): anyone holding the link opens it signed out, and a PHP error's message can name a server file and line (a TypeError's "called in /path on line N"). The catch would print it whatever `display_errors` says.
- The reason goes to `data/temp/php_errors.log` (`pg_error_write()`: class, file, line, short trace; the address is the page's public one, never the signed frame's).
- A person's frame (roles 0-2) still names the reason; a page that came out empty says "it is empty." in both.

Related: docs/memory/designer/designer-prototype-share-links.md, docs/memory/designer/designer-prototype-viewer.md
