---
name: orders-list-redraw-filters-panel
description: Plan orders-list-redraw, step OL2b: the Advanced Filters panel's faults on All Orders (Update floating over the fields, thick scrollbars, large labels) and how the panel is put together
priority: 6
---

## What is asked?
- Owner (2026-11-22, agent channel message 12057, a picture of All Orders with the Filters panel open): "burada da hatalar var".
- The panel is the old Advanced Filters panel (`.advanced-filter-bar`, `ui/order-filter-names.md`), which `orders-list-redraw.md` left as it was. It is redrawn in its place; no filter, field name or id changes.

## What is wrong? (development bac3f2b4, seen on a sandbox and in the picture)
- Update is a `position-sticky` button at the form's end with nothing behind it: it floats over the fields and covers a label ("Address 1" in the picture).
- From 1280 px the panel is a sticky right column that scrolls as a whole with the browser's own bar; beside the list's bars that is three thick bars side by side.
- In the picture the panel ends at about half of the list's height; the step finds why (its height is `100vh - 56px` whatever stands above it).
- The labels are 16 px `form-label`, the head a bare paragraph with a `btn-close` link: none of it from the look.

## How is the panel put together?
- Parts from the Design examples screen: Fields (the look's field label and control), Drawer (its head and foot), Buttons.
- Head, 48 px: the funnel icon, "Filters", the count of filters in use as a pill when there is one, Close as an icon button (the link that turns the panel off, as today).
- Body, the only part that scrolls, with the panel's thin bar (6 px): today's groups in today's order under `.pg-group-label` headings, one field per line, the look's label over its control.
- Foot, fixed at the panel's bottom with a top border and the panel's background: "Clear" (quiet, at the left; empties every advanced filter and reloads the list) and "Update" (fill, last). Nothing floats over the body.
- From 1280 px it stays the right column, 300 px, from under the top bar to the window's bottom; narrower it stays a block over the list with the same head and foot (the foot sticks to the window's bottom while the panel is on screen).

## What stays?
- Every field's `name`, `id`, `autocomplete` token and keyboard; the form's `get` to `view_orders.php`; the panel printed after the toolbar; the icons silent (`tests/advanced_filters_order_test.php`, `order_filter_names_test.php`).
- "Clear" is new only as a button: it sends today's fields empty; no new parameter unless the screen has no other way.

## Which screens?
- The CSS is shared (`.advanced-filter-bar`), so Contacts, Submitted Forms, Commissions, Site Log and the shipping report get the scroll, the bar and the foot's place with it; their markup (head, labels, the foot's wrapper) is changed in the same step where it is the same few lines, else named in the PR as left.

## Which step?
- OL2b (M), after OL2 has landed and before OL3; in `view_orders.php` and `assets/css/backend.src.css`. OL3's close-out deletes this file with the plan's.
- Checked on a sandbox at 1440, 1280, 1024 and 390 px in both themes with the panel open and the list holding thirty orders: the foot in sight at every scroll position, one bar per scroller, Update and Clear working, Tab order as today.
- The architect looks at the landed step on a sandbox; no step waits for it (`workflow/screen-described-in-plan.md`).

Related: docs/memory/plans/orders-list-redraw.md, docs/memory/ui/order-filter-names.md, docs/memory/ui/screen-composition-rules.md
