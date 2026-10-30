---
name: dashboard-card-drag
description: Dashboard card drag: the grip pinned to the right of the header is the only handle, mouse drags at once, touch keeps a short hold, keyboard unchanged
---

## Where is a dashboard card picked up?
- Only by its grip, `button.pg-widget-move` (role < 2), pinned to the right end of the card header in the same place on every card (`backend.src.css`: absolute, `right: 0.5rem`, grab cursor, faint until the card is hovered or the grip has focus, never hidden since touch has no hover). Out of the flow, so the centred title stays centred. The Release Notes header action (`.pg-widget-action`) moves to `right: 2.25rem` when a grip precedes it.
- `welcome.php` `Sortable.create()`: `handle: ".pg-widget-move"`, `delay: 200` with `delayOnTouchOnly: true` and `touchStartThreshold: 5`. The header itself carries no grab cursor.

## Why no delay for the mouse?
- Before, the whole header was the handle with `delay: 300` for every pointer, so a mouse had to hold still 300 ms and a 1 px move cancelled the drag (#3591). With the grip as the only handle a click elsewhere on the header stays a click, so the mouse needs no hold. Touch keeps a short hold so a finger scrolling past the grip does not pick the card up; the threshold lets it tremble a few pixels during the hold.

## What about the keyboard?
- Unchanged: the arrow keys on the grip move the card (`sortable-init-pitfall.md`). `tests/keyboard_reorder_test.php` pins it; `tests/dashboard_drag_handle_test.php` pins the handle, the touch-only delay, the grip rule and the tip key ("…dragged by the grip at the right of their headers?", `brief.php`). Added in #3676.
