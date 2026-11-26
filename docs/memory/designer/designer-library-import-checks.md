---
name: designer-library-import-checks
description: The checks an import into My library runs (script, event attribute, address kinds), all or nothing, and the refusal's words
module: designer_library_io
---

## What does an import accept?
- `pg_library_import_read($text)`: an export file or one item's own file (`pinegrap-library-item`), at most 1 MB (`PG_LIBRARY_EXPORT_MAX`, the same as one item).
- Every item passes `pg_library_item_decode()` and `pg_library_item_problem()`. One item that fails refuses the whole file: `error` (code), `item` (its name, or `#place`), `detail` (the attribute or the scheme). Nothing of the file is kept.

## What do the checks refuse?
- `pg_library_item_problem()` returns `null` or `code` and `detail`; it reads the tree, the trees of the item's widgets and shared components, the widget settings, the rules and the variables. Codes: `script` (a `script` node or `tag`, `<script>` in a string, `srcdoc`, CSS `expression(`, `-moz-binding`, `behavior:`, `@import`), `event` (an `_attrs` name or a tag attribute starting with `on`, detail its name), `address` (detail the scheme), `deep` (past 80 levels).
- `pg_library_address_refused()`: allowed are no scheme (a site path, `#`, `?`, `//host`, a `{{token}}`), `http`, `https`, `mailto`, `tel`, `sms`, and `data:image/` png, jpeg, gif, webp, avif (not svg). Entities (a numeric one without its `;` too, as a browser reads it) and blanks are taken out before the scheme is read.
- Markup: an attribute name is read in any case (`ONERROR`); each start tag is read up to its first `>` and again with quoted values whole (`alt=">"`), and a tag left open at the end of a text is read too. The `to`, `from`, `by` and `values` of a tag (SVG `<set>`, `<animate>`) are read as addresses, each `;` part.
- Style: CSS escapes are read first (`\64 ata:`); a `url()` address runs to its own quote, so `)` or the other quote inside it hides nothing.
- An address field (`pg_library_address_key()`: `href`, `src`, `srcset`, `action`, `poster`, `url`, `link`…) is checked whatever its text; any other string only when it starts with `javascript:`, `vbscript:` or `livescript:`, holds a tag, or holds `url(`. "Learn javascript: basics" passes.

## How is a refusal worded?
- `pg_library_import_message($error, $file, $item, $detail)`, the drawing's words: "“photo.json” is not a library file.", "“old-kit.json” holds a script in the item “Promo banner”.", "… holds an event attribute (onclick) in the item …", "… holds an address that is not allowed (javascript:) in the item …", "“big-kit.json” is larger than 1 MB.", "“new-kit.json” was made by a newer Pinegrap. Update this site, then import it."
- The window's title is "This file was not imported", then the reason, then "Nothing was added to the library." and Close.

## How is it tested?
- `tests/designer_library_io_test.php`: the address kinds, the text checks, the item checks in each place, the refusals and their words.
