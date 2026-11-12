---
name: canvas-bridge
description: A live page's data bridge (#8947): page to shell to panel to site and back, the message shape, the canvas named by the card and never the page, the bounds and the stop, the who call, the proofs
module: guarded_frame
---

## How does a page ask the site?
- Never by itself: the page has no connection (`platform/guarded-frame.md`). It posts `{pg: 'canvas-data', id, call, args}` to its parent, the shell. The shell (`pg_guarded_frame_bridge_script()`, script mode only) passes it to the panel; the page card (`assets/js/workspace_page_card.js`) sends the panel action `ws_page_call` {message_id, call, args as JSON text}; the answer goes back the same way.
- Why through the panel: the shell is an opaque origin with `connect-src 'none'`; a request from it would carry no Lax session cookie. The panel has the reader's session.
- The answer: `{pg: 'canvas-data', id, ok: true, data}` or `{…, ok: false, error: {code, text}}`. A refused call is an answer for the page (`ws_action_ok` with `answer`), not a panel error; a failed request answers `failed`.

## How is a page kept from asking in another canvas's name?
- The canvas is the message id of the card whose frame the shell is (`frameOf(event.source)`), never a field of the request: `canvas`, `message_id` or anything in `args` are not read for it.
- The shell takes a request only from its own document frame and an answer only from its parent, and passes an answer only for a request still waiting; the panel takes the bridge's messages only from a frame a card made. A page posting to the top window or into another canvas's shell reaches nothing.

## What are the bounds?
- In the shell (`PG_GUARDED_FRAME_BRIDGE_*`): an id is a string of 1-64 characters or a number, else the request is dropped unanswered; a call name `[a-z][a-z_]{0,23}`, arguments an object, else `invalid`; arguments over 16 KB as JSON, or 8 requests already waiting, `limit`; an answer over 1 MB reaches the page as `limit`.
- More than 120 requests in a minute: the shell removes the page's frame and posts `{pg: 'canvas-data', stopped: 'limit'}`; the card closes and shows "The page was stopped: it asked the site too often." with Open again.
- The site checks again (`ws_page_bridge_request()`): the same name and size rules, the call among `ws_page_bridge_calls()`, else `unknown_call`. The numbers are first values; CO5 measures and sets them.

## Which calls are there?
- `who`: the reader's display name (`ws_person_name()`), nothing else of them.
- Each answer is for the person looking (`ws_page_bridge_answer()`): a team member who reads the channel, a message not deleted whose file is a live page, else `not_found`. A guest's room page has no Open, so no bridge.

## How is it proven?
- Unit: `tests/workspace_page_bridge_test.php`, the site's check and the shell's script run under Node against stand-in windows (forged canvas fields, strangers, answers to nothing, the bounds, the stop).
- Browser, by hand: `node tests/js/guarded_frame_proofs.js`, pages `bridge` (a request through the shell is asked for canvas 11, whatever it names; straight to the panel or into canvas 22's shell nothing is taken) and `flood` (the stop).
- Not proven in a browser: the real page card; its part is the stand-in panel's, checked in the unit test by its text.
