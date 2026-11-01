---
name: graph-canvas-core-module
description: The graph canvas core (pg_graph_canvas.js, window.PGGraphCanvas, includes/fn/graph_canvas.php): what it owns, its adapter, its texts, its pgc- CSS and tokens, its first user the Flows canvas
module: graph_canvas
---

## What is it?
- `assets/js/pg_graph_canvas.js`, bundle `pg_graph_canvas.min.js`, printed before its user (the flows screen: `ws_screen_assets()`). Built in steps by `plans/graph-canvas-core.md`.
- It owns what a box-and-arrow canvas needs whatever its boxes mean: geometry helpers, undo history, `viewport(host)`, and `create(adapter)` (pointer, pinch, Space, zoom keys, `destroy()`).

## Where are its texts?
- `pg_graph_canvas_js_strings()` (`includes/fn/graph_canvas.php`), keys `pgc_*`, merged into the Workspace config next to `ws_flow_js_strings()`. The core reads them through `adapter.text(key, ...)`; an adapter may name its own (Flows gives its box role `fl_box` through `boxRole()`).
- `tests/graph_canvas_test.php` checks every `pgc_` key the core reads is handed over, every handed key is read, and each text has its Turkish key.

## What does create() draw and drag since 2c (#4518)?
- `box(item, {className, into, out, fill})`: focus, role, `data-node`, `data-refocus`, in port, the adapter's content, out port (titled while `editable()`); `place()` calls `adapter.placed()`; `edge(index, from, to)` adds the arrow's group to `ui.svg`; `mark(selected)`; `announce(text)` on `ui.live`.
- A press the adapter's `press()` leaves without a kind is the core's: out port → `connect` (`canConnect()`, `connected(from, to)`), box → `move` (`moved(item)`; `none` read-only), arrow → selected, else pan. `cancel()` puts a moved box back. Flows keeps its data threads, the double tap and the empty-canvas click in its hooks.
- The adapter is built before the Workspace lends its `t()`: give `text` and `boxRole` as functions that read it late (the harness caught `t is not a function`).

## How does autosave work (2d, #4557)?
- `G.autosave(host)` (not tied to `create()`): `changed()`, `later()` (save after `host.delay`), `save({keepalive})`, `flush()`, `leave(leaving)`. Its state is kept on `host.record`; Flows passes its `state`, so `state.dirty`, `saving`, `saveError`, `conflict`, `leaveSent` keep their readers.
- `host.send(options)` applies the server's answer itself and rejects with `{ field, message }`; `isConflict(error)` names the 409, `conflict(message)` shows it.

## How is it styled (slice 3)?
- What the core draws carries `pgc-` classes: `pgc-stage`, `-viewport`, `-layer`, `-edges`, `-boxes`, `-node`, `-port(-in|-out)`, `-edge(-line|-hit|-temp|-label)`, `-arrowhead(-on)`, states `-selected`, `-target`, `-panning`, `-space`, `-hidden`. `create()` uses its `CLASSES` unless the adapter's `classes` names one.
- The "Graph canvas core" block of backend.src.css reads only `--pgc-*` tokens with `--pg-*` fallbacks: accent, soft, radius(-sm), shadow(-accent), line, ring, node-accent, marker(-on). No `--ws-*`, no `wsf-` in it (`test_graph_canvas_css_on_pgc_tokens`).
- The Workspace token block maps them to `--ws-*` (and sets the ring per theme) on the same selectors, so its dark values reach them. Flows sets `--pgc-marker: url(#wsf-arrow)` on `.wsf-app`, `--pgc-node-accent` from its group colour, and keeps its states (`wsf-bad`, run view, AI marks) as `wsf-` modifiers on `pgc-` boxes.
