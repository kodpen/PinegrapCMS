---
name: button-busy-state
description: Buttons never change their label or lock for a fixed time while a request runs; the window-level guard against double submits and double clicks (3 s); how an Ajax button disables itself.
---

## May a button say "Saving…" while it works?
- No. A button keeps its label during a request ("Kaydediliyor…", "Oluşturuluyor…" are not written) and is never locked for a fixed time: that kept screens that save over Ajax waiting long after the answer had come back. The old `data-loading-content` attribute was removed; do not bring it back.

## How are double submits stopped?
- `backend.src.js` (`window.pgSubmitGuard`, installed once per window) listens for `submit` and link `click` on `window`, so it runs after every handler on the element and on the document: a second submit of the same form, or a second click on the same link, within 3 seconds is cancelled. Nothing changes on screen, and the submitting button keeps its name and value in the request (a disabled button would drop them). Short on purpose: an answer that is a download leaves the page where it is and must be repeatable at once.
- Left alone: an event a handler already cancelled (a form sent over Ajax, a link handled by script), a target other than `_self`, `download` links, `#` and `javascript:` links, clicks with a modifier key.

## What does a button that sends its own request do?
- It is `disabled` exactly while its request runs, and enabled again on every path: success, an error answer and a network failure.

Related: docs/memory/ui/admin-screen-toolbar-rules.md
