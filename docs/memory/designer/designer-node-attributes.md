---
name: designer-node-attributes
description: The attributes every designer content, semantic and grid node writes on the page and in the editor HTML: one style order, font quoting, title and tooltip, Attributes panel rules, sizing on content
---

## Which code writes them?
- The page: `_compose_inline_style($props, $extra, $sizing)` and `_render_extra_attrs($props, $skip, true)` (with `_pg_node_title_attrs()`) in `includes/fn/designer.php`, called by `_render_content_html()`, the `semantic` case of `_render_tree_node()` and `_pg_grid_open_tag()`. The editor's HTML (code view, preview, revision compare): their twins `_sdNodeStyle()`, `_sdNodeExtraAttrs()`, `_sdNodeTitleAttrs()` in `style_designer.js`, called by `addGlobalAttrs()`, the semantic case of `_sdEmitNode()` and `_sdGridOpenTag()`. The saved page is the PHP side; JS follows it. Components keep their own markup (their `title` is heading text).
- Tests: `tests/designer_node_attrs_test.php`, `tests/designer_grid_nodes_test.php` (both run the cut-out emitter under Node), `tests/designer_code_view_attrs_test.php`.

## In what order is the style written?
- One `style` attribute: `fontFamily`, the old `inlineStyle` field, the type's own style (icon font-size, image object-position, spacer height, an SVG's own), the Display & Sizing props, then the Attributes panel's `style`; the last wins on a repeat (the canvas uses the same order). The editor once wrote a second style attribute.
- The font is quoted only when it holds a space or starts with a digit, then `,sans-serif` (`_sdFontFamilyCss()`); a bare name (`serif`) stays a generic family.

## How are the title and the tooltip written?
- After the id: `title` (the tooltip's text while it is on, the `title` prop otherwise), `data-bs-toggle="tooltip"`, `data-bs-placement` (default `top`); the page wrote none before. The front end binds no Bootstrap tooltip, so the page shows the browser's title; the preview binds Bootstrap's.
- An element with a `data-bs-toggle` in the Attributes panel (modal, popover, collapse) gets no tooltip: its toggle and title would replace the other one. `title` and `data-bs-placement` yield to a panel entry of the same name.

## Which Attributes panel entries are written?
- All but `class`, `style` and `id` (any case) and the ones the props wrote: a link's `href` (and `target`/`rel` when set), an image's `src`, `alt`, `width`, `height`, `onerror`, a semantic `<a>`'s href/target/rel or `<img>`'s src/alt. `style` merges into the style attribute; a `class` entry is written by neither side (classes are `cssClass`); `id` is `props.id`.

## What do content nodes get from Display & Sizing?
- The classes join `cssClass` once, the style goes in the style attribute, as on the canvas. An image's `width`/`height` and a spacer's `height` are the element's own props, not sizing (`_pg_content_sizing()` / `_sdContentSizingNode()`). In a ratio box (a preset) the box takes an image's whole sizing style, its width and height in it (a bare number in px), and its rounded classes; the `<img>` keeps its id, attributes and own style.
- A divider writes `my-<margin>`, then `cssClass`, each class once. The Divider component sets `margin`, no class; its old `cssClass: 'my-4'` gave `<hr class="my-4 my-4">`.
- Only the types whose page markup carries attributes get them: heading, paragraph, span, text, link, icon, image, divider, spacer, svg. Custom HTML and a PHP block print as written; carousel, messages, breadcrumb, variant picker and applied offers are the server's.

## What is the page's alone?
- `data-pg-search-section` after the id (the Search section switch; its cue to add the sections' structured data). Not in the editor's HTML (typing it there would change nothing), nor a copy typed in the panel. An image's `onerror`, `srcset`, `sizes`, `<picture>`.

## When does an existing site see it?
- Widgets and shared components at once; a page body on its next save or "Save and rebuild page HTML" (designer/page-html-rebuild.md).

Related: designer/designer-grid-node-tag.md, designer/designer-code-view-editing.md
