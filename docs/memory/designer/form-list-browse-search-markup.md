---
name: form-list-browse-search-markup
description: The form list view template's Browse select and Search box: markup read by Bootstrap 3 and 5.3.8 alike, inline SVG icons, no Glyphicons (#9034)
---

## How does the default form list view template draw Browse and Search?
`includes/templates/form_list_view.php` keeps Bootstrap 3's `form-group input-group` and `span.input-group-btn` wrappers and adds the 5.3.8 names beside them: `mb-3` on the group, `form-select` on the browse select, `h-100` and `rounded-start-0` / `rounded-end-0` / `rounded-0` on the buttons, `d-flex` on the search button's span. Bootstrap 3 does not know those names, so a Bootstrap 3 page looks as before.

## Why inline SVG icons?
The minus, plus, search and clear icons were Glyphicons, which a Bootstrap 5.3.8 site does not load: the buttons drew as empty slivers and the group broke (#9034). The icons are now inline SVG (Bootstrap Icons' paths, `fill="currentColor"`), so no icon font has to be loaded on either version. Each button has an `aria-label`.

## What about the system template and saved layouts?
`form_list_view_system.php` draws a table with the system theme's own classes, not Bootstrap, and is unchanged. A page's saved custom layout keeps its own markup; on a `bootstrap5` visual design the layout pass converts it ([[legacy-layouts-shapes]]).
