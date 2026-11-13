---
name: canvas-closed-blocks-regions
description: CB0 list, region nodes and classic page types the canvas draws closed: the System region of a classic page, the page types with no widget, the legacy regions of a classic style, what opens each
---

## What does a classic page's System region draw?
- A `region` node of type `system`: the page type's classic renderer (`get_*_screen_content.php`), with its custom layout when it has one (`layout-widget-offer.md`).
- Canvas: the label band; on a page with a custom layout, a read-only preview under it as the server draws it (`legacy-region-preview.md`). A named System region (another page's) keeps the plain placeholder.
- Change today: the layout as code (Layout tab, role 1, `layout-code-tab.md`) and the page type's settings; never on the canvas.
- Use: no template places one on a site page (only on comment e-mail pages, `canvas-closed-blocks-emails.md`); met on sites moved from classic styles.

## What opens it today?
- 24 layout page types: "Put a "{kind}" widget beside it", then "Remove the classic region" (`layout-widget-offer.md`).
- Custom form: "Convert to design elements" (`layout-form-converter-editor.md`).
- A classic page with no custom layout: no offer and no preview; the widget is dropped from the palette by hand.

## Which page types have no widget?
- E-mail a friend, form view directory, affiliate confirmation and affiliate welcome (#2436 answer 2a, `system-widget-page-types.md`); order receipt (the order view widget draws the receipt e-mail page, but a classic receipt page type gets no offer).
- Opening: new widget kinds, one per step; #2436 decided they stay `system` nodes, so a step for them needs an owner decision first.

## Which legacy regions does a classic style bring?
- Opened with "Open in the designer" (`classic-style-to-design.md`), they keep their region nodes, marked Legacy (`SD_LEGACY_REGIONS`): Menu, Menu Sequence, Ad Region, Tag Cloud, Mobile Switch, PDF, Common, Designer and Dynamic regions; Login and Cart regions and the Comments Block are not marked.
- Canvas: a label box; the site prints the classic region.
- Open today: Menu, Menu Sequence, Ad and Tag Cloud by "Make designable" (`region-node-to-widget.md`); a Common region by its shared component (`common-region-to-shared-component.md`); a Designer region (`designer-region-to-shared-component.md`).
- No way yet: Login region (the Login Region widget exists, no converter), Cart region (the Cart Link widget exists, no converter), Comments Block (the Comments widget exists; hand-off in `comments-widget-hand-off.md`), Dynamic region (PHP), Mobile Switch, PDF.
- Opening: a converter like "Make designable" for Login, Cart and Comments Block; Dynamic stays code; Mobile Switch and PDF are a question for CB1 (keep or retire).
- Page Region (`pregion`): the page's own content area, edited as the page; not closed.
