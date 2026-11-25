---
name: designer-preview-overview
description: Start file for the designer-preview-* files: what the designer's Preview is, which file holds the menu, links, page scripts and each part of "Preview as" (cases, server, renderer readers, drawings)
---

## What is the Preview?
- A window beside the editor that draws the open page as the site would, from the trees the editor holds now, saved or not; its system widgets are drawn by the server in a background render that writes nothing (`designer-live-preview.md`).

## Which file holds which part?
- The Preview menu beside Publish and its rows: `designer-preview-menu.md`.
- Links in the Preview (a page link plays its tab, Hotspots): `designer-preview-play.md`.
- Platform scripts the Preview adds as the page would get them: `designer-preview-page-scripts.md`.
- "Preview as" (plan `plans/designer-preview-as.md`): the case model `designer-preview-as-cases.md`; the server, record picks and the scope `designer-preview-as-server.md`; what the renderers read of a case `designer-preview-as-readers.md`.
- Its drawing: `designer-preview-as-drawing.md` (the sheet), `designer-preview-as-drawing-bar.md`, `designer-preview-as-drawing-choosers.md`, `designer-preview-as-drawing-check.md`.

## What is still open?
- The visitor of "Preview as" (signed in, member, returning) is not read by the renderers yet (plan step PW2c), then the bar (PW3) and "Check all cases" (PW4).
