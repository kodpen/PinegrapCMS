---
name: figma-start-frame-list
description: The Figma start of the New design window (#11558, ST3): when it is listed, the pane's states, the server reads (figma_file, figma_pictures), the frame list part and what Next hands on
module: figma
---

## When is the Figma start listed?
- `pg_designer_start_kinds($has_templates, $figma)` puts it after Template; `pg_designer_figma_start_on()` lists it once `pg_figma_import_start()` exists, which it does since #11643 (`figma-start-import.md`).
- "Next" fires the pane's `sd-figma-frames` event (`detail`: `link`, `key`, `ids` of the ticked frames); the import listens to it.

## What does the pane show?
- `pg_designer_figma_pane($connection, $manager)`: not connected, an empty state with "Connect in Connections" (or "Ask a manager of the site to connect Figma."); connected, the account line with "Manage in Connections", the amber line when the token is refused, the field "Figma file link", its help and the seat line.
- The foot: the window's own button is hidden on this pane; the part draws "Read the file" (grey until the field holds text; Enter presses it), "Reading…" with a spinner, then the count, "Back" and "Next" into `.sd-ns-part-foot` and `.sd-ns-count`.

## How is a file read?
- `api.php` action `designer`, sub-actions `figma_file` (link → frame list) and `figma_pictures` (the same link → frame id → picture address), designers only, in the page cache's clean list. Errors come from `pg_figma_refusal()`: `not_link`, `not_connected`, `token`, `scope`, `access`, `limit` (amber, with the wait in minutes), `network`, `other`.
- `pg_figma_read_file()`: `GET /v1/files/:key?depth=2`, `pg_figma_frame_list()` keeps FRAME and COMPONENT children of each page (hidden ones out), sizes rounded; ticked at the start: the first page with frames, those 1024 px wide or more, at most `pg_figma_max_frames()` (12).
- `pg_figma_read_pictures()`: one `GET /v1/images` (png, scale 0.2) for the first 100 frames. Both answers are kept in `$_SESSION['pg_figma_files']` (one file), and the part keeps each read while the window is open, so "Another file", "Back" and a retry spend no call (two Tier 1 calls per file).
- The token stays on the server; the browser loads Figma's picture addresses itself (`referrerpolicy="no-referrer"`).

## Where is the script?
- `assets/js/designer_figma.js`, loaded by `designer_home.js` (`loadPart()`) the first time a pane with `data-part` is chosen: no cost for the home while unused. Tiles are `role="checkbox"`; Space or Enter ticks, arrow keys move; "Tick all" / "Untick all" per page; over 12 ticked the count turns amber and Next is grey.
- The tile is drawn with the window's own classes (`.sd-fg-*` in `designer_home.css`), not yet a "Choice cards" Design example.

Tests: `tests/figma_test.php`.
