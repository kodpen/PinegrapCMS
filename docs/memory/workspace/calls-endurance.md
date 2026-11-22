---
name: calls-endurance
description: Workspace calls on a phone and a changing network (CL8, #11156): call buttons at phone width, window places, ICE restart, a vanished member out after 90 s, Join here
---

## How does a call look on a phone?
- The head's Voice call and Video call stay in a narrow head (no `ws-head-wide` on `.ws-call-head`); the search, info and tour still move into the channel menu. A narrow head's Join button cuts its words at 9rem.
- Under 576px the window at card size sits at the top under the panel's bar, across the width less 1rem, so the writing box stays free (the guest page did so already, `calls-guest.md`).
- It never runs off a short screen: `max-height` of the view less its margins (`100dvh`), scrolling inside. A phone on its side (landscape, height 500px or less) keeps it in the right half (`min(24rem, 48vw)`), three or four tiles in one row. Both sheets: `backend.src.css` and `workspace_guest.css`.
- A shared screen still covers the view under 992px (`calls-share-stage.md`).

## What keeps the route when the network changes?
- An ICE restart (`createOffer({ iceRestart: true })`) by each pair's offerer only (`restartDue()`, pure): a connection failed, or disconnected for 2 s, again at most every 5 s while it stays broken; every connection at once on the window's `online` event and on `navigator.connection`'s `change` (Wi-Fi to mobile).
- A connection is given up after 30 s broken (was 15 s), so restarts have time.
- A read (`ws_call_sync`) lost on the network (fetch's `TypeError`) is tried again for 45 s (`readGiveUp()`); a refusal from the server ends the call here at once.

## How does a closed tab leave?
- `pagehide` sends `ws_call_leave` with keepalive, naming its place (`member_id`).
- Where the leave never arrives (a phone that dropped the page, a crash), the clean-up (`ws_calls_sweep()`, every 10 s while a call is live) takes out a member with no signal of its own for `WS_CALL_MEMBER_SILENCE` (90 s, three missed 30 s keep-alives): its row is closed and its signals and share go (`ws_call_member_out()`); the last one out ends the call. Before, only the whole call's 2-minute silence ended it, so a vanished member held a seat in a group.
- A window that comes back after it was taken out reads "The connection was lost.".

## What does a second window of the same person do?
- One person is one member: `ws_call_join()` refuses a party already in the live call (code `call_elsewhere`, "You are already in this call in another window."); a start there is the same refused join.
- The other window's head shows "Join here" (`joinState()` gives `here`), its title the same sentence. It joins with `again`: the old place is taken out and a new one made, so the others connect to the new window (`ws_call_join_place()`, pure).
- The old window's next read finds another member id for the person and closes with "The call goes on in another window." (`placeOutcome()`). Its late leave or signals name its old `member_id` and change nothing (`ws_call_member_matches()`); a page without `member_id` acts for the party's row as before.
- Works on the guest page too (its Join button is the same module's).

## Does the idle cost change?
- No: the member clean-up is inside `ws_calls_sweep()`, which runs only while the stamp says a call is live (`calls-signalling.md`); it reads each live member's newest signal by `idx_from`.

## How was it checked?
- Unit tests: `tests/workspace_calls_endurance_test.php` (server rules, the browser parts under node, wiring, phone CSS).
- Chromium with fake media devices against an in-memory stand-in of the call actions, the panel's stylesheets: buttons in a 390 px head, the window at 390×844 and 844×390, a video call through 8 s offline and back with an ICE restart, a second tab's Join here taking the place over, a closed tab's leave arriving. Not run: a real phone, a real network switch, the real PHP actions in a browser.
