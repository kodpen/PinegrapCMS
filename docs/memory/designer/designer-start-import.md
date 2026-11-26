---
name: designer-start-import
description: The Figma and Canva starts of the New design window (2026-11): what the owner asked, where each part is written, and what was left unbuilt
---

## What was asked?
- Owner (2026-11-24): "görsel tasarımcıyı 2-3 gömlek ileri taşıyalım. figmadan içe aktar, canva dan içe aktar. seçenekleri ile daha hızlı başlangıç seçenekleri": one New design window with every start, import from Figma as layers, from Canva as pictures.
- Owner (2026-11-24): Canva gives out no editable layers, so Canva pages come as reference pictures and their colours as the brand (`workflow/decisions-made-product-105.md`).
- Owner (2026-11-24): Figma with a pasted personal access token, Canva with no connection at all (`workflow/decisions-made-product-107.md`).

## Where is each part written?
- The window and how a start is added: `new-design-window.md`. Figma and Canva's APIs as read on 2026-11-24: `api/figma-canva-census.md`.
- Figma: the token on Connections `ui/connections-figma-page.md`; the link and frame list `figma-start-frame-list.md`; the converter `figma-import-converter.md`; pictures, shared components, variables and the report `figma-start-import.md`.
- Canva: `canva-start.md`. The drawing of both: `designer-start-import-drawing.md`.

## What holds for every start?
- No table or column: a start keeps its work in the session, `data/temp/` or `localStorage`, and its files in the file manager.
- Every fetch is the server's, under an upload's size, count and type limits; the Figma token is sealed like other connected services' and never reaches the browser.
- A start's form comes first, then the start questions (`design-variables-questions.md`), then the design opens.

## What was left unbuilt?
- Figma (drawing boards B8, B11, B12): the import going on with the window closed, the line on the design's card of the designs home, and waiting by itself when Figma's limit is met half way. The import runs only while the window is open.
- Figma: "Ask me the questions" on the variables card (the questions with the file's answers chosen).
- Canva: a PDF is refused (rasterising it needs Ghostscript, which runs the PDF's own program and a host may lack), so the drawing's PDF unfolding is not built; fonts are never taken.
- The window's "Sketch it" and "Describe it" (Pinegrap AI) rows: not listed; no plan builds them yet. Each is one entry in `pg_designer_start_kinds()` (`new-design-window.md`).
