---
name: designer-pages-window
description: The designer's Pages window (DP20): the Pages button and Ctrl+P, Add Page, the search by name and address, the filters, pages by group, keys, F2, dragging onto a group, "Recently opened" per browser
---

## What is the Pages window?
- Every page of the design in one window (D50 to D52). It took the place of the "All pages" menu: `_pgTabsAllToggle()`, `_pgTabsAllFill()`, `_pgTabsAllPageMenu()` and the menu markup in `designer_screen.php` are gone.
- The Pages button `#sd-tabs-all-btn` (in `#sd-tabs-all`, after the strip, with the count) and Ctrl+P (Cmd+P) open it; Ctrl+P a second time, the ×, Escape or a click outside close it. The button still shows only while a page has no tab in view (`_pgTabsAllSync()`, unchanged); Ctrl+P works always.
- Ctrl+P had no handler before (K9): Ctrl+Shift+P stays the command palette, Ctrl+Alt+P the preview. Caught in the editor's keydown and in the canvas frame's, not while a field is typed in. Also a command palette row and a line of the shortcuts list.

## What does it hold?
- Head: "Pages" with the count, "Add Page" (where the strip's New Page exists and not at content level; runs `_pgTabsAddNew()`), ×.
- The search "Type a name or an address…": a page's name or address (`_pgPageAddress()`: `/` + saved name, `/` for the home page) holds the words, Turkish letters folded (`_pwFold()`, the tree search's fold, to be merged at DP25).
- The filters All, Changed (`_pgPageDirty()`: unsaved changes), Draft (only with drafts ready), Recently opened, with their counts (not Recently opened's).
- The pages by group in strip order, "Other" last (a page of an unknown group counts as Other); a group's head folds it (window only, not stored) and lists 8 rows then "N more pages" while nothing is searched or filtered. Recently opened is one list without heads, latest first, the open page first.
- A row: changed dot, home or page icon, name, address (" · open" on the open page), the holder's lock and name in the warning colour, the draft pill, the content-change mark (`_pgContentChangeBadge(p, true)`), a locked page's lock (not openable), the three-dot button.

## Which keys and actions?
- Focus stays in the search box (a combobox, `aria-activedescendant`): Up and Down walk the rows, Enter opens, F2 renames in the row (Enter or leaving commits, Escape keeps; `_pgPageNameSet()`, the strip rename's own setter), Escape clears, then closes.
- Right-click, the context-menu key or Shift+F10: the window closes, the page opens and the strip's page menu opens, as the old list did.
- The three-dot menu (the phone's way to F2 and dragging): Rename, "Move to group" with every group and Other, "Page options" (the strip's page menu).
- A row dragged (HTML drag and drop) onto another group's section moves the page to that group's end; onto Other takes it out (`_pgPagesMoveToGroup()` → `_pgTabGroupAdd()` / `_pgTabGroupRemove()` → `designer/tab_layout`). Not at content level or without the column.

## Where is "Recently opened" kept?
- Per browser and design: `localStorage` `pg_sd_recent_<styleId>`, saved page ids, latest first, at most 12 (`_pgPagesRecentPush()`), noted by `_pgTabsSwitch()`. Nothing on the server.

## How is it drawn on a phone?
- Under 576 px: a sheet from 58 px under the top to the bottom, corners 20, a handle pulled down closes it, rows 44 px, the three-dot button always shown, no key hints (owner poll 129, `phone-pages-move.jpg`, git history: `designer-panels-overview.md`).

## Where is the code and the test?
- The part `pages` (`assets/js/style_designer_pages.js`, `pg_designer_parts()`), handed to every editor; it reads the pages through `_pgPagesHost()` and takes only `_sdT` and `esc` from the kit. Styles `.sd-pw-*` in `style_designer.css`.
- `tests/designer_pages_window_test.php`: filters, folded search, keys and F2, a drop written through `tab_layout`, Recently opened, the part and Ctrl+P.
