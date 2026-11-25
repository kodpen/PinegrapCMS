---
name: designer-compare-look-tab
description: Compare's Look tab (part compare, CL2): its sides, the pictures drawn by compare_render, the slider, Side by side, the width, the states, History's choosers
module: seo_draft
---

## Where does the Compare window live?
- Designer part `compare` (`includes/designer_parts/compare.php`, `when` always): `pg_compare_changes.js` (the reader), `pg_compare_look.js` (`window.PgCompareLook`, the pure rules) and `style_designer_compare.js` (the window). The core keeps `_sdCompareOpen(opts)` and `_sdRevisionOpenCompare(pageId, no, done)`, History's row button.
- `opts`: `pageId`; `left` / `right` as `now` (the tab's tree), `live` (the page on the site) or `rev:<no>`, default `live` and `now`; `history` (the revision whose row opened it: its head, the side choosers, "Load this revision"); `done`. The Activity tab (`designer-activity-tab.md`) opens it with `left: 'rev:<n-1>', right: 'rev:<n>', history: n`.

## What is the page on the site?
- The tab's stored tree: `PgCompareLook.liveTree(p.baseline)` (the baseline is the tree's JSON, then `|` and the settings). For a page not open in a tab, the newest revision. A draft page (`page_draft` with drafts ready) or one never saved has none: the reader's `new`.
- "since …" is the newest revision's time from `revisions_list`, fetched when the window opens.

## How is a picture drawn?
- Panel action `designer/compare_render` (`page_id`, `tree_json`, `widgets`; listed clean in `pg_page_cache_panel_sub_actions()`): `pg_seo_check_draft_render()` with no fields, so the page's stored title, the session lent empty, `data-pg-node` on each element. Nothing written, no visit counted. Answers `html: ''` when it cannot draw (view mode, a refused tree): the editor draws that picture itself (`_sdPreviewDocHtml()` over `generateHTML()`).
- The draft side sends the editor's system widgets (`_sdSeoCheckBody()`), as the Preview; every other side shows the stored ones.
- The frame is `sandbox="allow-same-origin"`, never `allow-scripts`, filled with `srcdoc` (a `<base>` to the site put first by `withBase()`), so the window reads the page's height. The left picture is drawn first, the right one once the left has loaded or failed; 12 s without `load` gives that half "… could not be drawn." and "Try again", which draws that picture only.

## How do the slider and Side by side work?
- Both pictures are the whole page, one over the other in one scroll box, scaled down to the room (never up); the right one is clipped with `clip-path: inset(0 0 0 <pos>%)`. The handle starts at 50; drag, a click on a picture, arrows (2, Shift 10), Home and End. Its place is not kept.
- Side by side: the two next to each other, each under its name, in the same scroll box. Width: Desktop 1280 px or Phone 390 px. The form and width chosen last are kept in localStorage `sd-compare-look`.
- `new`: the draft alone with "This page is not on the site yet. This is the draft alone.", no handle, no Side by side. `same`: the left picture alone, no handle. Under 768 px the window fills the screen and shows the slider alone at phone width.

## What does the foot say, and what is the Code tab?
- The count of the reader's rows: "7 changes between the page on the site and the draft." (live against now), else "… between the two versions."; "Nothing has changed.", "The whole page is new.", or the unreadable sentence. The list beside the pictures: `designer-compare-what-changed.md`.
- "Code": the line diff of the two sides' `generateHTML()` markup, drawn when the tab is first shown.

Tests: `tests/designer_compare_look_test.php` (Node).
