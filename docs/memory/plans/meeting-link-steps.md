---
name: meeting-link-steps
description: Plan meeting-link, steps SL1 to SL4: the link's code and rules with the profile's one column, the page a visitor books on, the Workspace screen "My meeting link", close-out
---

## Which steps are there?
- SL1 (M) The link and its rules, server and data only.
  - A migration step adds one column to `ws_profiles` (the member's meeting address); the draft PR's body carries its type for the architect.
  - Existing files: the Workspace profile's read and save (the new column). The engine's functions are called, not changed.
  - New: `includes/fn/booking_links.php` (the code made and checked, the member a code names, the services a link offers, the meeting address of a booking), `tests/booking_links_test.php`.
  - Tests, red first: a code that cannot be guessed and the same 404 for a wrong code and a member out of booking; a service the member lost leaving the link; a deposit service refused; a private plan leaving the free times as they are and a team event taking its time.
  - Features: M1, M3, M6, M7, M9 (data), M13, M14.

- SL2 (M) after SL1: the page.
  - Existing files: `includes/fn/booking_messages.php` (the meeting address as a mail field and in the calendar file).
  - New: the root file `book_with.php` and its handling of the pick and the confirm, built on the engine's functions; no script is needed to book.
  - Tests: free times equal to the engine's for that member; a taken slot; the POST's token, origin and rate limit; no cache and no index.
  - Pictures: the services, the time pick, the form, the confirmation, a taken time, a phone. To the owner; not marked ready before his look.
  - Features: M4, M5, M8, M9, M10, M11, M12.

- SL3 (S) after SL1: the Workspace screen.
  - Existing files: `includes/workspace/nav.php` (one entry in `ws_nav_groups()`), the short links' checks called, not changed.
  - New: the screen "My meeting link" with the address, Copy, the QR, the short address and the meeting address.
  - Pictures: with and without a short address, a member with no services, a service with a deposit. To the owner.
  - Features: M2, M9 (the field).

- SL4 (S) close-out: every M number checked against the code with its PR; `commerce/booking-meeting-link.md`; this plan's three files deleted and its line in `plans/more-features.md` removed.

## Which steps run side by side?
- SL2 and SL3 run beside each other once SL1 has landed.
