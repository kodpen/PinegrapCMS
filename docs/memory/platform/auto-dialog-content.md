---
name: auto-dialog-content
description: What an auto dialog shows: content url (its address in an iframe) or component (a shared component drawn into a template and copied into the dialog); columns, readers, the page, the script, the API
module: auto_dialogs
---

## Which columns are there?
- `auto_dialogs.content` VARCHAR(10) 'url' and `component_id` INT UNSIGNED 0, after `place`; no index, no backfill. Step 11.0.242 (`upgrade_2026_11_0_auto_dialog_content()`), PR #10450; confirmed by the architect in agent channel message 9861 (`workflow/decisions-made-schema-28.md`).
- A row keeps its address while it shows a component, so it can switch back without retyping it.

## How is a row read and written?
- `pg_auto_dialog_content($row)` (`includes/fn/auto_dialogs.php`): an unknown content reads as url, a bad id as 0. `pg_auto_dialog_content_ready()` asks for the columns.
- `pg_auto_dialog_save(…, $open, $content)`: null, or a site without the columns, leaves them as stored.
- `pg_auto_dialog_form_content_problems($sent, $found)` (`includes/forms/auto_dialog_form.php`): a value not offered; for component, a component missing, not given or a system widget (`pg_auto_dialog_components_found()`).

## What does the page print?
- `get_page_content.php`, preview and enabled rows: `pg_auto_dialogs_for_page($rows, pg_auto_dialog_components_found($rows))` keeps a designed pop-up only while its component exists and is no widget (its URL is not asked); `pg_auto_dialogs_on_page()` prints `content`, `component_id` and an empty `url` for it.
- `pg_auto_dialog_templates()`: one `<template id="pg-popup-<dialog id>">` per designed pop-up, drawn by `pg_common_region_component_html($id, $mode, $email)`, printed just before the page's closing script. Same for every visitor, so the page stays cacheable unless a widget inside asks otherwise.

## What does the script do?
- `frontend.src.js` (and the hand-patched `frontend.min.js`) `show_auto_dialog()`: for content component a copy of the template (`importNode`) goes to `open_dialog({content})`; no template, no dialog.
- `open_dialog()` with `content`: a `div.pg-dialog-body` in place of the iframe, class `pg-dialog-content` (no padding, the page's own font and colour, only the close button over the top right corner); a side left out, or a mobile device, sizes it to the content within the window. A press on `[data-pg-popup-close]` inside closes it.
- Content holding a `[data-pg-popup-close]` gets `pg-dialog-own-close`, which hides the frame's own close button; a press on `[data-pg-popup-copy]` runs `software.popup_copy()` (`designer/designer-blocks-popup.md`).
- Taken is counted on a press on `[data-pg-popup-take]` or a form sent inside (`visitors/auto-dialog-counts.md`).

## Who can set it?
- The API since #10450 (`api/api-design-dialogs-regions.md`).
- The Create and Edit screens since PU6b (#10530): "What does it show?" (An address / A designed pop-up) and, for a designed one, Shared Component (every component that is no system widget, `pg_auto_dialog_component_rows()`).
- Asked only with the columns and at least one visual design (`pg_auto_dialog_form_content_asked()`); a site with no visual design gets no question, keeps the address kind and saves with `$content` null (columns as stored).
- The URL is required only with the address kind (`pg_auto_dialog_form_url_required()`); a URL typed with a designed pop-up is still checked and kept. The component sent is checked by `pg_auto_dialog_form_content_problems()`; with the address kind the chosen component is kept too.
- Links under the select, in a new tab: "Open in the visual designer" (`edit_system_style.php?component=<id>`, follows the select, hidden with none) and "Design a new pop-up" (the lowest visual design's id, only for `pg_designer_is_full()`); `pg_auto_dialog_form_designer_links()`.

## Tested?
- `tests/auto_dialog_content_test.php` (the screens' helpers too), `tests/auto_dialog_form_test.php`, `tests/api_design_dialogs_regions_test.php`. Both scripts ran in Chromium (#10450): a designed pop-up opened, counted and closed; an address one kept its size.
