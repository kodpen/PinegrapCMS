---
name: agent-canvas-design
description: Plan (owner 2026-11-11, #8762): an agent makes a design on the Workspace canvas through the API: layers from an SVG, layer writes, sizes, templates, the server's drawing, saving into Files
---

## What is it for?
- Owner (2026-11-11): "tuvalin api ile tasarım çıkaracak seviyeye getirmek istiyorum"; "tuvale ekleme fikri aslında araç sayısını çoğaltmak yerine tuvali güçlendirmekten gelmişti". No second tool beside the canvas: a chart, a banner, a post picture or a slide is a canvas.
- The decisions: `workflow/decisions-made-workspace-16.md` (#8762). The steps: `agent-canvas-design-steps.md`.

## What is there today?
- An agent sends one SVG and gets a canvas with one imported `svg` layer (`workspace/agent-canvas-svg.md`). It reads any canvas of its channel and edits it with at most 20 operations: add an SVG, replace, remove, an existing text layer's words, a box, the whole canvas anew (`agent-canvas-edit.md`). The assistants have the same as tools (`agent-canvas-assistants.md`).
- The picture is drawn by the first browser of a member who may post (`agent-canvas-picture.md`); the server never rasterises SVG.
- The trial of 2026-11-11 (a chart sent as one SVG) showed the gap: the result is one piece. A person who opens it cannot change a title's words or move one bar without Break apart, and the agent cannot name a part to change it.
- The editor has what a design needs (`ui/image-editor-overview.md`): text layers with the font catalogue, pictures, shapes, icons, scenes, Present, export.

## What does the canvas become for an agent?
- Architect (2026-11-11, for all that follows). An agent writes layers, not one picture: what it makes opens in the editor as if a person had made it, and it changes one layer by its id or name.
- One SVG stays the shortest way in: asked to, the site takes it apart as Import's Break apart does. A text element becomes a text layer, a picture a picture layer, the rest a layer per element.
- Layer writes: a text layer, a picture from Files or the channel, a shape, an icon; a layer's style, order, name and lock. Sizes by name (a post, a story, a slide). Scenes, so a presentation is a canvas too.
- Templates: a canvas a person marks as a template has named layers; an agent lists the templates and makes a canvas from one by giving the words and pictures for its names.
- The picture: the server draws it where the host can (owner: optional, never required), else the browser as today.
- A finished design is saved into Files under the risk tiers (`workspace/agent-risk-tiers.md`): a tier 1 agent's save is a proposal a person approves.

## What does every step keep?
- One set of operations for the API, the MCP tool and the assistants' tools: a step that adds one adds it to all three and to `check_api_schema.php`.
- Who may: as today, an agent in every channel it is added to with an allowed row (owner: "ajan kanalı dışında da olsa"); a test pins that the agents channel is no condition.
- A layer the server writes is one the editor's model accepts: each layer kind has a fixture that the model's tests (JavaScript) and the server's tests (PHP) both read, so the two cannot part.
- The limits stay (artboard 16 to 8000, 1 MB, 20 operations a call) unless a step names a new one with its reason.
- No schema change is planned. A step that finds it needs one asks first (Fixed rules 5).

## What is not in it?
- Translating the words in pictures in the background (owner 2026-11-11): its own plan after CV6, which gives it the server's text drawing.
- Live pages: `workspace/agent-page.md`.

## Who holds it, where does it start?
- Coordinators hand the steps out; claim as `[plan:agent-canvas-design] <step> — <topic>`. CV1 starts.
