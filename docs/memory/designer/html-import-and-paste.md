---
name: html-import-and-paste
description: Designer HTML import: three entry points, one converter, inline SVG and Bootstrap icons, framework files, repeated blocks made shared, and why a canvas paste is not an import.
---

## What feeds the HTML converter?
`includes/designer_import.php` turns HTML into nodes. Three paths:
| Path | Endpoint | Result |
|---|---|---|
| File (.html / .zip) | `designer_import.php` (multipart) | page + assets + files |
| Pasted page | `api.php designer/import_paste` | page + assets, no files |
| Paste onto canvas | `api.php designer/import_fragment` | nodes only |
- `api.php` reads the body as JSON, so the file-carrying path is a separate script. `_pgImportApply()` is the only place turning the reply into tabs; never write a second copy.

## What becomes of inline SVG and icons?
- An inline `<svg>` is never written to a file: on every path it becomes an `svg` content node (`html` = the drawing, its `class` → `cssClass`); one with a `bi-*` class becomes an icon node. `pg_di_lift_svgs()` takes each one out before parsing (libxml nests `<path/>`), counting nested `<svg>`. The markup is cleaned when drawn: `pg_designer_svg_markup()` (PHP) and its twin `_sdSvgElement()` (JS).
- A text-less `<i>`/`<span>` with a `bi-*` class becomes an icon node (`pg_di_icon_props()`); a link holding one is built as nodes, not as a link content node (`pg_di_has_icon_child()`). An icon inside heading or paragraph text stays in the rich text.
- In the editor every element that draws an icon by its class gets the icon picker (`_sdBiIconClass()`, `_sdBiIconSection()`, `_sdSetBiIcon()`).

## What happens to Bootstrap, jQuery and Popper files?
- `pg_di_is_framework_url()` drops them only for a Bootstrap 5 design, whose sentinels load its own. The client sends `framework_bootstrap` ('1'/'0'); with '0' the server sets `keep_framework` and the project's bootstrap/jquery/popper come through like any file. A new import path carries the flag too (`design-framework.md`).

## What happens to blocks several imported pages repeat?
- `_pgImportFindShared()` finds them (trees compared without `_id`, ignoring `active` and `aria-current`) and the editor asks whether to make them shared components. Such a row carries `category = 'import:<project>'` and, like a template's rows, is deleted when the operator leaves a design that was never published (`design-templates-rows.md`).

## What does `pg_designer_import_fragment()` do differently?
- It runs with `$ctx->fragment = true`. A paste is not an import:
  - Does NOT write to disk: a Ctrl+V must not leave a file in File Manager.
  - `<style>` / `<script>` are counted and discarded, NOT added to the design's shared assets: one paste would change every page of the design and the asset panel is not in the undo stack. The toast says how many blocks were skipped.
  - An unresolvable address stays as is. The `about.html` -> `/about` rewrite is right only when the archive's page list exists; on paste a guess produces a broken link indistinguishable from a working one.

Related: docs/memory/designer/designer-paste-clipboard.md
