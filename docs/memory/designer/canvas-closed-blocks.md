---
name: canvas-closed-blocks
description: Designer canvas closed blocks after their opening (CB0-CB10, 2026-11): the rules an opened block keeps, which step opened what (file, PR), what stays closed
---

## What is a closed block?
- A box the canvas draws as one piece: its inner elements cannot be picked, styled, moved or deleted one by one.
- Four families, each saying what a designer can open today and what stays closed: content nodes (`canvas-closed-blocks-content.md`), sections and HTML values inside system widgets (`-widgets.md`), region nodes and classic page types (`-regions.md`), e-mail pages (`-emails.md`).
- Every palette component is a node tree (`palette-component-atomic.md`); every system widget kind (`SW_TYPES`) has a tree of bound nodes.

## Why were they opened, and in which order?
- Owner (2026-11-13, #9076): the components the canvas draws as one closed block are listed and opened into editable elements. Record: `workflow/decisions-made-product-48.md`.
- Owner (2026-11-15, channel message 4146): the proposed order, most met first; Custom HTML, SVG and Custom PHP stay closed. Record: `workflow/decisions-made-product-54.md`.

## Which rules does an opened block keep?
- Tree: the slot becomes nodes on data bindings (`system-widget-bindings.md`), its output compared with the classic output it replaces (`system-widget-change-checklist.md`), in a test against development's builder.
- A published page keeps its output until its user converts it: a saved page keeps its section; "Convert to design elements" on the section's panel, or a starter, gives the tree.
- Output that depends on the request (the visitor, cart, order, a token, a site setting) stays a placeholder or section in the saved body: the block gets real settings and a true drawing on the canvas, a JS twin of a pure PHP builder, never a stored tree (`language-switcher-component.md`).
- Converter: a classic region gets a button that puts a widget in its place or beside it (`canvas-closed-blocks-regions.md`).
- No schema change for an opening; a need goes to the architect.

## Which step opened what?
- CB0 (#9254) the census; CB1 the owner's order (above).
- CB2 (#9716) comments slots and search pagination: `comments-widget-open-slots.md`; CB2b (#9765) the Page links list: `paging-list-canvas-line.md`.
- CB3 (#9685) comment e-mail System region: `comment-email-pages-system-region.md`. CB4 (#9806) custom form slots: `custom-form-open-slots.md`. CB5 (#9723) Messages node: `messages-node-settings.md`.
- CB6a (#9849, #9871) catalog detail: `catalog-detail-open-blocks.md`, `catalog-detail-variant-price-blocks.md`. CB6b (#9890) catalog: `catalog-listing-open-blocks.md`. CB6c (#9901) cart: `cart-open-blocks.md`.
- CB6d (#9920, #9966) order preview: `checkout-open-blocks.md`, `checkout-open-recipients.md`. CB6e (#10002, #10163) My Account: `account-order-open-blocks.md`, `account-subscription-credit-open-blocks.md`.
- CB7 (#9895) sign-in and sign-up: `member-widgets-open-slots.md`. CB8 (#9918) booking: `booking-widget-open-parts.md`. CB9 (#9932), CB9b (#9981) calendar: `calendar-widgets-open-slots.md`. CB10 (#10060) classic sites: `canvas-closed-blocks-regions.md`.

## What stays closed, in short?
- The user's own code: Custom HTML, SVG, Custom PHP (owner, 2026-11-15).
- Markup kept by rule: a select's option list, a form's own answers, an ad's markup, a section's action forms with their token; per kind in `-widgets.md`.
- Nine classic page types with no widget, Dynamic region, Mobile Switch and PDF regions (`-regions.md`).
