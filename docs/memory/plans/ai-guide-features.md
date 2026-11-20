---
name: ai-guide-features
description: Plan ai-guide, features G1 to G10: the guide's registry, its text built from code, sizes, version and digest, the topics (start, designer, the design's values, panel) and who reads it where
---

## What is the guide?
- (G1) A registry of topics in `includes/fn/agent_guide.php`: id (`[a-z_]`), title, when to read it, the module it needs, sections (id, heading, lines). Topics: `start`, `designer`, `content`, `forms`, `store`, `workspace`, `erp`, `panel`. Each topic's builder is its own file (`includes/fn/agent_guide_<topic>.php`); a topic without its file is not listed.
- (G2) Built from code, never typed twice: a tool, operation, kind, class family, scope or screen the text names is read from the registry that owns it. A test fails on a tool name that is no offered route, and on a value the text carries that its registry lacks.
- (G3) Sizes, held by tests: the topic list at most 1,536 bytes, a topic 4,096; a subject that needs more is a second topic.
- (G4) An answer carries `version` (the software's) and `digest` (of the text); the route answers `If-None-Match` with 304. Only topics whose module is on are listed, and beside `start` only those the caller holds a tool for.

## What do the topics say?
- (G5) `start`: one line each for page, folder, design, region, system widget, collection, form, product, order, channel; which tool family does which job; propose rather than write; money, paging, `dry_run`; "read an area's topic before the first work in it".
- (G6) `designer`: the lines the built-in assistant reads, moved into one function both read: how a page is shown (`data-pg`, the `<pg-keep>` kinds and their settings, `data-pg-bound`), the eight operations and their fields (`pg_design_ai_op_names()`, `pg_design_ai_op_fields()`), the classes that work (`pg_design_ai_known_class()`, the design's framework), which system widget does which job, five worked operation lists the test runs through `pg_design_ai_apply_ops()`, frequent mistakes.
- (G7) The design's own values: `design_designs_list` gives each design's framework, colours and font families, read from the design; the topic names the tool. No value is copied into the guide.
- (G8) `panel`, for an agent in the person's browser: the screens the viewer may open, by English label and address, from the list the panel search box reads (`ui/command-palette.md`); a change is kept by the screen's Save button; in the Visual Page Editor use the Assistant box or the `design_*` tools, never drags on the canvas.
- The other topics (`content`, `forms`, `store`, `workspace`, `erp`) follow G5's shape: the area's records in a line each, the tool for each job, what a write needs first, the frequent mistakes. `workspace` points at the canvas guide.

## Who reads it, and where?
- (G9) API: `GET /guide` with optional `topic` and `section` (id `guide.read`): the list, a topic, or one section. Any valid credentials; no scope of its own, as it holds no record of the site.
- (G10) MCP: two lines in the opening instructions (the map in a sentence; read the area's topic first); the resource `pinegrap://guide` and the template `pinegrap://guide/{topic}`; the tool `guide_read` for clients that read no resources; the prompt `design_change` (page_id, wish) that embeds `designer`. Tools of an area with a topic end with "Guide: <topic>.", added in `pg_mcp_tool_guidance()`, not in the route rows.
- The features G11 to G19 (the panel's door, the compact list, presets, budgets, hints, the report): `ai-guide-features-2.md`.
