---
name: notifications-menu
description: The header notifications panel (#notifications, bell #notification_toggle): one Bootstrap 6 Menu on the bell, Ctrl+Q clicks the bell, menu events and open state read on the toggle (#2578)
---

## How is the panel opened?
- The bell `#notification_toggle` (`includes/fn/output.php`) carries `data-bs-toggle="menu"`, `data-bs-display="static"` and `data-bs-target="#notifications"`; the `.menu` box is placed by its own classes (`position-fixed end-0 top-0`).
- Ctrl+Q (`backend.src.js`, window `keydown`, `case 'q'`) calls `notification_toggle.click()` in a `setTimeout` (Firefox opened its find bar otherwise).

## Why not pgUI.menu.toggle('#notifications')?
- Under v6 that made a second Menu with the `.menu` box as its toggle: it ignored the bell's static display, ran Floating UI and wrote inline position, left, top and margin that nothing cleared, so the panel stayed misplaced (for the bell too) until a reload, and its `aria-expanded` on the box went stale.

## Where are the menu events heard?
- v6 fires `shown/hidden.bs.menu` on the toggle, not on the box: the shown/hidden listeners (`get_notifications(true)`, `pg_push_refresh()`, the bell's `show` class) sit on `#notification_toggle`, and the refresh checks in the unread poll read `pgUI.menu.isOpen('#notification_toggle')` (`aria-expanded`).
- One list refresh per open (the shown handler); the bell's click handler refreshes when it closes the panel. `tests/bs6_inline_feedback_test.php` pins both.
