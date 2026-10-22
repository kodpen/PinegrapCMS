---
name: designer-node-attributes
description: The attributes every designer content, semantic and grid node writes on the page and in the editor HTML: one style order, font quoting, title and tooltip, Attributes panel rules, sizing on content
---

## Which code writes them?
- The page: `_compose_inline_style($props, $extra, $sizing)` and `_render_extra_attrs($props, $skip, true)` (with `_pg_node_title_attrs()`) in `includes/fn/designer.php`, called by `_render_content_html()`, the `semantic` case of `_render_tree_node()` and `_pg_grid_open_tag()`. The editor's HTML (code view, preview, revision compare): their twins `_sdNodeStyle()`, `_sdNodeExtraAttrs()`, `_sdNodeTitleAttrs()` in `style_designer.js`, called by `addGlobalAttrs()`, the semantic case of `_sdEmitNode()` and `_sdGridOpenTag()`. The saved page is the PHP side; the JS side follows it. Components keep their own markup (a component's `title` is its heading text, not an attribute).
- Tests: `tests/designer_node_attrs_test.php`, `tests/designer_grid_nodes_test.php` (both run the cut-out emitter under Node), `tests/designer_code_view_attrs_test.php`.

## In what order is the style written?
- One `style` attribute: `fontFamily`, the old `inlineStyle` field, the type's own style (icon font-size, image object-position, spacer height, an SVG's own), the Display & Sizing props, then the Attributes panel's `style`; the last wins on a repeat (the canvas uses the same order). Before, the editor wrote `inlineStyle` first and gave an icon, image or spacer a second style attribute; a semantic element's font and inline style were missing from the page.
- The font is quoted only when it holds a space or starts with a digit, then `,sans-serif` (`_sdFontFamilyCss()`); a bare name (`serif`) stays a generic family.

## How are the title and the tooltip written?
- After the id: `title` (the tooltip's text while the tooltip is on, the `title` prop otherwise), `data-bs-toggle="tooltip"`, `data-bs-placement` (default `top`). The page wrote none of them before, on any node. The front end binds no Bootstrap tooltip, so the page shows the browser's title tooltip; the preview binds Bootstrap's.
- An element with a `data-bs-toggle` in the Attributes panel (modal, popover, collapse) gets no tooltip: its toggle and title would replace the other one. `title` and `data-bs-placement` yield to a panel entry of the same name.

## Which Attributes panel entries are written?
- All but `class`, `style` and `id` (any case; class and style merge, id is `props.id`) and the ones the props wrote: a link's `href` (and `target`/`rel` when set), an image's `src`, `alt`, `width`, `height`, `onerror`, a semantic `<a>`'s href/target/rel or `<img>`'s src/alt. The editor wrote a second class, style, id or href before.

## What do content nodes get from Display & Sizing?
- The classes join `cssClass` once, the style goes in the style attribute, as on the canvas; the page wrote only what cssClass held. An image's `width`/`height` and a spacer's `height` are the element's own props, not sizing, and an image in a ratio box hands `overflow*` to the box (`_pg_content_sizing()` / `_sdContentSizingNode()`).
- Only the types whose page markup carries attributes get them: heading, paragraph, span, text, link, icon, image, divider, spacer, svg. Custom HTML and a PHP block print as written; carousel, messages, breadcrumb, variant picker and applied offers are the server's. The editor used to write the node's attributes into their first tag.

## What is the page's alone?
- `data-pg-search-section` after the id (the Search section switch; its cue to add the sections' structured data). Not in the editor's HTML: typing it in the code view would change nothing. A typed copy in the panel is not written twice. An image's `onerror`, `srcset`, `sizes`, `<picture>`.

## When does an existing site see it?
- System widgets and shared components at once; a page body on its next save or with "Save and rebuild page HTML" (designer/page-html-rebuild.md).

Related: designer/designer-grid-node-tag.md, designer/designer-code-view-editing.md
