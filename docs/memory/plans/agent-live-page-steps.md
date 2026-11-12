---
name: agent-live-page-steps
description: Steps LP1-LP5 of plans/agent-live-page.md: the guarded frame and its proofs in a browser, the agent's routes and the card, the assistants' tools, the site's libraries, close-out
---

## Which steps are there?
- S under 400 product lines, M about 1,000; each with its tests and its topic. `free` once the steps it names have landed.

- LP1 (M) The guarded frame. `free`.
  - A module (`includes/fn/guarded_frame.php`) and the route that answers a stored page: the headers, the shell document, the inner frame, the two messages (size in, height out), the two modes (with script for a page, without for a mail's HTML).
  - Proofs in a real browser (the sandbox; the PR says why: a unit test cannot reach what a browser enforces). A page tries each of these and fails: read `document.cookie` and storage, reach `parent` and `top`, call a panel action with the reader's session, load a script, a picture, a font or a style sheet from outside, `fetch` or open a socket, submit a form, open a window, move the top window, move itself to another address, start a download.
  - In the mode without script no script runs at all.
  - Unit tests: the headers of each mode, the shell's markup, the refusal of anything but a stored page's id. Topic `platform/guarded-frame.md`.
  - No agent route and nothing visible yet.
- LP2 (M) The agent's routes and the card. After LP1.
  - `POST /workspace/channels/{id}/pages` (name, html, the line that goes with it, libraries), `GET /workspace/messages/{id}/page` (the source), `PATCH` (a new version, 409 when stale), the MCP tool; who may and the limits as the plan says.
  - The card: name, maker, Open (the frame in the card), Full screen, View source, Download, versions, "Edited by" and Undo. It runs only when opened and stops when the channel is left. How a message marks a page is found without a schema change, else asked first.
  - Topics `workspace/agent-page.md`, `workspace/agent-page-card.md`.
- LP3 (S) The assistants' tools. After LP2. Claude, Pinegrap AI and model assistants make, read and edit a page as they do a canvas (`workspace/agent-canvas-assistants.md`).
- LP4 (S) The site's libraries. After LP2. The list (Chart.js first), how a call names one, how the site puts its own copy into the document; a test that no address reaches a page.
- LP5 (S) Close-out. The lasting rules into the topic files, the API's description, the PR body names the `[NEW]` changelog line, both plan files deleted.

## Which questions are open?
- None for LP1 to LP5. A step that needs a column asks first.
