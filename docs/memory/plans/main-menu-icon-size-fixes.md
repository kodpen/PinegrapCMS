---
name: main-menu-icon-size-fixes
description: Two differences the designer's look found on the landed System tools panel (MI3 #11247), taken by step MZ1: System Information lacks its new-tab mark, the longest tool name ends at the panel's edge
---

## What is this file?
- The designer's look at the System tools panel after MI3 landed (2026-11-24; sandbox, 1440 and 390 px, both themes, the wide menu, the rail and the phone drawer, a 620 px high window). Everything else matched the drawing `ui/main-menu-icons-drawing/tools.html`: one list, five headings, nineteen tools in the drawing's order, no screen entry, headings not focusable, the list scrolling in itself.
- MZ1 of `plans/main-menu-icon-size.md` takes both: it changes the same lines (the panel's entries grow with the tile). MZ1's close-out deletes this file with its plan.

## 1. Does System Information show that it opens a new tab?
- No. The drawing ends the entry "System Information" (group Infrastructure) with `bi-box-arrow-up-right` at the row's right end; the landed entry is the only one with `target="_blank"` and draws no mark.
- Cause: `pg_menu_tools_markup()` (`includes/fn/output.php`) prints every tool with the same anchor; the entry that opens outside the panel gets `target` but no trailing mark.
- Fix: the mark at the end of that entry, pushed to the right (`ms-auto`), `aria-hidden`, with the hidden text `lang('(opens in a new tab)')`, as the account menu does (`pg-um-ext` in `output_header()`).

## 2. Does the longest tool name fit the panel?
- Barely. In Turkish "Tüm Yönlendirme Kaynakları" ends about half a pixel before the entry's right edge (text to 243.5 px of 244 px) in the wide menu; a longer translation or a wider icon pushes it out of the panel.
- Cause: the panel's tool entries are `btn btn-link … text-start` without `text-truncate`, so a long name neither wraps nor cuts; the Visitors panel's external entry already carries `text-truncate`.
- Fix: MZ1 widens the tile to about 32 px, which takes the missing room. The entries get `text-truncate` (with `d-block` or `mw-100` so the cut applies) and the full name as `title`, or the panel's text column is measured again with the new tile so the longest Turkish name keeps at least 8 px of air.

## What was looked at and is not a difference?
- In a low window the panel's title row scrolls away with the list. Every panel of the menu does the same; it is not MI3's and no step is asked for it.
