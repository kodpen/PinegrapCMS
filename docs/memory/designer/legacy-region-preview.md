---
name: legacy-region-preview
description: The canvas's true picture of every region node (LE4): markers around each region tag, designer/legacy_region_preview, the band and pill, the match, who may ask
---

## What does the canvas show for a region node?
- Every region node but the Page region (the page's own content) and the Comments Block (its placeholder): a band (the kind's icon, its label and name, a pill "Code" for a Dynamic region or the page's own System region drawing a custom layout, "Classic" for the rest), and under it the region as the server draws it for the signed-in user, read only (`_sdRegionTrueAppend()` in `style_designer.js`).
- Nothing drawn: one muted line, "Drawn on the site; nothing to show here". A page not saved, or a node missing from the answer while the page has unsaved changes: "Save the page to see it". Not answered yet: the band alone.
- DOM only: `inert`, `aria-hidden`, no `data-sd-id`, never in the tree JSON. A click selects the region node.
- Nothing of it may run in the editor, (same origin, the designer's session, #1950): `script`, `iframe`, `object` and `embed` are dropped; ids, `on*` attributes and every attribute holding a `javascript:` address (`_sdLegacyRegionJsUrl()`) are removed (`_sdRegionTrueClean()`).

## How is a node matched to its region?
- By key `tag:name` (`_sdRegionTrueKey()`; twin of `pg_layout_region_tags()`): a Common and a Designer region are both `cregion`, a Tag Cloud `tcloud`; Cart, Mobile Switch and PDF carry no name. Then by order: the n-th node of a key on the canvas, in document order, takes the n-th region of that key in the render (`_sdRegionTrueFill()`, after the canvas is drawn).
- A named System region draws the region its name gives, as on the site.

## How does the server cut the regions out?
- While `pg_layout_region_marking()` is on, `get_page_content.php` runs `pg_layout_region_mark_tags()` on the page just before its first region pass (designer regions): each region tag gets `<!--pg-region-start:ID:tag:name:order-->` before and `<!--pg-region-end:ID-->` after, name URL-encoded, ID unique. Every pass replaces the tag as before, so its output lands between them. Off in every other request.
- `pg_layout_regions_cut()` returns each region (type, name, order, html), a region inside another (a common region in a designer region) on its own, with inner markers taken out.
- A tag a pass writes later (in a designer region's or page region's content, the Comments Block's synthetic System region) is not marked: no node stands for it.

## Which state is drawn, and how?
- `pg_layout_regions_preview($page_id)` (`includes/fn/layouts.php`): the page's saved state (stored tree, type settings, layout file), every page, not only one with a custom layout. A background render of `get_page_content()` in `'preview'` mode (`pg_seo_rendering(true)`, `pg_error_throws(true)`, buffered, all put back in `finally`). An error gives no region.
- It runs as the caller with their session: their view (account, cart, access); no status code, no view counted, no edit chip. Not free of writes: the classic renderers touch the caller's own session and cart as for any visitor. A custom form returning to a page the caller submitted before redirects: no JSON, the bands stay alone.
- Asked once a page; again after a design Save or a layout tab save (`_sdLegacyRegionForget()`), the last answer shown meanwhile.

## Who may ask?
- api.php `designer` sub-action `legacy_region_preview`: in the content operators' list, so everyone who may open the editor; a content-level operator is refused a page `pg_designer_page_access()` hides from them. Page cache: clean. Answer: `regions`.

## What does the Region panel say?
- Dynamic region: "PHP code: it stays code and runs on the site as written", then its Edit PHP code button (administrators, as before). Mobile Switch and PDF: "It has no design element; the site draws it as before", no action. A System region keeps its lines and buttons (`canvas-closed-blocks-regions.md`).

## Tests?
- `tests/designer_legacy_region_preview_test.php`.
