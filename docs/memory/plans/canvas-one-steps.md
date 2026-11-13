---
name: canvas-one-steps
description: Steps CO0-CO9 of plans/canvas-one.md: model and screens drawn, the bridge and its proofs, bound tables read, writes with consent, channel tables, measures, Data, one API flow, the phone, close-out
---

## Which steps are there?
- Owner (2026-11-12, #8850): DL1 and DL2 of the design language (`panel-design-language-steps.md`) and the bridge first; tables as Data and the phone on top. S under 400 product lines, M about 1,000; each with its tests and its topic file `workspace/canvas-<part>.md`.

- CO0 (S, no product code) The model and the screens: what one canvas is in the store and the API, its screens drawn in look A (`plans/canvas-one/`) and approved by the owner, the schema changes the later steps need. Done in #8922.
  - Its files: `canvas-one-model.md`, `canvas-one-schema.md` (S1-S5, #8923-#8927), `canvas-one-screens.md`.

- CO1 (M) The bridge, with no data: the shell's message door for a page (`platform/guarded-frame.md`), proven in a browser. Done in #8947 (`workspace/canvas-bridge.md`).

- CO2 (M) Bound tables, read in the reader's rights; the card's line of tables. Done in #8960 (`workspace/canvas-tables.md`).

- CO3 (M) Writes with consent: the first-write question, asked again for a new version; "through <canvas>" on a row. Done in #9180 (`workspace/canvas-consents.md`).

- CO4 (M) Tables of a channel: a table that belongs to a channel, read and written by the channel's rights. Done in #9038 (`workspace/workflow-table-channel.md`: a table of the tables cluster, not a canvas part).

- CO5 (S) Measures: a page's read on a table of 5000 rows; the bounds set from it. Done in #9207 (`workspace/canvas-measures.md`): no limit moved, nothing asked.

- CO6 (M) Data as the name and place. Workflow tables are called Data in the menu and on the screens; a canvas shows its tables under Data; addresses, API names and MCP tools keep their names. Done in #8978 (`workspace/workflow-tables-data-list.md`).

- CO6b (S) The canvases that use a table: a column of the Data list and a line under an opened table's title, read from `ws_canvas_tables`. Done in #9192 (`workspace/workflow-tables-data-list.md`).

- CO7 (M) One flow over the API and MCP. Make a canvas with a page, bind a table, read and write, list versions: the routes and the tools. After CO3. Done in #9231 (`api/workspace-canvas-api.md`), with the five tools of `canvas-one-model.md`.

- CO7b (S) The second part in the store and the API. S1's `ws_canvas_parts` by a 2026.11.0 migration step; `PUT …/drawing` and `…/page` add a part beside the first (409 `part_missing` ends); the versions list tells each version's part. No screen: until CO7c the card of a canvas with two parts shows the first. Done in #9258 (`workspace/canvas-parts.md`).

- CO7c (M) The joined card (`canvas-card` in `canvas-one-screens.md`): the part chips, the segmented control between Drawing and Page, the one Versions drawer. After CO7b. Done in #9274 (`workspace/canvas-card.md`).

- CO7d (S) An agent's binding as a channel line with Undo. Done in #9323.

- CO8 (M) The phone: the card, the first-write sheet and the Data list at 390 px. Done in #9361 (`workspace/canvas-phone.md`).

- CO9 (S) Close-out: `workspace/canvas-overview.md`, the lasting rules in the topic files, the PR body names the `[NEW]` changelog line, both plan files and the folder deleted.

## What holds for every step?
- What is left of the canvas-design and live-page plans goes on under those plans; a CO step never rewrites what they build, it joins it.
- No schema change without the owner's answer on its issue; CO0 lists them.
- A step that shows a screen is approved from a real panel's screenshots.

## Open questions
- None now: the schema ones of CO0, S1-S5 (#8923-#8927), are answered (`canvas-one-schema.md`).
- Architect (2026-11-13): the cut of CO7b to CO7d, and CO8 not waiting for DL5f.
