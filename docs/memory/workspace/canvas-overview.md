---
name: canvas-overview
description: The Workspace canvas as one thing (canvas-one, #8850 to #9361): a drawing, a live page and data on one card; the decisions that bind it, how a page reaches tables, which file holds each part
---

## What is it?
- Owner (2026-11-12, #8850): "tuval claude uygulamasındaki yapıt özellikleri (uzaktan api ilede kullanma ile), workspace tablolar da data bölümüne dönüşüyor. arayüzleri sadeleşiyor, mobil kullanırlık için iyileşiyorlar." A canvas does what an artifact of the Claude app does, over the API too; workflow tables are its Data.
- One thing in a channel: a message whose id is the canvas's id, with up to three parts: a drawing (the Design Canvas's document), a live page (an agent's HTML in the guarded frame) and data (tables bound to it). One card, one name, one list of versions; a part that is not there is not shown. Nothing made before was converted.
- Not the Page Design Studio, which designs the site's pages.

## Which decisions bind it?
- `workflow/decisions-made-workspace-18.md` (#8850): the plan and its order. `decisions-made-schema-17.md`: the five tables and columns (S1-S5, #8923-#8927). `decisions-made-workspace-20.md` (#8929): the drawings as the reference. `decisions-made-workspace-21.md`: the corrections to CO2's card.
- Architect (2026-11-13, for the owner, channel message 2334): the phone screens of #9361 accepted, with the gaps named for DL5f (`canvas-phone.md`).

## How does a page reach data?
- Only through the frame's shell, never by itself: no connection, no storage, no way to the panel (`platform/guarded-frame.md`, `canvas-bridge.md`). The card names the canvas; a page never does.
- Only the tables its canvas binds, each read or read and write; any other id answers `not_bound` (`canvas-tables.md`).
- With the rights of the person looking: what the reader may not read is not given, a write they may not make is refused; a guest gets nothing.
- The first write in a reader's name is asked once per page version (`canvas-consents.md`); every write is the table's own (`ws_wtable_row_create()`, `ws_wtable_row_update()`) and marks the row "through <canvas>".
- Bounds on requests, rows and answer size stop a page that passes them (`canvas-measures.md`: 5000 rows measured, no limit moved).
- Who binds: whoever may write in the channel and write the table. An agent's binding is a channel line with Undo (`canvas-binding-line.md`).

## Which file holds each part?
- Bindings: `canvas-tables.md` (`ws_canvas_tables`). The second part: `canvas-parts.md` (`ws_canvas_parts`). The joined card and its Versions drawer: `canvas-card.md`. The phone, the icon tabs (`.pg-seg-icons`) and the floating New table: `canvas-phone.md`.
- The API and MCP: `api/workspace-canvas-api.md` (`/workspace/canvases/{id}`, its tables, page and drawing). Channel tables: `workflow-table-channel.md`. Data as the name and its list: `workflow-tables-data-list.md`.
- The parts before they joined: the drawing (`agent-canvas-overview.md`), the page (`agent-page.md`, `agent-page-card.md`).

## How is a new part or call added?
- A new kind of part: a `ws_canvas_parts` kind, read through `ws_canvas_part_file()` / `ws_canvas_part_message()` so its existing readers take it unchanged; the card's chips, switch and Versions list take it from `file.joined`.
- A new bridge call: in the shell's message shape (`pg: 'canvas-data'`), answered by the panel for the canvas the card named, with the reader's rights; a write goes through the table's own write and the first-write question.

## What is not in it?
- Each its own question when wanted: a share link outside the channel, a page that asks an AI in the reader's name, a file a page hands the reader, live pushes.
- For DL5f (`canvas-phone.md`): the compact Data card and its groups, a bulk Delete or no selection, the empty Canvases line on a phone card, the 52 px floating button.
