---
name: designer-live-preview
description: The designer's Preview and live preview: blob page, system widgets drawn by the server from the editor's unsaved trees (preview_widgets), background-render rules, hooks that refresh the preview.
---

## How does Preview show system widgets?
- The preview is a blob page that cannot draw widgets itself, so the editor asks `designer/preview_widgets` → `pg_designer_preview_widgets($page_id, $widgets)` (at most 40): each widget is rendered from the tree and settings the editor holds now, saved or not, put in place of the stored row through `pg_sw_preview_overrides()` for `_expand_system_widgets()`, then `pg_expand_nested_components()`.
- A saved page is the address the widgets read (`$_GET['page']`); a single-record widget gets a real record (the newest submission of its form, the first product of its group); a `tab:` setting reads as nothing chosen.
- It is a background render: `pg_seo_rendering(true)` and `pg_error_throws(true)`, no status code, no redirect, drawn as a signed-out visitor sees it. A new renderer that writes the session, a status code or a redirect checks `pg_seo_rendering()`.
- The request is an `api.php` call: a renderer that prints the visitor's address prints the page's own in a background render (the error page's `__requested_url`, `pg_sw_error_requested_url()`).

## Which placeholders does the widget HTML resolve?
- The page resolves `{path}` and `{software_directory}` last (`get_page_content.php`); rich text stores its images as `{path}name.jpg`. `pg_designer_preview_widgets()` resolves both on every widget it returns (`pg_designer_resolve_output_placeholders()`), so the Preview and the ghost records cut from it load the picture instead of asking for `/%7Bpath%7Dname.jpg`.

## How does the live preview follow edits?
- While the preview window is open, `_sdLivePreviewSchedule()` refreshes it after 800 ms of quiet. `preview_widgets` is asked only when the widget payload (ids, trees, settings, page) changed, a document with the same markup is not reloaded, and one refresh runs at a time (a change meanwhile queues one more).
- The window is reloaded with `location.replace(<blob URL>)` and its scroll position is put back (`_sdPreviewRestoreScroll()`), not with `document.write`.
- Hooks: the end of `render()`, `scheduleAutosave()`, the `pg-design-theme-change` event, `_setCustomField()`, `_pgTabsSwitch()`, the settings modal's `hidden.bs.modal`, inline text editing on the canvas (`liveTextObs`, commit, Escape) and the properties panel's `[data-prop]` text boxes (these two pass through neither `render()` nor `saveState()`).
- With the window closed a hook costs one property read. A new state that reaches the preview through a path none of these hooks sees gets the call added.

## Where do the Preview's scripts come from?
- A platform script that a page gets from a marker (theme switcher, tooltips/popovers `pg_bs_tips.js`) is written twice: on the page by `pg_*_inject()` (`get_page_content.php`), in the Preview by a `_SD_PAGE_SCRIPTS` row. The Bootstrap bundle is added only when no `bootstrap-js` row is there; the `#` link guard and the switcher engine sit in `_sdPreviewHarness()`, the dark view in `_sdPreviewDarkView()`. `_sdInsertBeforeBodyEnd()` finds the last `</body>` on the markup itself: an index from `html.toLowerCase()` moves one place per `İ`.

Related: docs/memory/designer/designer-widget-ghosts-and-edit-chips.md, docs/memory/designer/designer-preview-page-scripts.md
