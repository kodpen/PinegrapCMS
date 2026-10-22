---
name: designer-widget-ghosts-and-edit-chips
description: Ghost records on the canvas for listing widgets (designer/widget_ghosts): kinds, count, DOM-only rules; and the front-end edit pencil on records (pg_sw_add_edit_chip()) with its permission checks.
---

## What are ghost records?
- A listing widget's card is designed on one sample record; the canvas follows it with real records the server draws from the same tree: `_sdAppendGhosts()` → `designer/widget_ghosts` → `pg_designer_widget_ghosts($page_id, $widget, $limit)`, a background render through `pg_designer_preview_widgets()` (`designer-live-preview.md`).
- Ghosts are DOM only: no `data-sd-id`, `inert`, never selectable, never in the tree JSON. They are redrawn with every render and asked for again 600 ms after the widget's tree or settings change. A browser can switch them off (`localStorage` `pg_sd_ghosts` = `'0'`).
- Kinds: the server's list in `pg_designer_widget_ghosts()` (`form_list_view` and its old names, `catalog_listing`) and JS `_SD_GHOST_TYPES`; a new listing kind goes into both.
- The count follows what the page shows: the server draws the first page (`items_per_page`, capped by `max_results`), drops the first record (its place is the card's) and returns the rest, at most `_SD_GHOST_LIMIT` (10) after the card.
- The card's bound image takes the natural size of the first ghost image bound to the same field (`data-sd-bind-src`, `_sdBindsSrc()`; the site's "no image" picture is skipped).

## What is the edit pencil on records?
- `pg_sw_add_edit_chip($html, $url, $title, $tone)` (`includes/fn/widgets.php`) puts a pencil link into the first element of a rendered record and makes that element its positioning box. Not on a record whose first element is a link, a button, a table row or a void element (the record's detail page carries one).
- Who sees it: a signed-in user, never in a search-index or SEO render (`pg_sw_edit_chips_on()`); products need `pg_sw_can_edit_products()` (role below 3 or e-commerce management), submitted forms `pg_sw_can_edit_submitted_forms()` (a manager, or forms management with edit access to the form's folder).
- A page built in the visual editor has no edit mode: `get_page.php` ignores a session left in edit mode for it.
