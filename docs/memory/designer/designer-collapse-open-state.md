---
name: designer-collapse-open-state
description: Open state an accordion item (.collapse) saves and publishes: the one its toggle is written with, not the item last clicked on the canvas; modal, offcanvas, dropdown-menu always close.
---

## Why does the editor touch `show` on save at all?
- A click on a collapse toggle on the canvas opens its panel, and `_syncToggleableShowStateFromDom()` copies the live `.show` into `props.cssClass` so a re-render keeps it. Saved as it is, an accordion would publish whichever item was last clicked open.
- A modal, offcanvas or dropdown menu open on the canvas never reaches the tree (designer-canvas-open-state.md); a tree saved before can still carry its `show`.
- `_normalizeToggleableShowState(tree)` (`assets/js/style_designer.js`) runs on save (page and every cached shared tree) and on every load path (page tabs, prefetched widgets, revisions, autosave), so canvas, preview and page start from the same state.

## What does it keep?
- A `.collapse` keeps or regains `show` when a toggle in the same tree is written open: `data-bs-toggle="collapse"`, `data-bs-target` (or a link's `href`) `#id`, `aria-expanded="true"` and no `collapsed` class. One open toggle of several is enough.
- Closed toggle, a class-selector target (`.multi`) or no toggle in the same tree: `show` is dropped (a toggle in a widget and its panel on the page count as no toggle).
- Modal, offcanvas, dropdown-menu: `show` always dropped.
- Id lookup: `props.id`, or a legacy `_attrs` id (`_sdNodeElementId()`).

## How does an operator change the authored state?
- Options panel > Status: "Expanded" on an `accordion-collapse`, or "Collapsed" on a semantic `accordion-button`, calls `_sdSetCollapseOpen(root, id, open)`: panel `show`, and on every toggle of `#id` `aria-expanded` (added if missing) and `collapsed` flip together, as Bootstrap does on a click. A content-node button (what the builders write) has no Status section; the panel's switch is the way.
- The canvas toolbar's Show/Hide and canvas clicks stay view state; the save puts the authored state back.
- A panel with no toggle cannot be saved open (the switch shows it on the canvas only).

## What do the builders write?
- FAQ blocks (`faq-accordion`, `faq-categorized` per category): first item open (button without `collapsed`, `aria-expanded="true"`, panel `show`), the rest closed. Before this rule the save dropped the first item's `show`, so the page showed every item closed under a button saying open, while the preview showed it open.
- Components tab > Accordion: one item, open, with `type`, `data-bs-toggle`, `data-bs-target`, `aria-expanded`, `aria-controls`, panel `id`, `aria-labelledby`, `data-bs-parent` (ids `acc_x`, `acc_x_c1`, `acc_x_h1`). Before, the button had none of them and the panel no id: it published closed and could never open. Such saved accordions stay broken until the item is added again; New Section (composite add) writes items closed with the same attributes.
- FAQ pages saved before the fix publish the first item open again on their next save (the load pass puts `show` back).

## What keeps this from coming back?
- `tests/designer_collapse_state_test.php` runs the functions and the three builders under Node: every `accordion-button` toggles a panel of its tree, `aria-expanded`/`collapsed` agree with the panel's `show`, the first item is open, and a save pass changes nothing on a fresh build.
