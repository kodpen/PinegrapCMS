---
name: calls-guest
description: A guest in a Workspace call (CL5): the guest page's bar with Join (no start), the call window on the guest page, call_sync, a guest room's call on the staff's side (no ring, no decline)
---

## What does the guest see?
- `workspace_guest.php`: a bar `#pg-guest-call` between the head and the conversation, hidden (`d-none`) unless the room holds a call the guest is not in. It reads "A call is on in this conversation." (telephone icon) or "A video call is on in this conversation." (camera icon) and holds one button, the call module's Join: "Join the call (n/4)", disabled "This call is full." at four.
- No Voice call or Video call button anywhere on the page: a guest never starts a call (the server refuses it too, `calls-server.md`). No ring: the bar shows at the page's next look (4 s, 15 s in a hidden tab).
- No bar for a guest who only reads (shared to read only), while calls are off (`calls` is null in the page's config), or where the browser has no WebRTC.
- Join asks the browser for the microphone (and the camera in a video call): no install, no account. The window is the Workspace's own (`calls-screen.md`, `calls-group.md`, `calls-share-stage.md`): tiles, mute, camera, share screen, devices, Hang up; its name is the room's title as plain text (no "Open the conversation").
- The guest is named in the call by the name the room knows (`ws_guests.name`, `ws_call_member_names()`); its own tile reads "You".
- Under 576px the card-size window sits at the top under the head, the full width less 1rem, so the writing box stays free; the stage still covers the view under 992px.

## How is it built?
- `pg_js_bundles()`: `workspace_guest.min.js` = `workspace_calls.js` + `workspace_guest.js`. The guest script gives the module its helpers; `callApi()` maps `ws_call_join`, `ws_call_signal`, `ws_call_sync`, `ws_call_leave` to the guest actions `call_join`, `call_signal`, `call_sync`, `call_leave` (no start in the map).
- `PGWsCalls.joinButton(channelId, name, hooks)`: the Join button alone; `channelCall()` shows and labels it from the `state` answer's `call` part. A guest's call is a group's (waits alone up to 5 minutes).
- `call_sync` (guests.php): the call part only, every second while in the call, as the panel's `ws_call_sync`.
- Texts: `ws_guest_call_strings()` = the two bar texts + `ws_call_js_strings()`, in the page's strings only while calls are on.
- Style: `assets/css/workspace_guest.css` draws the window as `backend.src.css` does, and the kit's `pg-button`, `pg-icon-button`, `pg-input`, `pg-field-label` with Bootstrap's colours, scoped to `.pg-guest`. A change of the window's look is made in both sheets.

## What changes on the staff's side?
- A guest room (kind `guest`) with one member or more carries the head's Voice call and Video call (`callable()`); its call is a group's (`isGroup()`): named by the room, waits alone up to 5 minutes, Join in the head while it runs.
- It rings nobody (`ws_call_rings_for()` leaves out kind `guest`) and cannot be declined (`ws_call_decline()` refuses): a decline would end a call the guest is in.

## How was it checked?
- Unit tests: `tests/workspace_calls_guest_test.php` (Join only under node, the bar's wiring, texts, bundle, no ring), `tests/workspace_calls_screen_test.php`, `tests/standalone_css_tokens_test.php`.
- Not run: a real guest and a staff browser in one call.
