---
name: walk-in-queue-lobby-kiosk
description: Walk-in queue lobby screen (queue_lobby.php) and kiosk (queue_kiosk.php), WQ6: signed display links, the board, the chime, the kiosk's cards, the Screens card on the Desks tab
module: walk_in_queue_screens
---

## Where are the two screens?
- `queue_lobby.php?place=<id>&k=<token>` (a TV) and `queue_kiosk.php?place=<id>&k=<token>` (a tablet), root files, no sign-in, no panel menu. Code: `includes/fn/walk_in_queue_screens.php`; tests `tests/walk_in_queue_screens_test.php`. Step WQ6 of the walk-in-queue plan (#11223).
- Staff find both links on a place's Desks tab, card "Screens": the link, a copy button and Open. The kiosk row says when "From a kiosk tablet" (Taking a number) is off.

## How is a display link signed?
- `pg_waiting_screen_token($place, $kind, $key)`: HMAC-SHA256 of `waiting-lobby:<id>` or `waiting-kiosk:<id>` under `ENCRYPTION_KEY` (`pg_booking_key()`), no column (schema-47 decisions). The lobby's link never opens the kiosk. `pg_waiting_screen_place()` checks it with `hash_equals()`.
- A link cannot be withdrawn alone: only a changed `ENCRYPTION_KEY` ends it (and every number's and booking's link with it).

## Why the bare shell and not output_header()?
- `output_header()` draws the signed-in user's bar (account menu, search, settings), so a screen with no user uses `output_header_secure()`: the panel's stylesheet and no menu, as `queue.php`.

## What does the lobby show?
- `pg_waiting_lobby_board()`: "Now serving" as figure cards (`.pg-figures > .pg-card.pg-figure`, number in `.pg-figure-value`, desk in `.pg-figure-label`), one per desk with a number `called`, newest first; "Called before": the last five calls (`PG_WAITING_LOBBY_PAST`). Numbers and desk names only; the read selects no name. A moved-back number waiting again is left out.
- `assets/js/queue_lobby.js` reads `&read=1` every 5 seconds (`PG_WAITING_LOBBY_READ_SECONDS`) while shown. The stamp (`called_at-id` of the newest call) changes with each call and "Call again"; a change chimes (Web Audio, two tones) once "Turn the sound on" was clicked, since a browser plays nothing before a click. "Full screen" asks the browser.
- The page and its read start no session (`PG_NO_SESSION`); the read shares the queue status bucket (`waf/waf-queue-status-bucket.md`).

## What does the kiosk do?
- Open only while the queue is open and the place's kiosk way is on (`pg_waiting_way_open($place, 'kiosk')`), else the closed text. Services as radio cards (`.pg-start-head`, letter in `.pg-icon-tile-lg`, a line "N waiting · about M min" from `pg_waiting_kiosk_line()`), two a row, one under 576 px; party size when asked; "Priority" (the place's groups, read by `pg_waiting_join_input($post, $place, true)` from WQ5); name, phone, e-mail when the e-mail notice is on; "Take a number".
- Taken with way `kiosk` and the form token, then redirected to `&done=1`: the number large, where it stands, a QR code of the person's page and Done. The number is held in the session for one showing, so a reload takes no second number.
- No per-address limit (`pg_waiting_throttled()`): everyone at the entrance shares the tablet's address and the signed link is the credential; the daily cap still holds. `assets/js/queue_kiosk.js` goes back to the start after 60 seconds untouched (`PG_WAITING_KIOSK_IDLE_SECONDS`), which also keeps the form token fresh.

## How was it checked?
- Unit tests (tokens, board, five past calls, stamp, kiosk line, wiring) and `tests/waf_queue_status_test.php`. No database or browser in the cloud session: the pages, the chime and full screen were not run.

Related: docs/memory/commerce/walk-in-queue-number-page.md, docs/memory/commerce/walk-in-queue-desk.md, docs/memory/waf/waf-queue-status-bucket.md
