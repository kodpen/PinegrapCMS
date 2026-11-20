---
name: walk-in-queue-screens-public
description: Plan walk-in-queue, the screens outside the panel menu (WQ3, WQ5, WQ6): the number page and the person's page in the site's look, the lobby board and the kiosk on the menu-less shell
---

## What does the person see? (WQ3, WQ5)
- A page in the site's own look, not the panel: the place's name, the services as large choices, name and phone optional, "Take a number" the one fill.
- Then their page: the number large, "N ahead", "about M minutes", the desk when called, "Leave the queue" quiet. Remote join: the same with "I'm on my way".

## What does the lobby show? (WQ6)
- The menu-less shell (`output_header()`'s `hide_menu`), opened by a signed display link, the whole screen.
- Now serving as figure cards (`.pg-figures > .pg-card.pg-figure`, the number in `.pg-figure-value`, the desk in `.pg-figure-label`), the last five calls under them, a chime on a call. Architect (2026-11-20, agent channel message 11146).

## What does the kiosk show? (WQ6)
- The menu-less shell at tablet size. The services as the starting point cards of the New application sheet (`includes/api/app_new_view.php`, `api/application-access-new-sheet.md`): a radio card with `.pg-icon-tile.pg-icon-tile-lg`, a name and one line; two a row, one under 576 px.
- Then name and phone (optional) [Fields] and "Take a number"; the number large; back to the start after a minute idle.

## What must be true?
- The person's page and the lobby show numbers, never a name.
- The lobby has no panel menu and lists at most five past calls.
- The kiosk's services are radio cards, two a row, one under 576 px; it returns to the start after 60 seconds idle.

Related: docs/memory/plans/walk-in-queue-screens.md, docs/memory/plans/walk-in-queue-steps.md
