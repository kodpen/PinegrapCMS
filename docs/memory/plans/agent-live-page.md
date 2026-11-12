---
name: agent-live-page
description: Plan (owner 2026-11-11, #8762): an agent makes a live page (HTML, CSS, script) shown in a Workspace channel in a guarded frame: the guard, the card, versions, limits; steps LP1-LP5
---

## What is it for?
- Owner (2026-11-11), after the trial canvas: "attığın görseli gördüm oldukça başarılı ama ben ise aynı claude daki oluşturma mantığın ile sitede de üretebil istiyorum."; asked what beyond the canvas: "Canlı sayfa da olsun (Önerilen)". A chart that shows a value on hover, a filter, a small tool, a presentation: what a still picture cannot be.
- The decision: `workflow/decisions-made-workspace-16.md` (#8762). The steps: `agent-live-page-steps.md`. The canvas keeps its own plan (`agent-canvas-design.md`).

## What is a live page?
- Architect (2026-11-11, for all that follows). One HTML document an agent writes, its styles and script inside it, kept as a file of the channel with its versions, as a canvas is. It shows as a card: its name, who made it, Open. Opened, it runs in a frame in the card or full screen; View source and Download beside it.
- It runs only once a person opens it and stops when the channel is left: a page's script costs no reader who did not ask for it.
- An edit is a new version: the whole page, 409 when stale, "Edited by" and Undo as for a canvas (`workspace/agent-canvas-edit.md`).
- Who may: as for canvases, an application agent with an allowed row in the channel, and the assistants as tools. Team accounts open it; a guest sees the card without Open.

## What is the guard?
- An agent may have read hostile words (a mail, a web page) before it wrote the page. So a page is treated as hostile, whoever made it. Before a page is shown anywhere these hold, proven in a browser:
  - It cannot read or change the panel. It runs in an opaque origin (`Content-Security-Policy: sandbox allow-scripts` on the answer, the frame's `sandbox` without `allow-same-origin`): no cookie, no storage, no way to the page around it, no panel action in the reader's name.
  - It reaches nothing outside: `default-src 'none'`, inline script and style only, pictures and fonts only as data inside the document; no connection, no form, no popup, no download it starts itself.
  - It cannot leave: it cannot move the window, and it sits inside a frame of the site's own (a shell) whose policy stops it from moving itself to another address.
  - The panel gives it its size and nothing else; it gives the panel its height and nothing else.
  - It is told apart: the card and the full screen name the agent and say the page is its work, not the panel's; nothing in the page can cover that line.
- `get_file.php` answers a stored HTML file inside a sandbox with no script (`platform/object-storage-active-types.md`). The page's route is the one place that lets a script run, and only in this frame.
- Libraries: a short list the site already ships (Chart.js first), named in the call and put in by the site; never an address.
- Limits: 500 KB a page, 20 versions kept, the frame's height bounded in the card.

## What is not in it?
- A page that reads the site's records or the channel, a page a person writes, a page on the public site: each its own question, asked when wanted.
- A mail's HTML is shown by the same module in its mode without script (`workspace-mail-screen.md`); whichever lands first builds the module.

## Who holds it, where does it start?
- Coordinators hand the steps out; claim as `[plan:agent-live-page] <step> — <topic>`. LP1 starts; nothing shows a page before LP1 has landed.
