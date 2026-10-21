---
name: data-binding-to-content
description: Bind data to content elements (span, h1-h6, p, a, img), never structural table tags; tooling, the text slot a binding fills inside markup, and the characters a bound field name keeps.
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

## What does a text binding replace in a text with markup?
- Content `heading`, `link`, `paragraph`, `span` and `text` print their text as written, markup included. A `text` binding keeps that markup: the token takes the place of the one run of words the markup wraps, so `<a href="/post">Title</a>` keeps its link (`pg_binding_text_slot()` in `_apply_bindings()`, on the canvas `_sdBindTextSlot()`; change both together).
- No markup, markup around no words, or several runs of words: the whole text becomes the token.

## Which characters may a bound field name hold?
- `[a-z0-9_-]`: `_apply_bindings()` and the editor's custom token box both keep the hyphen, because a form field may be named `publish-date` (without it the token named no field and printed nothing). Never add a filter that strips the hyphen.
- A binding typed before the hyphen was kept reads the bare spelling (`publishdate`); `pg_sw_index_form_data()` indexes each field under both.

Related: docs/memory/designer/wysiwyg-text-binding.md
