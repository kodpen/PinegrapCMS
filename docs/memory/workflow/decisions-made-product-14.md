---
name: decisions-made-product-14
description: Decisions list, product part 14 (2026-10-31 on): own image editor, nothing alone under a screen's title
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## Which image editor does the software use? (owner, 2026-10-31)
- Decided (owner, 2026-10-31; `plans/image-editor.md`): its own, written in this repository with no outside engine, open-source ones included: an engine's model and limits would bind every later feature. It replaces the bundled third-party editor, which goes with its loader.
- A core part: an in-page modal with a full-screen button, callable from any screen with a subset of tools (arrows only for a marking task); the full-page editor retires. Every text through `lang()`.
- The server does the work it does efficiently (the original's pixels, geometry, redaction, encoding, optimisation, SVG cleaning); what would be slow there runs in JS. No video. Optimisation (format, quality, size, metadata) is part of the editor.
- Texts and shapes stay editable after a save (a versions table keeps each document), so the editor is also a canvas: File Manager "New image" and Workspace canvases. Ready-made shapes, corner rounding and Illustrator-like artboard sizing belong to it.

## What may sit under a screen's title? (owner, 2026-10-30)
- Decided (owner, 2026-10-30, with a screenshot of Calendar subscription's lone "Work Calendar" button): no button, link or short text alone under the title. Everything a screen does is in one toolbar there (`ui/admin-screen-toolbar.md`); the way back is only the back arrow and the breadcrumb (`ui/back-arrow-no-copies.md`). Done #3912-#4100. The bar does not stick on phones (#3837, part 12).
