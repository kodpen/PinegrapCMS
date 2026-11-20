---
name: prototype-comments-ping
description: The prototype viewer's keep-alive ping to the comment door (every 10 minutes), why the guest's session needs it, what the door does with it
module: prototype_comments
---

## Why does the viewer ping the door?
- The door's gates read the session the link was opened in (its form token and the mark past the gates). PHP drops a session idle for `session.gc_maxlifetime` (1440 seconds by default), and a guest who reads a page or writes a long comment makes no request.
- Before the bug hunt of prototype comments (2026-11-20), a guest idle past that had the comment refused ("Open the prototype again from its link, then write your comment.") and lost the typed text to the reload.

## How does it work?
- In comment mode `pg_proto_comments.js` posts `l`, `page_id`, `token` and `ping=1` every `cfg.ping` seconds (`pg_proto_comment_ping_seconds()`, 600), whatever the mode's switch.
- The door answers 204 once its gates pass (`pg_proto_comment_is_ping()`), before the fields, the burst and the write: nothing is written or counted. A refused ping keeps the session too: the request starts it.

Related: docs/memory/designer/designer-prototype-comments-door.md, docs/memory/designer/designer-prototype-comments-viewer.md
