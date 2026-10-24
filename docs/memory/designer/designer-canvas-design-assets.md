---
name: designer-canvas-design-assets
description: How a design's scripts and stylesheets reach the designer canvas: scripts once per canvas document, realm reset, links synced by difference, file stamps, frame scheduling, motion pause, state classes.
---

## How often does the design's JavaScript run on the canvas?
- Once per canvas document. `applyAssetsToIframe()` compares the signature of the scripts the page on the canvas loads (`_sdCanvasJsSig()`: type, name, content or stamped URL, module flag) with the one stored on the document. When it changed, `_sdCanvasRealmReset()` replaces the iframe element with a fresh one; `document.open()` would keep the window, its timers and its globals.
- Why: a script cannot be taken back once it has run (its timers, listeners and observers stay), so it never runs again on top of its previous copy. The editor's state carries over the reset: selection, zoom, width, dark canvas, grid and outline, scroll position, an open modal, offcanvas or dropdown menu (designer-canvas-open-state.md).
- A new path that puts script on the canvas goes through this gate. Inline `<script>` inside a `custom_html` node is the exception: `renderCanvas()` rebuilds the body and `_reExecuteCanvasScripts()` runs it again on every render, so such code must be idempotent.

## How are stylesheets kept in step?
- `<link>` families (Google Fonts `pg-gf-`, external CSS `pg-ext-css-`) are synced by difference (`_sdSyncCanvasLinks()`): links that already match are kept. Removing a stylesheet and adding it back restarts every animation it drives and lays the page out without it in between.
- The inline `<style>` elements (`pg-page-css`; `pg-user-styles`, kept the last stylesheet in `<head>` so handwritten rules win) are rewritten only when their text changed: a new text is a new stylesheet the whole canvas restyles for.
- A managed designer file keeps its URL when its body is saved, so the canvas asks for it with a stamp (`?_t=`: the editor's load time, or the last save from this editor via `_sdBustAsset(url)`). CDN files are never stamped (HTTP caching).
- The editor's own Bootstrap Icons sheet is disabled while the design loads one: the same classes twice is two thousand rules restyled for nothing.

## On which window does editor work run?
- Canvas work is scheduled with the designer window's `requestAnimationFrame`, never the canvas window's: `_sdMotionShimFn()` runs in the canvas before the design's scripts and holds the frames the page asks for during a drag, and a replaced canvas document takes its frames with it.
- The canvas stops the design's motion unless the operator plays it (Animations button, `pg_sd_motion` in `localStorage`): CSS motion through `body.sd-motion-paused`, scripts are told `prefers-reduced-motion: reduce`. Toggling resets the realm because scripts read the setting when they start. The preview window and the site always play.

## Which canvas body classes belong to the editor?
- `renderCanvas()` rewrites `<body>` from the root node (class, style, id, attributes) and keeps `_EDITOR_STATE_CLASSES` (`sd-grid-overlay`, `sd-outline-grid`, `sd-motion-paused`, `sd-motion-hold`). A new editor state class on the canvas body goes into that list, or the next render wipes it.
- The editor's body defaults sit in `@layer sd-canvas-base` (first rule of `IFRAME_CSS`): as plain rules they beat a framework that layers its own body rules (Bootstrap 6) and the canvas lost the design's font.

Related: docs/memory/designer/designer-canvas-redraw-paths.md, docs/memory/designer/designer-canvas-style-designer.md
