---
name: link-target-in-attrs
description: A link's window and rel: props first, _attrs when the prop is empty (link and button link, server and canvas alike), the selection-time fold, starter trees and templates, page bodies drawn before
---

## Where do a link's target and rel come from?
- The content link and the Button component drawn as `<a>` print `target` and `rel` from their props (the Target and Relationship options). When a prop is empty, an `_attrs` entry of the same name prints instead, the way the editor's canvas and code view print it; a prop that is set wins and its `_attrs` entry is skipped, so each attribute prints once (`_render_content_html()` `case 'link'`, `_render_component_html()` `case 'btn'`, `includes/fn/designer.php`).
- A Button drawn as `<button>` or `<input>` prints neither. The semantic `<a>` always did the same with its builtins.

## What was wrong before?
- The link and the button link skipped the `_attrs` window unconditionally: a window typed in the Attrs panel, or written there by a starter tree, showed as a new window in the designer and opened in the same window on the page. My Account's order view ("Print Invoice", "Track the Shipment") and My Invoices ("PDF") did this on every site.
- The content link never printed its `rel` prop, so the Relationship option did nothing on the page; `rel` in `_attrs` printed.
- PR #878 first moved the window into the prop inside the order view widget only (`pg_sw_link_attrs_to_props()`); PR #915 made the renderer read `_attrs`, which replaced that pass; the function is gone.

## What does the designer do?
- On selection, `migrateNodeLinkProps()` (`style_designer.js`, beside `migrateNodeStyleProps()`) folds an `_attrs` `target` / `rel` into the prop when the option offers the value (link target `_blank _self _parent _top`, button link `_blank _self`, the Relationship list), and drops an `_attrs` entry that sits beside a set prop (the page ignores it). A value the option cannot hold (a named frame, `noopener nofollow`) stays in `_attrs`. Silent, no undo entry; nothing moves on the page.

## What do starter trees and templates carry?
- Starter trees: links that open a new window have `target: '_blank'` as a prop and `rel: noopener` in `_attrs` (order view since #878, My Invoices since #915). No starter tree keeps a window in `_attrs` (scan of every `StyleDesigner.starterTree()` kind in jsdom).
- Shipped templates (`includes/design_templates/`): their own trees already give the window as a prop. Six of them take the order view and/or My Invoices from the starter tree (`'tree' => 'starter'`), so they deliver the changed links; their versions went up one patch: hello-pinegrap 2.4.10, bookshop 1.0.8, boutique 1.0.8, clinic 1.0.2, playground 1.0.7, store 1.0.9. Cafe takes neither.
- `tests/designer_link_target_test.php` checks the renderer, the two starter trees and that no shipped template keeps a link's window in `_attrs`.

## When does an existing site see it?
- System widgets draw from their tree on every request: at once.
- A page body is the HTML stored at save (`page.page_tree_code`): a page whose link kept its window in `_attrs` gets it on its next save or with "Save and rebuild page HTML" (designer/page-html-rebuild.md).

Related: docs/memory/commerce/order-view-legal-documents.md
