---
name: designer-phone-preview-phone
description: "Preview on a phone" on the phone (PP3): the page a phone link opens, its 32 px line, the page list, the asking and its stop rules, the redraw after a save keeping the scroll
module: prototype_phone_view
---

## What does a phone link open?
- `prototype.php?link=<token>` for a link `pg_proto_link_is_phone()` marks draws `pg_proto_phone_shell_html()` (`includes/fn/prototype_phone_view.php`) instead of the desktop viewer's bar, after every share-link gate (team sign-in, use counted, session mark). Other links are unchanged.
- One dark 32 px line (page name, grey words, list button 44 px wide reaching 12 px below; a blue focus ring on its buttons and rows, #11855) over the sandboxed frame at the phone's width. No Bootstrap stylesheet, only the icons.
- Body (`pg_proto_phone_view_body()`): `frame`; `locked` (a page for signed-in visitors on a link for anyone: "This page is for signed-in visitors" / "Ask for a team link to see it on your phone."); `gone` (the page asked for is no longer a draft: "This page is no longer in the design.", list open, the line keeps the earlier name from sessionStorage); `error`. Only `frame` is watched.
- The list (`pg_proto_phone_view_rows()`): drafts in tab order, shown one ticked, locked ones greyed with a lock (anyone link only; two small reads per page via `pg_proto_link_page_access()`). A press opens `?link=…&page=N`: Back returns.

## How does it ask, and when does it stop?
- The inline script asks `pg_proto_phone_watch_query()`'s address (`designer-phone-preview-server.md`) with `since` = the drawn page's `page_timestamp`, read by the viewer. Only while `visibilityState` is not hidden; it waits the answer's `next` (10 s, 30 s after ten quiet minutes); `stop` (an hour after the drawing) shows amber "Paused" with "Update". Coming back to the tab asks at once (not while paused).
- "Update", or a pull down on the line while paused, draws the page again, which signs a new watch.
- A failed request: amber "No connection. Trying again…", asked again after the same wait, 30 s once ten minutes have passed since the drawing; an hour after it the page pauses by itself, as no answer brings the stop. One ask at a time: a return to the tab while an answer is on its way asks nothing more.
- A 404 (ended, run out, page gone, session changed) draws the page once; the viewer then shows what is left. A 404 on the first ask after such a redraw pauses: no loop when cookies are refused.

## How is the redraw done?
- `location.replace()` of the viewer's address of the page shown (`here`, with `page=`, so no use is counted): a new frame and watch are signed. Before it the parent asks the frame `{pgPrototype:"where"}` (300 ms at most); the frame (`pg_proto_frame_inject()`) answers its scroll, and gets `{pgPrototype:"scroll",y}` on `ready` and `load` of the new drawing. The line's lower edge is blue 2 s; "Updated just now", then "Updated N min ago".
- On load `history.replaceState()` puts `here` in the address bar, so a browser reload counts no use either.

## What does it cost?
- Phone page 13.2 KB HTML (4.5 KB gzip) with icons 87 KB, against the desktop viewer's 10.1 KB plus Bootstrap 381 KB (52 KB gzip) and icons. Asking: at most 60 + 100 asks in the hour, 66-byte bodies, none while hidden or after the stop.

Tests: `tests/prototype_phone_view_test.php` (the script run in Node against `tests/js/proto_phone_view_harness.js`). Not run on a real phone or over HTTP in the session that wrote it.

Related: docs/memory/designer/designer-phone-preview-drawing-phone.md, docs/memory/designer/designer-phone-preview-server.md
