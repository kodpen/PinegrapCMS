---
name: decisions-made-panel-2
description: Decisions list, panel part 2 (2026-11-02 on): no Site Settings row in the main menu; every menu icon in two colours, the System tools toolbox animated
---

## Why is there no Site Settings row in the main menu? (owner, 2026-11-02)
- The owner asked for the Site Settings row and its panels to be removed and the System tools row kept. Slot 20 is retired, never reused; the settings open from the header gear and from links inside screens; a manager finds Translations in the Pages panel (`ui/main-menu-settings-row.md`). Not a lost row or a missing menu path; do not bring the row back without the owner.

## Why are the panel entries' icons on coloured tiles? (owner, 2026-11-02)
- The owner asked for every menu icon in two colours, to make the menu easier to read, and for the System tools icon to move. Row icons keep their drawings (System tools got an accent and a lid that opens); a panel entry's Bootstrap icon is drawn in its row's module colour on a tile of the module's other shade (`ui/main-menu-icons.md`). Not a styling accident; keep both colours when adding a row or an entry.

## Why do the File Manager and the visual designer print no alert boxes? (owner, 2026-11-02)
- The owner set the standard: a screen that works like an application (full height, its own toolbar) never prints the panel's alert boxes; pending errors, warnings and notices are shown as the screen's own toasts. The visual designer did it first (#4892, `designer/designer-screen-messages.md`), the File Manager follows (#5216, `ui/app-screen-messages.md`). Not missing messages; do not bring the boxes back.
