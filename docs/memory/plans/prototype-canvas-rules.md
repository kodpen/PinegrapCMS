---
name: prototype-canvas-rules
description: Plan prototype-canvas: what today's Prototype view does that no step may lose (K1 to K9), the rules every step keeps, and what a step's PR body carries so that no feature is skipped
---

## What does today's view do that no step may lose?
- (K1) An arrow is the link's href: drawing one writes `props.href`, the `_attrs` href, the btn or the nth link of a text (`hrefPatch()`), kept by page id for a tab. The normal Save or Publish writes it; the view has no save path of its own.
- (K2) Who may draw: roles 0-1 everywhere, roles 2-3 inside editable areas; a locked tab draws nothing; classic menus, legacy regions and widget page settings are read-only arrows.
- (K3) The two warnings, once per opening: a shared component changes everywhere, a live page's link goes live on Publish.
- (K4) Ctrl+Z and Ctrl+Y undo the open tab and read the map again; the editor's document shortcuts skip the view.
- (K5) Broken has one definition, `pg_link_resolve()` and its twin; no hidden page name reaches a content-level operator.
- (K6) Moved places: frames of saved tabs and shared components on the design (`proto` of the tab layout, both normalisers), the rest per browser.
- (K7) The view is a snapshot; a later load or a close drops an earlier answer (`_sdProto.seq`).
- (K8) Bootstrap toggle links are no hotspots; a classic menu or region gives one link per item.
- (K9) `pg_proto_hotspots.js` stays pure and tested in Node.
- The files that say how each works: `designer/prototype-view.md`, `prototype-hotspots.md`, `prototype-places.md`, `prototype-view-decisions.md`.

## Which rules does every step keep?
- What a box-and-arrow canvas needs whatever its boxes mean (order, arrangement, focus, routing, checks) goes into `pg_graph_canvas.js` as pure helpers or optional adapter fields; a user that passes none draws as before. Nothing that means page, link or prototype goes into the core; nothing generic is written in the view.
- An engine PR runs `tests/graph_canvas_test.php`, `tests/workspace_flows_canvas_test.php` and the Flows harness (`workspace/workspace-flows-canvas-harness.md`) and gives the result.
- New classes of the core are `pgc-` and read only `--pgc-*` tokens; the view themes them under `.sd-proto` for light and dark.
- Texts: `_sdT('English')` in the designer, a `pgc_*` key in `pg_graph_canvas_js_strings()` for the core, the Turkish with `tools/tr_add.php`. The drawing's Turkish words are samples.
- A pure function gets its test first (`tests/graph_canvas_test.php`, `tests/proto_hotspots_test.php`).
- No schema change, no new endpoint. A need for either comes back as a question to the architect.
- A step that finds its feature cannot be built as written stops and asks; it does not leave the feature out.

## What does a step's PR body carry?
- A table of the F and K numbers its step names, each with the test or the picture that shows it. A number with nothing beside it is not done: the PR stays a draft.
- Pictures from a test site with a design of at least thirty pages, three tab groups, two shared components and two broken links: 1440 px, light and dark, before and after, posted under the plan's message in the agent channel.
- In words: what could not be verified.
