---
name: bell-redraw
description: Plan (owner 2026-11-22): the bell's drawer redrawn: a head of icon buttons, one selector, chips on one line, search in the head, rows that fit a phone, drawn states; BR1 to BR3
priority: 6
---

## What is asked?
- Owner (2026-11-22, agent channel message 11882): "bildirim panelinde kapatma düğmesi yanındaki düğme html buton gibi kalmış masaüstünde sorun yoktu. Bildirim panelini baştan cizermisin olmuşken tam olsun modernize edelim tekrar".
- The raw button was the head's "More actions" toggle without `pg-icon-button`; it is fixed on its own as a small fix, outside this plan.
- The drawing: `ui/bell-redraw-drawing/bell.html` (Z1 desktop, Z2 phone, Z3 states), accepted by the owner in poll 218 (message 11888, `workflow/decisions-made-panel-16.md`).

## What does the drawer look like?
- Parts copied from the Design examples screen (`design_examples.php`): Buttons (icon buttons), Segmented control and status, Icon tiles and people, Rows and gathered rows, Menus, Fields, Switch rows.
- Head, 56 px: "Notifications", a small pill "4 new" while a row is unread, then three icon buttons of one kind: Search, More actions, Close. No text button in the head.
- Under it one segmented control over the whole width: All, Unread (count), Asked of me (count).
- Under it the module chips on one line (Shop, Accounting, Content, Team, System, each with its colour dot and unread count): a chip only for a module with rows, the line only with two modules or more; it scrolls sideways when it does not fit. A chip pressed shows that module, pressed again shows all.
- Search: the head's Search button puts a search field in the head's place (back arrow, the field, clear); the matches are marked in the title and the text. Escape or the arrow closes the search first, then the drawer.
- The list is the only part that scrolls. Day headings Today, Yesterday, Earlier stay at the top while their rows scroll. "Mark all as read" stands at the right of the first heading while a row is unread, and in the head's menu above "Delete All Notifications".
- Foot: "Notifications on this device" with a switch; where the browser refuses, a line with a muted bell and "Notifications are off for this site" over the hint.

## What does a row look like?
- Tile or the person's face; line one: the title (one line), the time in its short form ("5 min", "3 h", "Yesterday", "9 Nov"), the unread dot; then two lines of text; then the row's button and its place when it has them.
- An unread row has a light tint of the accent and a 600 title; a read row no tint, a dim title and tile.
- Desktop: on hover or focus "Mark as read" and the row's menu take the time's place. Phone: the menu button stays after the dot, 32 px.
- A fold is drawn as today (count beside the title, the runs under a rule, "Hide" / "Show N more").

## Which states are drawn?
- Loading: four skeleton rows in place of the spinner. Failed: a tile, "Notifications could not be loaded", the server's sentence, "Try again".
- No row at all: "No notifications" and "New orders, forms and team messages appear here." Unread chosen with none unread: "All read" and "Show all". A filter or search with no match: today's sentence with "Clear filters".

## What stays as it is?
- The read rule, a row's button and in-place actions, the folds, the endpoints, the 20 s poll, the keys, the drawer's width and how it opens (`workflow/decisions-made-panel-4.md`, `ui/bell-*.md`, `ui/notifications-*.md`). No schema.
- Steps and checks: `plans/bell-redraw-steps.md`.
