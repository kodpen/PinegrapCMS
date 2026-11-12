---
name: canvas-one-steps
description: Steps CO0-CO9 of plans/canvas-one.md: model and screens drawn, the bridge and its proofs, bound tables read, writes with consent, channel tables, measures, Data, one API flow, the phone, close-out
---

## Which steps are there?
- Owner (2026-11-12, #8850): DL1 and DL2 of the design language (`panel-design-language-steps.md`) and the bridge first; tables as Data and the phone on top. S under 400 product lines, M about 1,000; each with its tests and its topic file `workspace/canvas-<part>.md`.

- CO0 (S, no product code) The model and the screens. What one canvas is in the store and the API (names, parts, versions), and its screens drawn in look A, on a phone too: the card with its parts, the line of tables it uses, the first-write question, the Data list; in `plans/canvas-one/`. The owner approves the drawings. It names every schema change the later steps need, each asked on its own issue. Done in #8922.
  - Its files: `canvas-one-model.md`, `canvas-one-schema.md` (S1-S5, #8923-#8927), `canvas-one-screens.md`.

- CO1 (M) The bridge, with no data. The shell's message door for a page (`platform/guarded-frame.md`): a request with an id, an answer, an error, the bounds; one harmless call (the display name of who is looking). Proven in a browser as the guarded frame was (#8802): the page reaches nothing but through the door, and a hostile page cannot ask in another canvas's name. Free now: the guarded frame (#8802) and the page routes (#8815) have landed.

- CO2 (M) Bound tables, read. A canvas names its tables; the page reads rows with the `where`, sort and totals of `workspace/workflow-tables-query.md`, in the reader's rights; the card's line of tables. After CO0 and CO1; its schema asked first.

- CO3 (M) Writes with consent. Add and change a row through the table's own functions; the first-write question, asked again for a new version; "through <canvas>" in the row's history. After CO2.

- CO4 (M) Tables of a channel. A table that belongs to a channel or a canvas, not to one person: the members read and write by the channel's rights; what becomes of it when the canvas is deleted. After CO0; its schema asked first.

- CO5 (S) Measures. Rows and time of a page's read on a table of 5000 rows with formulas and lookups; the bounds set from it; a question to the owner only if a limit must move. After CO2.

- CO6 (M) Data as the name and place. Workflow tables are called Data in the menu and on the screens; a canvas shows its tables under Data; addresses, API names and MCP tools keep their names. After DL2.

- CO7 (M) One flow over the API and MCP. Make a canvas with a page, bind a table, read and write, list versions: the routes and the tools, described in `api/`; the assistants' tools follow (`workspace/agent-canvas-assistants.md`). After CO3.

- CO8 (M) The phone. The table screen and the canvas's screens on the recipes of look A (the design language's Workspace family, DL5e): rows as cards, the editor from the bottom. After DL1 and CO6.

- CO9 (S) Close-out: `workspace/canvas-overview.md`, the lasting rules in the topic files, the PR body names the `[NEW]` changelog line, both plan files and the folder deleted.

## What holds for every step?
- What is left of the canvas-design and live-page plans goes on under those plans; a CO step never rewrites what they build, it joins it.
- No schema change without the owner's answer on its issue; CO0 lists them.
- A step that shows a screen is approved from a real panel's screenshots.

## Open questions
- None now: the schema ones of CO0, S1-S5 (#8923-#8927), are answered (`canvas-one-schema.md`).
