---
name: image-editor-overview
description: Pinegrap's own image editor as a whole: what it is, the owner decisions that bind it, who does what (browser or server), the document in rotated space, and which topic file holds each part
module: image_editor
---

## What is it?
- One editor for the whole software, written here with no outside engine: photo work, marking, redaction, vectors and SVG, a blank canvas with artboard sizing, animation, and optimisation on save.
- A core part: a `<dialog>` with a full-screen button, opened from any screen by one call (`pgImageEditor.open()`, alias `window.openImageEditor()`); a caller may limit the tools to a floating mini bar (`ui/image-editor-modal.md`, `image-editor-callers.md`).
- Texts and shapes stay editable: a save keeps its document in `file_versions`, so a later open edits layers, not pixels. That makes it a canvas too: File Manager "New image", Workspace canvases.
- It replaced the bundled third-party editor (`assets/lib/image_editor/`, 2.3 MB), removed with its loader and `image_editor_edit.php` (now a redirect) in #4609.

## Which decisions bind it?
- Owner, 2026-10-31 (`workflow/decisions-made-product-14.md`, `-15.md`, `decisions-made-schema-7.md`): own code, no engine; core modal with full screen; callable with a tool subset; labels through `lang()`; work on the server where it is efficient, else in JS; a versions table (the original plus the last 10 saves); optimisation inside; ready-made shapes and corner rounding; artboard sizing; "New image" opens the canvas; a redacted save keeps no metadata; the live site's edit button for signed-in editors only.
- Owner, 2026-11-01 (`decisions-made-product-16.md`): layers animate and save as GIF, APNG, WebP, MP4 or WebM (`image-editor-animation.md`).
- Owner, 2026-11-03 and -04 (`decisions-made-product-24.md`, `-27.md`): the new tools (sticker change, outlines, scissors, shape builder, warp, free deformation, area selection, select subject with an own algorithm and no model); the Fotoğraf and Vektör rail groups (`image-editor-rail.md`); the picture as the bottom layer; Import; a context menu; video editing inside the editor (`plans/image-editor-video.md`).

## Who does what, the browser or the server?
- The server does what it does well; what would be slow there is written in JS. The browser does all interaction and preview and draws the overlay (shapes, text, stickers, frames, inserted pictures) at output size into a transparent PNG, and the colour step on a host without Imagick.
- The server works on the original's pixels: the browser sends the recipe and the overlay, never the result, so a 48 MP photo is edited on a preview and rendered at full size (`platform/image-editor-save.md`). Preview and engine read the same colour matrix and gamma, held by a test (`image-editor-adjust.md`).
- The editor never has the server rasterise SVG (GD cannot; Imagick's SVG readers are often missing): an SVG is kept as sanitized markup (`image-editor-svg-sanitize.md`, `image-editor-svg.md`). An agent's canvas is the exception (`platform/image-server-draw.md`).

## What is the document?
- JSON: source (file id, natural size), geometry (quarter turns, flips, straighten, crop box and shape, output size), adjustments, filter, frame, artboard, layers, motion and scenes.
- Layers live in rotated space (after quarter turns and flips, before straighten and crop): a text stays upright and a crop never moves a layer; the picture alone takes the full transform. One path builder gives the `d` string that both the SVG view and the export draw; text has one line layout for both.
- The document refuses inline pixel data and a size over 1 MB; an inserted picture is its own `files` row referenced by id (`image-editor-versions.md`).

## Where is each part?
- Files of a tool and the hooks it may use: `image-editor-parts.md`; the core, loader and labels: `image-editor-modal.md`; keys: `image-editor-keys.md`, `image-editor-access.md`, touch: `image-editor-touch.md`; the visual designer's button: `designer/image-editor-tool.md`.
