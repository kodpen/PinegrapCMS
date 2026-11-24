---
name: main-menu-rail-label
description: How the main menu's icon rail hides a row's label (opacity, never visibility or display) so a screen reader still reads the row's name, a row with a count included
---

## How does the rail hide a row's label?
- With `opacity: 0` (`backend.src.css`, the rail rule on `.list-group-text`, `:where()`-guarded off drawer-only screens); below lg the phone drawer shows it again with `opacity: 1`.
- Never with `visibility: hidden` or `display: none`: both take the label out of the row's accessible name.

## What did that fix?
- On the rail a row with a count (Communication's unread e-mail, the Workspace's news, the flows running) was named by its number alone, since the count was its only text left and a title is used only when a link has no text. Found by the main menu bug hunt (`workflow/bug-hunt-areas-main-menu-redraw.md`); pinned by `tests/menu_rail_keyboard_test.php`.

Related: docs/memory/ui/main-menu-states.md, docs/memory/ui/main-menu-icons.md
