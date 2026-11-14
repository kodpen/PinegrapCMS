---
name: agent-drawing-guides
description: Plan (owner 2026-11-14): what the canvas side gives an agent's drawing: a guide it reads, charts as an operation, ready layouts, a chart preset for live pages
---

## What is asked?
- Owner (2026-11-14, agent channel, on the pictures of step CO8 of `canvas-one`): "panoda chartlar aşırı basit olmamış mı? ajanın çizimlerinde yardımcı olacak guide leri yazılım tarafı sağlayabilirse sağlayalım. arayüz tasarımı olarak biraz daha iyileşme şart". Record: `workflow/decisions-made-workspace-24.md`.

## What is there today?
- An agent draws a canvas from SVG it writes by hand, edits layers one by one, sizes boards and scenes, and fills a template a person marked (`workspace/agent-canvas-overview.md`, `agent-canvas-templates.md`). Nothing tells it a grid, a type scale, a palette or how a chart is built: each drawing is as good as the model's guess.
- A live page an agent writes may name Chart.js from the site's own copy (`workspace/agent-page-libraries.md`), with the library's default look.

## Which steps?
- DG1 (S) Done (#9400). Census and proposal, no code: three of today's agent charts as pictures; a drawn guide sheet (artboard presets, grid, type scale, palette with a chart series readable in dark and light, spacing) and the chart kinds proposed, each drawn. Posted in the agent channel for the owner's look; written as `workspace/agent-drawing-guides-census.md`.
- DG2 (M) Done (#9457). The guide an agent reads: one text from one source, given as an MCP resource and prompt, shortly in the canvas tools' descriptions and in the assistants' draw tool (`agent-canvas-assistants.md`). Tests pin the text to the values the server uses.
- DG3 (M) A chart as an operation: `chart` in the one set of canvas operations (API route, MCP tool, `edit_canvas`): kind (bar, stacked bar, line, area, donut, sparkline, a figure tile), data, labels, size. The server writes the layers in the guide's style; they stay layers a person edits in the Design Canvas. A model test in Node per kind.
- DG4 (M) Ready layouts: templates shipped with the software (a row of figures with a chart, a report page, a comparison, a slide), listed beside a person's templates and filled by the agent.
- DG5 (S) Live pages: an agent's Chart.js charts take the guide's palette and type by a ready preset the page names.
- DG6 (S) Close-out: the rules into `workspace/agent-canvas-*.md`, this plan deleted.

## What does every step keep?
- No second tool beside the canvas (owner, 2026-11-11, `workflow/decisions-made-workspace-16.md`); a new operation goes into the route, the MCP tool and `edit_canvas` alike; the limits stay (20 operations a call, a document of 1 MB): a chart is one operation.
- No table or column; a step that needs one stops and asks the architect. A layer the server writes is one the editor's model accepts.
- Every step from DG2 on posts real pictures (dark and light) in the agent channel before its PR goes ready.
- From DG2 on, each step also draws a real canvas in agent channel 25 through the canvas API route or MCP tool with the channel's agent key, as an agent would, beside its pictures, so the owner sees the trial in the channel (owner, 2026-11-14, message 2660).
- Owner (2026-11-14, message 2660): DG1's drawings approved as they are; drawing them through the API is DG3.
- DG1 first; DG2 after the owner's look at it; DG3, DG4 and DG5 after DG2, side by side. Claim as `[plan:agent-drawing-guides] <step> — <topic>`.
