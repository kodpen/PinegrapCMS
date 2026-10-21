---
name: html-import-and-paste
description: Designer HTML import: three entry points, one converter, and why a canvas paste is not an import (no disk, style/script dropped, URLs kept).
---

## What feeds the HTML converter?
`includes/designer_import.php` turns HTML into nodes. Three paths:
| Path | Endpoint | Result |
|---|---|---|
| File (.html / .zip) | `designer_import.php` (multipart) | page + assets + files |
| Pasted page | `api.php designer/import_paste` | page + assets, no files |
| Paste onto canvas | `api.php designer/import_fragment` | nodes only |
- `api.php` reads the body as JSON, so the file-carrying path is a separate script. `_pgImportApply()` is the only place turning the reply into tabs; never write a second copy.

## What does `pg_designer_import_fragment()` do differently?
- It runs with `$ctx->fragment = true`. A paste is not an import:
  - Does NOT write to disk. Inline `<svg>` goes to `custom_html`, not a file (a Bootstrap icon still becomes an icon node). A Ctrl+V must not leave a file in File Manager.
  - `<style>` / `<script>` are counted and discarded, NOT added to the design's shared assets: one paste would change every page of the design and the asset panel is not in the undo stack. The toast says how many blocks were skipped.
  - An unresolvable address stays as is. The `about.html` -> `/about` rewrite is right only when the archive's page list exists; on paste a guess produces a broken link indistinguishable from a working one.

Related: docs/memory/designer/designer-paste-clipboard.md

Lazy-loaded images (placeholder in src): docs/memory/designer/import-lazy-images.md
