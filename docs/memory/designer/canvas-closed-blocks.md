---
name: canvas-closed-blocks
description: CB0 list (2026-11-13): what the designer canvas draws as one closed block, in four families, the counts, and the proposed order of opening them (for CB1, the owner's answer)
---

## What is this list and how was it made?
- CB0 of `plans/designer-closed-blocks.md`, read from code on 2026-11-13, no browser run. CB1 takes it to the owner; the steps from CB2 on are written from his answer.
- A closed block: a box the user cannot open on the canvas; its inner elements cannot be picked, styled, moved or deleted one by one.
- Families: content nodes drawn by the server (`canvas-closed-blocks-content.md`), section slots and HTML values inside system widgets (`-widgets.md`), region nodes and classic page types (`-regions.md`), e-mail pages (`-emails.md`).
- Counts: the 57 design templates (`includes/design_templates/`, files and folders). 32 of them are shop templates (catalog, cart, checkout, order view widgets).

## What is open already?
- Every palette component but three is a node tree (`palette-component-atomic.md`): `btn` and `badge` are one element, not closed blocks.
- Every system widget kind (`SW_TYPES`, 47) has a tree of bound nodes; only the slots in `-widgets.md` are closed inside it.

## What does opening take, by kind?
- Tree: the slot becomes nodes on bindings (`system-widget-bindings.md`), compared with the classic output (`system-widget-change-checklist.md`).
- Settings only: output depends on the request (`language-switcher-component.md`); the block gets real settings and a true drawing, no stored tree.
- Converter: a classic region gets a button that puts a widget beside it (`layout-widget-offer.md`, `region-node-to-widget.md`).
- Stays closed: the user's own code (Custom HTML, SVG, Custom PHP); its editor is the code box.

## Which order is proposed? (most met first)
1. Comments widget slots: CAPTCHA, star rating input, publish options, watcher list, pagination; in all 57 templates and every comment e-mail (`-widgets.md`).
2. Comment e-mail pages: the System region after the comment card, 57 templates (`-emails.md`).
3. Custom form slots: CAPTCHA (57 templates), and on a form with steps the step break, the step progress and the step buttons.
4. Messages node: on every widget page of 57 templates; settings only (its look), with a true sample on the canvas.
5. Shop: catalog detail (variant attribute, breadcrumb, product carousel, price block), catalog (category tree, filter offcanvas), cart (applied offers, quick add, campaign gifts), checkout steps, order view returns, My Account order history (40 templates).
6. Login and sign-up slots: social sign-in buttons, password rules, sign-in and sign-up links (login 56, registration 57 templates).
7. Search pagination (57 templates): small; may go with 1 as one pagination family.
8. Booking widget steps (16 templates).
9. Calendar picker, month grid and reserve form (no template).
10. Classic sites: the System region of a classic page type (preview only), the page types with no widget, and the legacy regions (`-regions.md`). No template; met on sites moved from classic styles.
- Not opened: Custom HTML, SVG, Custom PHP; the Language Switcher Button (already drawn true, settings only).
