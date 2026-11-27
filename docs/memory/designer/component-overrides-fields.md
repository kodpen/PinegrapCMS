---
name: component-overrides-fields
description: Opening a shared component's field "Can change per place" in the designer (CO3): the menu and palette row, the list, labels, the marks, the settings list with order, Rename and Close the field
module: component_overrides
---

## Where is a field opened?
- In the element's three-dot menu of the Options head (after "Show in Overview") and the command palette row "Can change per place" (`act_open_field`), only when `_sdCoMayOpen(node)`: a node inside an ordinary shared component (not the `shared_ref`, not the page root, not a system widget), the page lock held, not content level (roles 0-1).
- Both open the list of the part `overrides` (`assets/js/style_designer_overrides.js`, loaded on first use with the model twin; `designer-parts-on-demand.md`). The green mark after an open field's Options row label opens it too.

## What does the list offer?
- A switch per field the element has, in this order: Text, Picture, Link, Icon (from its props and content type), and Shown, except on the component's root. A field bound to data (`props._bindings` text, src or href) is dimmed with "Bound to data: it already changes with the record."
- A switch on writes `{id, kind, label}` into `props._open` (id from `newFieldId()`), appended to the root's `props._openOrder` when one is stored; off removes it. A "Label" field under an open switch; empty goes back to the default.
- Default label: the element's name; with two fields open both take "name field" ("Button text", "Button link"). Labels still at a default follow when a field opens or closes.
- First use: one line with "Got it", kept in `localStorage` `sd_co_first_seen`.
- Up and Down move between switches, Space flips, Tab reaches Label, Enter keeps a label and goes back to its switch, Escape closes and gives the focus back to what opened the list (the element's three-dot button when that was redrawn; #11853). A label typed and not yet left is kept when the list closes otherwise (a click outside), dropped by Escape; after an Undo the list writes into the tree the editor has now (#11998).

## How is a change written?
- Every write goes through the core's `_sdCoWrite(sid, fn)`: the row's snapshot taken first when none (never after a change), `saveState()` (one undo step; shared trees are in the step), the change, `_sharedDirty[sid]`, `render()`. Nothing is sent: Save sends the component.

## How are open fields marked?
- Overview: `bi-arrows-angle-expand` at the row's right edge (`.sd-tree-open-badge`), tip "Can change per place: labels".
- Options panel: the same sign after the label of the row whose control has `data-prop` text, src, href or iconName; "Shown" after the element's name in the head (`_sdCoAfter()`, run in `renderProperties()`). Content level sees no mark.

## What does the "Shared Component" section list?
- Under "Can change per place", between "Content editors" and the usage and Detach rows, for a component that is not a system widget, not at content level: one row per open field in `openFields()` order (grip, kind icon, label, three-dot). With none, and the part not loaded, the core prints "No field yet…" without loading it.
- Drag by the grip or Alt+Up/Alt+Down writes the whole order into the root's `props._openOrder`.
- Row menu: Rename (in place, Enter keeps, Escape leaves), Show in Overview (selects the node), Close the field: removed with no question, the places' values stay and are ignored (left-overs, `component-overrides-model.md`); a Result line (`.pg-result`) with Undo puts the entry back at its place and order.
- The menu is reached by the pointer only, so a focused row takes F2 (Rename) and Delete (Close the field); after closing, the focus is on Undo, after Undo on the row (#11853).

## Tests?
- `tests/component_overrides_test.php`: the part's field rules and labels in Node with a stub kit; the core's entry points. CO3, #11597.
