---
name: dom-node-html
description: pg_dom_html() instead of $document->saveHTML($node): one DOM node's HTML the same on every PHP version (7.1-7.2 pretty-printed it), its callers and its test
---

## Why not $document->saveHTML($node)?
- Before PHP 7.3 it calls libxml's `htmlNodeDump()`, which pretty-prints whatever `formatOutput` says (PHP bug #76285, fixed in 7.3.0); from 7.3 it does so only with `formatOutput` on. libxml then writes a line break after a block element whose next sibling is not text, and inside a block whose first or last child is not text (`<p>A</p>\n<p>B</p>`, `<ul>\n<li>a</li>\n</ul>`). The PHP version decides, not libxml2.
- Symptom fixed (#1355): on PHP 7.1 and 7.2, rich text filtered by `pg_sanitize_rich_text()` (form answers, template sample records, API event descriptions) was stored and printed with extra line breaks, so one input gave different HTML per PHP version, and the designer's HTML import and AI page edits kept them in custom HTML blocks (`<map>`, `<template>`, `<object>`) and heading text.

## What does pg_dom_html() do?
- `includes/fn/core.php`. On PHP 7.3+ with `formatOutput` off (every product document) it is `saveHTML($node)`, byte for byte. Otherwise it saves a deep copy of the node with an empty text node before a first child, after a last child and after every element child that text does not follow: libxml writes no break next to text and nothing for empty text. The caller's tree is not changed.
- Callers: `pg_sanitize_rich_text()` (`editor.php`), `pg_tr_html_text_segments()` (`translate/content.php`), `pg_di_inner_html()` and `pg_di_outer_html()` (`designer_import.php`), `_pg_dai_inner()` (`designer_ai.php`). A new place that needs one node's HTML calls it, not `saveHTML($node)`.

## How is it tested?
- `tests/dom_html_test.php` turns `formatOutput` on, so the copy path runs on every PHP version, CI's 8.3 included; without the copy step it fails there. `test_custom_form_rich_text_seed_value` is the PHP 7.1 regression check.

Related: docs/memory/designer/custom-form-rich-text.md, docs/memory/core/content-translation-html-pieces.md
