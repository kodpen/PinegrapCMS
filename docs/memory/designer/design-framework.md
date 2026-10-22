---
name: design-framework
description: A visual design's framework (style.style_framework, bootstrap5 or custom): the pg_design_frameworks() registry, written once at creation, what a custom design does without, the new-design question.
---

## Where is the framework kept?
- `style.style_framework` (2026.4.5, with `style_template` and `style_template_version`; `pg_style_framework_ready()`): `bootstrap5` or `custom`; Bootstrap 6 will be one more entry. An empty or unknown value reads as `bootstrap5` (`pg_design_framework_key()`).
- One registry, `pg_design_frameworks()` (`includes/fn/designer.php`): label, CSS and JS addresses, version, and `bootstrap` (whether the Bootstrap palette, grid and blocks apply). The front end (`get_page_content.php`), the canvas, the preview and the sentinel rows read it; JS reads `sdDesign.frameworkInfo` through `_sdFrameworkInfo()` / `_sdUsesBootstrap()`. Never write a framework address anywhere else.

## When is it written?
- Only when the style row is created (`save_system_style()` with no `style_id`); an update never changes it, because the pages were written against it.
- A copy (`duplicate_style.php`) and a page detached into a design of its own (`designer/page_detach`) carry the source's. A new path that creates a style row carries it too, or the copy of a custom design becomes Bootstrap 5.

## What does a custom design do without?
- No `bootstrap-css` / `bootstrap-js` sentinel rows, no Bootstrap fallback on the front end (printed only when the registry gives an address), no look or palette (`design-theme-look-and-palette.md`).
- A palette group that relies on Bootstrap classes is flagged `bootstrap: true`; an unflagged group shows in a custom design too.
- To hide the editor's own wrappers on the canvas do not rely on a Bootstrap class (`d-none`): write the rule in `IFRAME_CSS`, since a custom design's canvas has no Bootstrap.

## How is it chosen?
- Every new design is asked first, whatever the start (blank, import, paste): `#sdNewDesignModal` on the designs home, the start kept in `data-sd-start`. `add_system_style.php` sends a `start=import|paste` that has no `framework` back to that question; a template brings its own framework.
- Imports follow it (`framework_bootstrap`, `keep_framework`): `html-import-and-paste.md`.

Related: docs/memory/designer/design-asset-records.md
