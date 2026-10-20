---
name: link-target-in-attrs
description: A link's window (target) written in _attrs: the PHP link and button renderers skip it while the canvas prints it; pg_sw_link_attrs_to_props() in the order view, the widget still affected
---

## Why does a link open in the same window on the page but a new one in the designer?
- The editor's canvas and code view print every `_attrs` entry. The server's renderers (`includes/fn/designer.php`) read `target` from props and skip an `_attrs` entry of that name: the content link (`case 'link'`, `_render_extra_attrs($props, array('href', 'target'))`) and the Button component drawn as `<a>` (`case 'btn'`, which also skips `rel`).
- The semantic `<a>` is not affected: it prints the `_attrs` window unless a prop already gave one.
- The Link Options "Target" box reads and writes the prop. A window lands in `_attrs` when it is typed in the Attrs panel or a starter tree writes it there.

## What does the order view do?
- `pg_sw_link_attrs_to_props()` (`includes/fn/widgets.php`) moves an `_attrs` `target` into the prop of every content link in the tree, and `target` and `rel` of every Button component drawn as a link; a prop already set wins, an empty value moves nothing, names compare in lower case like the renderer's skip.
- `_render_system_widget_order_view()` calls it right after decoding the tree, before the not-found closure takes the tree (PR #878). It covers layouts built from the starter tree before the change: their "Print Invoice" and "Track the Shipment" links kept the window in `_attrs` and opened in the same window.
- The starter tree gives every link `target: '_blank'` as a prop, `rel: noopener` in `_attrs` (the content link prints `rel` from `_attrs`).
- Tested in `tests/widget_link_target_test.php`.

## Which widgets still have it?
- My Invoices (`account_invoices`): the starter tree's "PDF" link (`__invoice_pdf_url`) keeps the window in `_attrs`. No other starter tree did (scan of every `StyleDesigner.starterTree()` kind in jsdom, 2026-10-20).
- Any link on any page or widget whose window was typed in the Attrs panel.

## What would fix it everywhere?
- The `link` and `btn` cases reading `target` (and `rel` for `btn`) from `_attrs` when the prop is empty, the way the semantic `<a>` does. Then the order view's call and the helper are no longer needed, and `test_widget_link_target_renderer_skips_attrs_target` changes.

Related: docs/memory/commerce/order-view-legal-documents.md
