---
name: bug-hunt-areas-main-menu-redraw
description: The bug hunt of the main menu after its redraw (MI1-MI3, MZ1), 2026-11-24: the rail's row names for a screen reader, the phone drawer's focus, what read clean
---

## What was the area?
- Named by the architect in the agent channel on 2026-11-24: the main menu after its redraw (#11202, #11233, #11247, #11271). The wide menu, the rail and the phone drawer; an opened panel, a pinned row, a row with a badge; reduced motion and the keyboard; the category tones and `pg_menu_icon()` for every row a Manager, a User and a site with modules off can see.
- Read from the code and the unit tests, no browser run.

## What did it fix?
- On the rail a row's label was hidden with `visibility: hidden`, which takes it out of the row's accessible name: a row with a count (Communication's unread e-mail, the Workspace's news, the flows running) was read by its number alone, its title only a description. The label is now hidden with `opacity: 0` (`backend.src.css`); the phone drawer shows it with `opacity: 1` (`tests/menu_rail_keyboard_test.php`).
- The phone drawer opened from its button (the bar's or a screen's own, `data-pg-menu-drawer-toggle`) left the focus on the button; the sidebar comes before the bar in the page, so Tab went through the bar and never reached a row. `focusMenuDrawer()` (`backend.src.js`) now puts the focus on the open panel's way back, else the current row, else the first row (`main-menu-drawer-focus.md`).

## What read clean?
- Every row a role can be given has a `menu_icon` with a drawing, and its tone comes from its category: Dashboard 0, Automation flows 26, Workspace 23, pinned My Notes 24 and Incoming e-mail 27, My reviews 28 (top).
- Also Pages 2, File Manager 1, Calendars 4 (also My desk alone), Forms 5 (content); Page Design Studio 21; Orders 10, Products 11, Catalog Manager 12, Offers 14, Shipping 15 (and the Order Reports row of a reports-only user), Pick waves 29 (ecommerce); ERP 22; Visitors 6, Contacts 7, Communication 9; Users 8, System tools 25.
- A category with no row for the person gets no heading; a row no category lists is drawn last in blue. The pinned rows drawn at runtime (`workspace.js`, `workspace_mail.js`) take the server's tile and tone and land after the right row.
- Badges and the flows' quiet count on the rail, expanded, in the phone drawer and on a drawer-only screen; the hint shows on the active row only.
- Reduced motion: no icon move, no flows dot, no rail slide, no delayed column (`.pg-menu-grows`). The truck's speed lines show at rest under reduced motion, as in the accepted drawing (`ui/main-menu-icons-drawing/icons.js`).
- Escape and back in panels, the widened rail closing on focus leaving, Ctrl+D (on Page Design Studio it stays the designer's Duplicate).

Related: docs/memory/workflow/bug-hunt.md, docs/memory/ui/main-menu-overview.md
