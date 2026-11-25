---
name: designer-start-import
description: Plan (owner 2026-11-24, before the launch): the New design window with every start in one place, import from Figma as layers and from Canva as reference pictures and brand; steps ST1 to ST7
priority: 7
---

## What is asked?
- Owner (2026-11-24): "görsel tasarımcıyı 2-3 gömlek ileri taşıyalım. figmadan içe aktar, canva dan içe aktar. seçenekleri ile daha hızlı başlangıç seçenekleri".
- His choice for Canva: pictures and brand, since Canva gives out no editable layers (`workflow/decisions-made-product-105.md`).

## What is there today?
- The home's four start cards: blank, ZIP, pasted markup, template (`designer/designer-home-screen.md`); one converter behind the imports (`designer/html-import-and-paste.md`).

## What is built?
- "New design" opens one window in place of the four cards' separate ways. Left a list of starts, right the chosen start's form: Blank, Template, Sketch it (`designer-sketch.md`), Figma, Canva, ZIP file, Pasted markup, and with Pinegrap AI on "Describe it". Each start is one line of words and a small drawing. After the start's own form come the start questions (`designer-variables.md`, DV4), then the design opens.
- Figma: a personal access token is pasted on Connections (`ui/connections-figma-page.md`); in the window a file's link is pasted and its frames listed with pictures, ticked ones become pages. Auto layout becomes flex and grid nodes, text stays text with its style, pictures and vectors are fetched into the file manager, a component used on several frames becomes a shared component, colours and text styles fill the variables. A report lists what could not be carried, per frame.
- Canva: no connection (`workflow/decisions-made-product-107.md`); the pages exported from Canva as PNG or PDF are uploaded in the window and come as reference pictures under Sketch frames (SK7); the colours measured from them are offered for the variables, fonts are not taken. The window says in one sentence that the pictures are a model to build on, not layers.
- Every fetch is the server's, with the size, count and type limits of an upload; the Figma token is sealed as other connected services' are and is never sent to the browser.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- ST1 (S) Census, no product code: what Figma's and Canva's APIs give today (sign-in kinds, file and node reads, picture export, styles and variables and on which plans, rate limits, terms), written to `api/`; a difference from this plan goes to the architect before ST3. Done in #11491: `api/figma-canva-census.md`.
- ST2 (M) The New design window with the starts that exist; the home's cards open it. Done (#11499, `designer/new-design-window.md`).
- ST3 (M) after ST1: Figma on Connections, the file read, the frame list. Done in #11558: `ui/connections-figma-page.md`, `designer/figma-start-frame-list.md`; the start is listed once ST5 defines `pg_figma_import_start()`.
- ST4 (M) after ST1: the Figma converter, pure: a frame's nodes to a tree; tests on stored answers. Done in #11509: `designer/figma-import-converter.md`.
- ST5 (M) after ST3, ST4: pictures, shared components, variables, the report.
- ST6 (M) after ST1 and SK7: the Canva upload (no connection), reference pictures, the colours. In progress.
- ST7 (S) Close-out: memory, this file deleted, the `[NEW]` line named.

## What does every step keep?
- No table or column; a step that needs one stops and asks the architect.
- Claim as `[plan:designer-start-import] <step> — <topic>`.
