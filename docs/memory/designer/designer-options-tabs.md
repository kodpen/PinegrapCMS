---
name: designer-options-tabs
description: The Options panel's three tabs Content, Style and Advanced (D4, D5, D14): which section goes where, the tab kept per kind, only the open tab built, the closed Translation and Bind data summaries
---

## What stands above the Options panel's scroll?
- `#sd-props-head` (made by `initRightPanel()`, filled by `_sdPropsHead()`): the identity row and the tab strip `_sdOptTabStrip()`; the Search... box stands under it. Several elements fill it too (`designer-options-multiple.md`). It is hidden for every other panel: nothing selected, the content level's read-only panel (K4), an element inside a text.
- A tab is named Content, Style or Advanced with its icon and the count of changed rows; the open tab's count is filled with the accent and written in the panel's ground (white on the accent is under 3:1). Under 300 px the icons go and the count sits on the tab's top-right corner with a ring of the strip's ground.
- Left and Right arrows move between the tabs and wrap round (`_sdOptTabNext()`); a tab with no section is not drawn (`_sdOptTabsShown()`).

## Which section goes in which tab?
- A builder marks its card with a key (`_sdSectKey()`, `data-sd-key`); `_SD_OPT_TAB_OF` maps it: text, spacing, colors, border, display, styling to Style; flexbox, visit, accessibility, tooltip, search, interactions to Advanced; anything else to Content.
- Content's order (`_SD_OPT_RANK.content`): shortcut bars, the element's own section, its other cards (Quick Layouts, form, choices, widget sections), the icon picker, Link (with Links in the text), the parent's options, Translation, Bind data. Style's and Advanced's order, names and open rules: `designer-options-style-advanced.md`.
- Show by the visit alone is Advanced; with bind fields it stays inside Bind data. `_sdSectTakeKeyed()` lifts top-level keyed cards out; `_sdOptPlan()` places every piece once, so no section is dropped (D5).

## Which tab opens?
- `_sdOptTabKeep()`: an element of the same kind keeps the open tab; another kind opens Content at the top (D8 read with D4). Undo and redo keep the selection and the tab (D9).
- A click on a tab empties the Search... box and opens that tab at the top.

## Is a closed tab built?
- No. Its markup waits in `_sdOptHeld[tab]`; its count is counted on a detached copy (`_sdOptHeldCount()`). The panel carries `data-sd-held` while a tab is held.
- The search draws every tab in one list (`data-sd-otab-all`) and redraws the open tab only when the box is emptied. A jump to a control in a held tab or closed card goes through `_sdOptReveal()`, which opens its tab and cards.

## What do closed Translation and Bind data show?
- Translation (D14): "EN, DE · not translated" in the warning colour, or "translated" (`_sdTrState()`); the closed card is updated when the segments arrive (`_sdTrPut()`) and removed when the element has none.
- Bind data: the chosen field of its first bound row, or "not bound" (`_sdBindSum()`).
- propsDataBinding's extra rows (Show by the visit) are put inside a card's body by `_sdSectSlotFix()`; before #10321 they were slotted into a closed card's empty body and lost when it opened.

## Where is it tested?
- `tests/designer_options_tabs_test.php` runs the pure block (marked "The Options tabs: pure helpers") under Node: places, every section reached for a heading, a row, a picture, a link and a form field, the kept tab, the strip's markup and the summaries; it also checks each builder's key in the source.
