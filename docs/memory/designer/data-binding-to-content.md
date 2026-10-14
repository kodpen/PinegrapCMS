---
name: data-binding-to-content
description: Bind data to content elements (span, h1-h6, p, a, img), never structural table tags; right/wrong example and tooling (_getBindableProps, migration).
---

## Which elements may carry a data binding?
- Bindings go ALWAYS on a content element: `span`, `h1..h6`, `p`, `a`, `img`.
- NEVER bind structural tags: `table, thead, tbody, tfoot, tr, td, th, caption, colgroup, col`.

## Why?
- `<td>` is layout: alignment, width and column membership belong to it. Binding the value directly on the cell means the designer cannot wrap it in a link, cannot add a badge next to it, can only format the value; and a second element left in the cell is silently overwritten by the binding.

## What is the right shape?
```php
// WRONG
$sem('td', 'text-end', '₺39,95', array('bindings' => array('text' => '__item_price')))

// RIGHT
$sem('td', 'text-end', array(
    $sem('span', '', '₺39,95', array('bindings' => array('text' => '__item_price'))),
))
```

## How does the tooling enforce it?
- `_getBindableProps()` does NOT offer text binding on structural tags and shows an explanatory warning instead. If a binding already exists the dropdown keeps showing (otherwise bindings in old trees would become invisible).
- `_sdMigrateStructuralBindings()` automatically moves old bindings to a `<span>`/`<img>` inside the cell when a tree loads (idempotent).

Related: docs/memory/designer/wysiwyg-text-binding.md
