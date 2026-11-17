---
name: designer-panels-features-4
description: Plan designer-panels, features D45 to D53: the page strip: the design's menu, the open group as a box, folded groups as chips, tab states, the Pages window, suggested groups
---

## Where do the design's own actions go? (`page-strip.jpg`)
- (D45) The design's name is a menu ("Online Store ▾") that holds Settings and, under a divider, Delete. The gear and the bin leave the strip; Delete asks as today.

## How are thirty-seven pages reached?
- (D46) The open group is a box with its name and colour, and its pages are tabs in it. One group is open at a time; the strip never scrolls sideways.
- (D47) A folded group is a chip with its name and count ("Account 13"). One click lists its pages under it without opening the group: the first pages, "8 more pages", "Open the group in the strip".
- (D48) Pages without a group stand together as "Other", the same way. Tabs that do not fit the open group's box go behind a "+N" chip that lists them.
- (D49) A tab shows its state: the home page's icon, changed (a blue dot), draft (a "draft" pill), open by somebody else (a lock, the name in the tip), and the schedule badge as today. Tab 30 px, corner 8, 12.5 px.

## What is the Pages window?
- (D50) "Pages 37" opens it, and Ctrl+P: "Add page", a search ("Type a name or an address…"), the filters All, Changed, Draft, Recently opened, and the pages listed by group with address and state.
- (D51) Keys: Up, Down, Enter opens, F2 renames; a page is dragged onto a group. It takes the place of today's "All pages" list.
- (D52) "Recently opened" is kept per browser.
- On a 390 px phone the window is a sheet from the bottom with rows of 44 px: `designer-panels-measures-6.md` (`phone-pages-move.jpg`).

## What about a design without groups?
- (D53) The strip offers a grouping ready to accept in one click, read from the pages' addresses and kinds (pages whose address starts alike, the account pages, the blog, the e-mails); refused, it does not come back for that design in that browser.

## What does not change?
- Group, colour and order stay on the design (`style.style_tab_layout`, saved through `designer/tab_layout`), folding stays per browser; no new storage (`designer/designer-tab-groups.md`).
- Opening, renaming, adding, closing and dragging a tab, the dirty check and a locked tab work as today (`designer/multi-page-designer-editor.md`).
