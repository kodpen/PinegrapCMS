---
name: canvas-closed-blocks-regions
description: Region nodes and classic page types on the canvas today: the System region converter (CB10, #10060), the nine page types with no widget, the legacy regions
---

## What does a classic page's System region draw?
- A `region` node of type `system`: the page type's classic renderer (`get_*_screen_content.php`), with its custom layout when it has one (`layout-widget-offer.md`).
- Canvas: the label band; on a page with a custom layout, a read-only preview under it as the server draws it (`legacy-region-preview.md`). A named System region (another page's) keeps the plain placeholder.
- Kept as a region, it changes only as the layout code (Layout tab, role 1, `layout-code-tab.md`) and the page type's settings, never on the canvas; converting it (below) gives a widget tree.
- Use: no template places one on a site page (only on comment e-mail pages, `canvas-closed-blocks-emails.md`); met on sites moved from classic styles.

## What opens it today?
- Every page type a widget stands in for (CB10, #10060), custom layout or not: "Convert to design elements" on the page's own System region puts that widget in the region's place, after a confirm, one undo step (`_sdClassicConvert()`).
- Offers: `_design.classicWidgets` from `pg_classic_widget_offers()` (`includes/fn/layouts.php`) for every tab's page, roles 0-1; kinds `pg_classic_widget_kinds()` = the layout kinds plus `pg_sw_page_type_widgets()` (calendar view and event view, folder view, logout, affiliate sign up form added). Settings carried as the layout offer's (`pg_layout_widget_seed()`).
- 24 layout page types keep "Put a "{kind}" widget beside it", then "Remove the classic region" (`layout-widget-offer.md`), under the converter.
- Custom form: "Convert to design elements" of the layout tab (`layout-form-converter-editor.md`).

## Which page types have no widget, and why do they stay closed?
- E-mail a friend, form view directory, affiliate confirmation and affiliate welcome (#2436 answer 2a, `system-widget-page-types.md`); order receipt (the order view widget draws the receipt e-mail page, but a classic receipt page type gets no offer).
- Also no converter (listed for the architect with CB10, #10060): custom form confirmation, registration confirmation, membership confirmation, error. All nine are `pg_classic_no_widget_types()`.
- Architect (2026-11-16, CB10): they stay System regions (#2436); a widget kind for one needs the owner's decision first, not asked while nobody wants one. Their Region panel shows no button, only a muted line: the type has no design widget, its layout is edited in the Layout tab (a page with a custom layout) or the region keeps drawing it from the page type's settings (`_design.classicNoWidget`, `pg_classic_no_widget_page_ids()`).

## Which legacy regions does a classic style bring?
- Opened with "Open in the designer" (`classic-style-to-design.md`), they keep their region nodes, marked Legacy (`SD_LEGACY_REGIONS`): Menu, Menu Sequence, Ad Region, Tag Cloud, Mobile Switch, PDF, Common, Designer and Dynamic regions; Login and Cart regions and the Comments Block are not marked.
- Canvas: a label box; the site prints the classic region.
- Open today: Menu, Menu Sequence, Ad and Tag Cloud by "Make designable" (`region-node-to-widget.md`); Login, Cart and Comments Block by the same button (CB10, #10060); a Common region by its shared component (`common-region-to-shared-component.md`); a Designer region (`designer-region-to-shared-component.md`).
- Stay closed: Dynamic region (PHP, stays code, as Custom PHP does); Mobile Switch and PDF, which no step opened (whether to keep or retire them was not asked).
- Page Region (`pregion`): the page's own content area, edited as the page; not closed.
