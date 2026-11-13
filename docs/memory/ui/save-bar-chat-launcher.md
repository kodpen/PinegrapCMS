---
name: save-bar-chat-launcher
description: Look A's floating save bar (edit screens, settings pages) ends its buttons before the chat launcher in the window's bottom right corner (#9330)
---

## What was wrong?
- With the panel chat on (`#pg-chat-root`), the round launcher floats 24 px from the window's bottom right. Look A's save bar puts its buttons at its right end, so the launcher covered Save; the owner saw it on the settings and tool pages of #9330 (2026-11-13).

## What does it do now?
- From 576 px the bar's `.container` takes `margin-inline-end: var(--pg-chat-clearance)` while the chat is on the page: `body.pg-measure-edit:has(#pg-chat-root) .row > .order-3 > nav.buttons.navigation > .container` and the same for `#pg_settings_page_form` (`backend.src.css`).
- Below 576 px the shared rule still lifts the whole bar above the launcher (`edit-screen-wide-layout.md`).
- `tests/look_page_widths_test.php` pins the edit screens' rule.
