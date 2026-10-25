---
name: comments-widget-recent-panel
description: The recent comments list's panel section: what it shows, the pages it offers and how each is labelled, tabs whose components are not loaded yet, notes, record filters, the stored page list
---

## What does the section show?
- `_sdCommentsRecentSect()` instead of Display and Comment settings: Shows, Comments from (the design's pages with a top-level record view, "{page} — records of {form}", or comments on, "{page} — the page's comments"; at five the rest disable; a page outside the design is "Another page (#id)"), Comment order (mine, pending), Number of comments; notes: comments off, viewer-filtered view, an unfiltered view lists every complete record with a comment, audiences, each comment follows its page's settings, the Preview is signed out.
- Only the open tab's components are loaded: a tab placing one not in `_sharedCache` is "{page} — loading its widgets…" (no notes) until `_sdCommentsRecentLoad()` fetches them all once (`_sdCommentsRecentAsked`) and redraws; one the server does not return counts as no record view.
- Record filters (#1679): the form list's editor (`_swFormListFiltersHtml(..., true, 'recent_filters')`: that form's fields, values from the address, a note of its own) while exactly one page with a record view is ticked, and whenever `recent_filters` is not empty; then, over a page source, several pages or none, an amber line says they are not applied. A page outside the design, or one still loading, gets no line and no incompatible marks. The rows and Add carry `data-sw-filter-key`; the shared handlers write that key (`filters` without one). Fields come from `_fetchFormFields()` only for a saved form id.
- `[data-sw-cfg-list-kind="page"]` writes `_swCfgListValues()`: ids or `tab:<key>`, five at most. `_swResolveTabRefs()` walks arrays, as the server's `pg_designer_resolve_tab_values()`; export, install and Preview walk the list too.

## How is it checked?
- `tests/comments_widget_designer_test.php` under Node: `recent_section` (labels, notes, five-page cap, a tab's key), `recent_filters` (when the editor shows, its key, fields and warning), `filter_editor_key` (source) and `recent_section_cold_cache` (an empty component cache: tabs are loading, never page sources; one fetch for every tab; redrawn once; nothing fetched twice; a component the server does not return no longer holds a tab back). No browser run.
