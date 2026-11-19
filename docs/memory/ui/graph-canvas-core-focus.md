---
name: graph-canvas-core-focus
description: Graph canvas core focus: adapter focus, canvas.focus() and focused(), out, in, hidden and dim classes, allEdges() faint arrows, groups() headings, press to focus, the announcement
module: graph_canvas
---

## What is it?
- Optional parts of `create()` in `assets/js/pg_graph_canvas.js` for a canvas where one box at a time shows its arrows, added for the Prototype view (designer/prototype-focus.md). Nothing in them means page or link.
- Without `adapter.focus` every arrow is drawn as before and `focus()` does nothing: Flows passes none (its harness 40 of 40, with and without `--min`).

## Which classes does a focus give?
- `adapter.focus: true`: `canvas.focus(id)` puts a box (its id) in focus, `focus(null)` none; `canvas.focused()` gives the id as a string, or null.
- Every arrow is still made by `edge()` and kept in `ui.svg`; its group gets one state class: `pgc-edge-out` (from the box in focus, a self-arrow too), `pgc-edge-in` (into it), `pgc-edge-faint` (another while `allEdges(true)`), else `pgc-hidden` (`display: none`). With no box in focus every arrow is hidden.
- Boxes (`[data-node]`) and targets (`adapter.targets`, their id from `adapter.target()`) at neither end of an out or in arrow get `pgc-dim`; none while nothing is in focus. A box drawn later is dimmed as drawn; a target the adapter adds itself is dimmed on the next `focus()` call (the same id again only refreshes).
- A focus change touches only the arrows whose state changed; the same focus again touches none and announces nothing.
- CSS (core block of backend.src.css): out in `--pgc-accent` with the `--pgc-marker-on` head, in dashed in `--pgc-in` (fallback `--pg-warning`), faint at `--pgc-faint` (0.3), dimmed at `--pgc-dim` (0.45). The view themes them under `.sd-proto`.

## What do allEdges() and groups() add?
- `canvas.allEdges(on)`: the other arrows faint; they go before the first out or in group in `ui.svg`, so the focused ones are drawn over them (an arrow drawn later takes its place the same way).
- `canvas.groups()` draws `adapter.groups()`, the headings `G.arrange()` gives (`{ id, label, x, y, w, kind }`), into one `div.pgc-groups` (`aria-hidden`) first in `ui.layer`, behind the arrows and boxes: each `div.pgc-group pgc-group-<kind>` with `data-group`, a `pgc-group-label` and a `pgc-group-rule` (a line in `--pgc-line`). Each call replaces the holder; the adapter calls it when it draws.

## How does a press focus, and what is announced?
- A press on a box the adapter leaves to the core selects it as before and puts it in focus; a click on the empty canvas (a pan that did not move, ending in pointerup) takes the focus away; a pan keeps it. An adapter that takes the press itself (`press()` sets a kind) decides on its own.
- `adapter.focusChanged(id)` (optional) is told each change of focus (null for none), from `focus()` or a press; the same id again tells nothing. The Prototype view redraws its row marks and notes from it.
- A box put in focus is announced through `announce()`: `pgc_focus` "{var:1}: {var:2} arrows out, {var:3} in", the name from `adapter.label(item)`, else the box's `aria-label`, else its id.
- Tests: `test_graph_canvas_focus` (class sets for a fixture of four boxes, a target and five arrows; faint order; a press; a click on the empty canvas; headings), `test_graph_canvas_css_on_pgc_tokens` (14 class names, each with a rule).
