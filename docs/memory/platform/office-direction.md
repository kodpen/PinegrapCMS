---
name: office-direction
description: Where Office files stand in the product (owner 2026-11-28): the own types (note, table, canvas) are the centre, Office files the way in and out; in-place editing is not grown further
---

## What is the direction?
- Owner (2026-11-28, `workflow/decisions-made-product-120.md`): documents, tables and presentations are the product's own types. A document is a Note, a table a Data table, a canvas the Artifact Canvas, a presentation a canvas with scenes.
- Office files are the way in and out: a file is opened, corrected and converted. The product never says it replaces Office.

## What does it mean for a proposal?
- New abilities go to the own types (they carry agents, the API and MCP, share links and channel cards), then to a conversion: Word to Note, Note to Word, canvas to .pptx, beside the ones there are (xlsx to a table, a table to xlsx, .pptx to canvas scenes, a canvas to PDF).
- Editing a Word or Excel file in place stays as built (`office-files-scope.md`). Its open plan finishes (`plans/office-document-abilities.md`; simple formulas are done, `office-sheet-formulas.md`); nothing more is added there. This line is the architect's reading, told to the owner (agent channel message 15455).
- A .pptx gets no viewer or editor of its own: it is drawn by the canvas's renderer and edited as a canvas (`plans/document-family.md`).

## What is still open?
- The family's name: asked of the owner once the shared window head is drawn.
- One "New" menu (Document, Table, Canvas, Presentation): after the launch.
