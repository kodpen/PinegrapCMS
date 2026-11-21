---
name: features-switchboard-screens
description: Plan features-switchboard, its screen: the Features pane's board of module switches, its folded rows, the words and what must be true
---

## By which rules?
- `ui/screen-composition-rules.md`; parts copied from the Design examples screen, its section in brackets (`workflow/screen-described-in-plan.md`). The switch row is drawn by the architect and enters that screen before FS1.

## How is the Features pane laid out?
- Head: "Features" and one purpose line [Type and text]: what the site has on, and how its search, pages and images behave.
- The "Modules" card [Cards] of switch rows [Switch row], one a module, in this order: Commerce, Accounting, Forms, Calendars, Workspace, Courses, Tickets, Ads, Auto dialogs, Sharing buttons, Visitor notifications, Maps, Clicks and scrolling, Content approval, Mobile view.
- A row: its icon tile [Icon tiles and people], name, one line, the switch at its end [Fields]. A row whose settings sit below adds "Settings" quiet, opening its folded row. Commerce and Accounting show their state pill [Segmented control and status] and "Open" quiet in place of the switch.
- Below the card, folded rows [Settings pane], one open, each line saying its state: "Site search", "Sharing buttons", "Visitor notifications", "Maps", "Clicks and scrolling", "Content approval", "Speed", "Images", "Page revisions", "Signature time stamp", "Badge".
- Site search: Search type [Segmented control and status] Simple, Advanced, its help under it; Synonyms; Promoted Results.
- Sharing buttons: Setup [Segmented control and status] Simple, Advanced; Simple shows the services as checkboxes [Fields] in two columns, Advanced the code field.
- Speed: Page Cache with its Page Lifetime, Prefetch Menu Links, Performance Monitoring; with Mobile view on, the row's line says the page cache is off.
- Foot: Save the fill, one only.

## Which words?
- "Modules", "Settings", "Open", "On", "Off", "Site search", "Sharing buttons", "Speed", "Badge", "Mobile view", "Auto dialogs"; a row's name never starts with "Enable"; never "Feature Options" or a constant's name.

## What must be true?
- One look at the board shows every module's state, Commerce and Accounting included.
- Every switch posts under today's name; a Save stores what it stored.
- `#settings/features/pgset-courses`, `…/pgset-event-tickets` and `…/pgset-features` open the board.
- With Maps off, its row keeps its fields and its line says it is off.
- Nothing in the pane is more than one fold deep; no popover is left.
- The settings search finds Courses and Tickets.

Related: docs/memory/plans/features-switchboard.md, docs/memory/plans/features-switchboard-steps.md
