---
name: agent-canvas-design-steps
description: Steps CV1-CV8 of plans/agent-canvas-design.md: an SVG taken apart, text and picture layers, layer writes, sizes and scenes, templates, the server's drawing, saving into Files, close-out
---

## Which steps are there?
- S under 400 product lines, M about 1,000; each with its tests and its topic in `workspace/`. `free` once the steps it names have landed.

- CV1 (M) One SVG taken apart. Done in #8799: `workspace/agent-canvas-layers.md`.
- CV2 (M) Text and pictures as their own layers. Done in #8823: `workspace/agent-canvas-split-parts.md`.
- CV3 (M) Layer writes. Done in #8834: `workspace/agent-canvas-layer-writes.md`.
- CV4 (S) Sizes and scenes. `free` once #8834 lands.
  - `size` by name from the New image dialog's presets (`M.BOARD_PRESETS`), `background`. Scenes: add, choose, remove, a transition (`ui/image-editor-scenes.md`); at most 30.
- CV5 (M) Templates. Done in #8959: `workspace/agent-canvas-templates.md`.
- CV6 (M) The server draws where it can. After CV1. Owner: optional, never required.
  - A probe first (`pg_image_draw_can()`): can the host's Imagick read an SVG, through which delegate, and draw text from a font file; shown on System status. The host draws a fixture set (shapes, a gradient, a picture, text in a site font) and the step reports how it compares with the editor's renderer.
  - Where it can, a pending canvas gets its picture from the server at once. A document it cannot draw faithfully (text in a font it has no file for, an effect outside the probe) waits for a browser as today; a browser's picture replaces the server's.
  - Guards: only markup that `pg_svg_sanitize()` passes; pictures put in from the files the document names, nothing fetched; limits on size and time, Imagick's resource limits set; no shell.
  - `ui/image-editor-overview.md` corrected: "the server never rasterises SVG" holds only where the host cannot.
- CV7 (M) Saving into Files. Done in #8948: `workspace/agent-canvas-save.md`.
- CV8 (S) Close-out. An overview if the `agent-canvas-*` cluster has ten files, the lasting rules into the topic files, the PR body names the `[NEW]` changelog line, both plan files deleted.

## Which questions are open?
- None. A step that needs a column asks first.
