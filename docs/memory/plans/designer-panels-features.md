---
name: designer-panels-features
description: Plan designer-panels, features D1 to D15: the Options panel's column, identity row, three tabs, section cards, the changed dot and counts, controls, the quick strip
---

## What is the column? (`options-4-whole-screen.jpg`)
- (D1) The Options panel takes the whole height of the right column (776 px visible on the drawing, 416 today). "Design", the style and script files, is the column's second tab, no longer a 300 px block under it.
- (D2) The bottom panel (HTML, Properties, Styles) is a thin strip, drawn only when opened; open or closed is remembered per browser.

## What stands at the top? (`options-1-content.jpg`, `measures-2-anatomy.jpg`)
- (D3) The identity row: icon box, the element's name, its tag badge, the last three steps of its path (a click selects that parent), a brush (D27) and a three-dot menu with what the panel head offers today.
- (D4) Three tabs: Content, Style, Advanced. A tab's badge counts its changed rows and is absent at 0; the open tab's badge is filled. Left and Right move between tabs. The tab is remembered per element kind.
- (D5) Where each of today's sections goes is the table of `designer-panels-measures-3.md`. No section and no row is dropped.

## How does a section look and open?
- (D6) A card. The element's own section is first and open. A general section is closed and shows a summary: a colour square, a single value as text, else "none" or "default"; and a badge counting its changed rows. Open or closed is kept per section in the browser as today (`sd_cs_<sid>`).
- (D7) Only the open tab and the open sections are built; a closed section's body is built when it is opened.
- (D8) Another kind of element selected: Content opens, scrolled to the top. A second element of the same kind: the tab and the scroll stay.
- (D9) Undo keeps the selection and the open tab.

## What does a row show? (`measures-3-controls.jpg`)
- (D10) A row whose value differs from its default has a blue dot before its label. The dot's place is always kept, so labels never shift. Under the pointer the dot is an undo arrow, "Reset to default"; a click resets the row as one undo step.
- (D11) One source for every count: a row is changed when its value differs from the default (`defaultProps`, `STRUCTURAL_DEFAULTS`, `SKIP_PROPS` as today); a section's and a tab's badge count dots.
- (D12) A screen-size badge ("MD", "all") only on a row whose value starts at a size; a click opens the size chooser (D17).
- (D13) Controls: a button group for up to six values (a click on the selected one removes the value), a select for seven or more; a field with its unit at the right; an invalid value is not written and says why in one line under the field. Every state as `designer-panels-measures-2.md`. A control the sheet does not show (colour, icon, picture picker) stays as today at height 30, corner 9.

## What else is on Content?
- (D14) Translation and Bind data: closed, with their state as the summary ("EN · not translated", "not bound", the link's target).
- (D15) The quick strip, on Content only: three styles of that element kind (a heading: alignment, size, colour) from a fixed list per kind in the code; once this browser has counted changes for the kind, the three changed most often. The count lives in the browser only.

## What stays for content-level users?
- A row hidden from a content-level user today stays hidden; a tab left without a row is not drawn; the locks and their reasons are today's (`designer/designer-content-roles-panels.md`).
