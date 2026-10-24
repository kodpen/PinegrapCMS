---
name: designer-widget-ghosts-and-edit-chips
description: Canvas ghost records of listing widgets (designer/widget_ghosts); the edit pencil on records (pg_sw_add_edit_chip()): who sees it, inline !important look, its page stylesheet.
---

## What are ghost records?
- A listing widget's card is designed on one sample record; the canvas follows it with real records the server draws from the same tree: `_sdAppendGhosts()` → `designer/widget_ghosts` → `pg_designer_widget_ghosts($page_id, $widget, $limit)`, a background render through `pg_designer_preview_widgets()` (`designer-live-preview.md`).
- Ghosts are DOM only: no `data-sd-id`, `inert`, never selectable, never in the tree JSON. They are redrawn with every render and asked for again 600 ms after the widget's tree or settings change. A browser can switch them off (`localStorage` `pg_sd_ghosts` = `'0'`).
- Kinds: the server's list in `pg_designer_widget_ghosts()` (`form_list_view` and its old names, `catalog_listing`) and JS `_SD_GHOST_TYPES`; a new listing kind goes into both.
- The count follows what the page shows: the server draws the first page (`items_per_page`, capped by `max_results`), drops the first record (its place is the card's) and returns the rest, at most `_SD_GHOST_LIMIT` (10) after the card.
- The card's bound image takes the natural size of the first ghost image bound to the same field (`data-sd-bind-src`, `_sdBindsSrc()`; the site's "no image" picture is skipped).

## What is the edit pencil on records?
- `pg_sw_add_edit_chip($html, $url, $title, $tone)` (`includes/fn/widgets.php`) puts a pencil link into the first element of a rendered record (class `pg-edit-host`). Not on a record whose first element is a link, a button, a form, a table row or a void element (the record's detail page carries one).
- Who sees it: a signed-in user, never in a search-index or SEO render (`pg_sw_edit_chips_on()`); products need `pg_sw_can_edit_products()` (role below 3 or e-commerce management), submitted forms `pg_sw_can_edit_submitted_forms()` (a manager, or forms management with edit access to the form's folder).
- A page built in the visual editor has no edit mode: `get_page.php` ignores a session left in edit mode for it.
- Canvas ghosts and the Preview never draw one: they are SEO renders.

## Why is the pencil's look written on the pencil?
- A design's rules for links, svg and pictures reach it. On the Magazine's issue list the pencil was a 340 px svg in the flow above each cover.
- The `<a>`, its `svg` and `path` carry their box inline with `!important`: absolute, top .5rem, left and right .5rem (the box keeps its start side, so top right on a right-to-left page), 1.9rem circle, tone background, flex centring, margin/padding/border/float/font reset; svg .9rem, block, static, `fill:currentColor`, no pointer events.
- Opacity (.55) and transform are plain inline declarations: a design's rules cannot beat them, the page's stylesheet raises them with `!important` on hover and focus. Print hides it with `visibility` (display is pinned).

## Where does its stylesheet come from?
- `pg_sw_edit_chip_css_inject()` at the end of `get_page_content()` (not in an e-mail) puts `<style id="pg-edit-chip-css">` once before `</head>` when the finished page holds a chip.
- It used to come with the first chip of the request. A list hidden by "Show only when" draws its rows before it is cut (the Magazine's `issue_list` on the issues page), so the stylesheet went with them and every later chip had none.
- `:where(.pg-edit-host){position:relative}` makes the record the pencil's box at zero specificity: a design that positions the record wins. Nothing is written on the record.
