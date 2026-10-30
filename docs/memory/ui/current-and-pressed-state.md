---
name: current-and-pressed-state
description: The current link or menu item carries aria-current beside .active; a segmented toggle button carries aria-pressed beside its fill (.active or btn-solid)
---

## Why is .active not enough?
- It is only a fill. A screen reader hears nothing, and Bootstrap 6 sets `aria-pressed` only on `data-bs-toggle="button"` elements. Every place that prints `.active` for the chosen item also prints the state.

## Which attribute goes where?
- A link to the current page, view or tab (nav pills, tabs, list-group links, menu items, pager links): `aria-current="page"`, printed in the same ternary as the class, `' active" aria-current="page'`.
- A filter chip that narrows the list in place (Translations): `aria-current="true"`.
- A button that toggles a choice without leaving the page: `aria-pressed`, `"true"` on the chosen one and `"false"` on the others, kept in step wherever script moves the fill.

## Where is it done?
- `aria-current`: the File and Catalog Manager tree rows (`markTreeRow()`, `explorer-tree-keyboard.md`) and its Files scope menu, Translations (language menu, chips, page and group lists, pager), ERP Cash flow view pills, ERP Settings document pills, ERP invoices e-Document filter menu, Cloudflare Tools period tabs, Paraşüt inbox tabs, the Site Settings category switcher, the image picker sort menu (`editor_select_image.php`).
- `aria-pressed`: the offer editor's Validity and Scope buttons (`edit_offer.php` prints `"false"`, `pressed()` in `offer_editor.js` sets class and attribute in `renderOptions()`); the per-result unit, target and tier-mode buttons through `choiceButton(on)`; the Site Log Type filter (`view_log.php` prints it and the click handler moves it). Workspace My Tasks scope buttons (`setScope()`) and the planning board's week buttons (`workspace/workspace-task-filters.md`). The File and Catalog Manager's Grid/List pair (its group named View), Filters, Preview panel and right-click View/Sort/Size/column choices: `explorer-picker-state.md`.

## What changed in the offer editor's target choice?
- The Product button was filled whenever the result did not target a group, so with Cheapest chosen two buttons were filled. Product is now chosen only when neither Group nor Cheapest is.

Test: `tests/current_state_marks_test.php`.

## How does the dashboard Sales Map say its period and map?
- Its Filter menu's period (`data-smap`) and map (`data-smap-view`) choices are `menu-item` buttons in `includes/dashboard/widgets/widget_1.php`. They print `aria-pressed` in the same ternary as `.active`, and `show()` sets it beside the class. Bootstrap 6 menus add no `menuitem` role, so `aria-pressed` is valid there. Before, the choice showed only by the fill and a check icon.
