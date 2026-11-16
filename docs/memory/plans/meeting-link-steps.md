---
name: meeting-link-steps
description: Plan meeting-link, steps SL1 to SL4: the link's table and rules, the page a visitor books on, the Workspace screen "My meeting link", close-out
---

## Which steps are there?
- SL1 (M) The link and its rules, server and data only.
  - A migration step adds one table of links and the place of a booking's meeting address, listed in `get_tables()`; the draft PR's body carries the columns for the architect. After the owner's yes on tables.
  - Existing files: none; the engine's functions are called, not changed.
  - New: `includes/fn/booking_links.php` (a link read and written, the token, the services a link may offer, the meeting address of a booking), `tests/booking_links_test.php`.
  - Tests, red first: a token that cannot be guessed and the same 404 for wrong and off; a service the member lost leaving the link; a deposit service refused; a private plan leaving the free times as they are and a team event taking its time.
  - Features: M1, M3, M6, M7, M9 (data), M13, M14.

- SL2 (M) after SL1: the page.
  - Existing files: `includes/fn/booking_messages.php` (the meeting address as a mail field and in the calendar file).
  - New: the root file `book_with.php` and its handling of the pick and the confirm, built on the engine's functions; no script is needed to book.
  - Tests: free times equal to the engine's for that member; a taken slot; the POST's token, origin and rate limit; no cache and no index.
  - Pictures: the services, the time pick, the form, the confirmation, a taken time, a phone. To the owner; not marked ready before his look.
  - Features: M4, M5, M8, M9, M10, M11, M12.

- SL3 (S) after SL1: the Workspace screen.
  - Existing files: `includes/workspace/nav.php` (one entry in `ws_nav_groups()`), the short links' checks called, not changed.
  - New: the screen "My meeting link" with on and off, the services, the meeting address, Copy, the QR and the short address.
  - Pictures: off, on, with a short address, a member with no services. To the owner.
  - Features: M1, M2, M3.

- SL4 (S) close-out: every M number checked against the code with its PR; `commerce/booking-meeting-link.md`; this plan's three files deleted and its line in `plans/more-features.md` removed.

## Which steps run side by side?
- SL2 and SL3 run beside each other once SL1 has landed.
