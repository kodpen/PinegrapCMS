---
name: guarded-frame
description: The guarded frame (LP1, #8802): how a stored page or a mail's HTML is shown in the panel: the shell, srcdoc, the two modes and their policies, the two messages, live_page.php, the browser proofs
module: guarded_frame
---

## What is it for?
- Showing a document the panel did not write (an agent's live page, `plans/agent-live-page.md`; a mail's HTML, WM12) inside the panel without letting it reach the panel, its cookies or the outside. `includes/fn/guarded_frame.php`; built in #8802.

## How is it built?
- The panel frames one answer, the shell (`pg_guarded_frame_shell()`), with the sandbox `pg_guarded_frame_panel_sandbox($mode)` names. The shell holds the document in `<iframe id="pg-frame" srcdoc>` (escaped), sandboxed by `pg_guarded_frame_inner_sandbox($mode)`.
- Why srcdoc and not a second address: a request from an opaque-origin frame is cross-site, so the Lax session cookie would not ride on it; srcdoc needs no request, and the document inherits the shell's policy.

## What are the two modes?
- `script` (a live page): shell policy `sandbox allow-scripts`, inline script and style only, pictures, fonts and media only as `data:`/`blob:`, every other source `'none'`, `form-action 'none'`, `frame-src 'none'`. Both frames lack `allow-same-origin`: two opaque origins. The site appends `pg_guarded_frame_reporter()` to the page: it posts the page's height.
- `static` (a mail): the document's frame has no `allow-scripts` and the policy allows only the shell's script by nonce, so nothing of the document runs. The shell keeps the site's origin (`sandbox allow-scripts allow-same-origin`) to measure the inert document beside it (ResizeObserver).
- Neither mode allows top navigation, popups, forms, downloads or modals; `Permissions-Policy` shuts camera, microphone, geolocation, payment and the like.

## What are the two messages?
- Panel to shell: `{pg: 'guarded-frame', width, height}`, taken only from `window.parent`; in `script` mode handed to the page as the same object.
- Shell to panel: `{pg: 'guarded-frame', height}`, a whole number up to `PG_GUARDED_FRAME_MAX_HEIGHT` (100000). The panel takes it only when `event.source` is the shell's frame window: the page can post to `top` too, and those are dropped. The card bounds the height further (LP2).

## Which route answers a stored page?
- `live_page.php?message=<id>[&mode=static]`: a panel session, the Workspace on, a member who may read the channel (`ws_can_read_channel()`), the message not deleted, its file `.html`/`.htm` of at most 500 KB (`PG_GUARDED_FRAME_MAX_BYTES`), read with `pg_file_reader_path()`. The id must be plain digits (`pg_guarded_frame_page_id()`).
- A request whose `Sec-Fetch-Dest` is not `iframe` (or missing) is refused: the shell is never a page of its own. Every refusal is the same 404 line, nothing of the page.
- The answer replaces the firewall's own policy (`header_remove()` of the report-only one).

## How is it proven?
- Unit tests: `tests/guarded_frame_test.php` (headers of each mode, shell markup, id and name refusal).
- Browser: `node tests/js/guarded_frame_proofs.js` (Playwright, by hand, not in CI). `guarded_frame_server.php` serves the real shell, a stand-in panel and an outside origin that logs every hit.
- The pages in `guarded_frame_pages/` try cookie, storage, parent, top, a panel action, outside scripts, pictures, fonts, style sheets, frames, fetch, XHR, beacon, socket, event source, worker, form, window, download, moving top or itself, a forged height; the mail tries script, event attributes, `javascript:` links, a nested srcdoc and a refresh.
- A blocked self-navigation shows Chrome's error page in the frame: the page is gone, nothing left the frame.

## What does it not cover?
- WebRTC: no CSP directive stops a peer connection, so a page could learn the reader's IP through STUN. It holds no secret of the reader's; a later step may close it if wanted.
- Outside pictures in a mail ("Show pictures") and links in a new tab are WM12's: they widen `img-src` and add `allow-popups` only in that mode.
