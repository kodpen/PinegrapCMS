---
name: bug-hunt-areas-figma-canva-starts
description: The bug hunt of the Figma and Canva starts of the New design window (hunt 57, 2026-11-25): what was given, what was found and fixed, what read clean, issues opened
---

## Which area was hunted?
- Hunt 57, named by the architect: the Figma and Canva starts (plan steps ST1 to ST7), server side first: the personal access token, the link field, pictures fetched from Figma under an upload's limits, a stopped or half-done import, the report's numbers, the Canva upload of PNG and PDF pages.
- Left alone: the HTML, ZIP and paste importers (hunt 55, `bug-hunt-areas-designer-import.md`).

## What did the hunt's PR fix?
- Canva: a page the site's upload cap shrank was measured with its size from before the shrink, so only its top-left part gave the colours (a long page about a sixteenth). `pg_canva_measure()` samples the opened picture's own size.
- Figma "Stop" pressed while a frame's pictures were fetched: that frame step wrote the work file back after Stop removed it, so its pictures stayed in the file manager. `pg_figma_import_keep_step()` deletes them and writes nothing.
- The 200-picture budget counted every layer of one image fill, though the fill is fetched once: places went empty with "More pictures than one import fetches". `pg_figma_picture_budget()` spends one place per fill.
- A frame whose picture step failed (network, timeout) was made at finish and reported "Carried whole" with its pictures empty; `pg_figma_frame_unfetched()` notes each as "The picture could not be fetched".
- A token refused at the import's nodes read (after the list came from the session) was not marked: Connections kept "Connected". The start marks it like the file read.

## What read clean?
- The token: sealed in `connect_accounts`, never in a page, log or answer; the form field is never filled back; Save gates on a manager; "Disconnect" deletes the row, and every read checks the connection first.
- The link field: only `https://figma.com/(design|file|proto)/<key>` with a letters-and-digits key, so the server calls only `api.figma.com`. Picture addresses go through the outbound client's public-address check, no redirects, the upload's size limit, a type from the first bytes, SVG through the allowlist.
- The converter's inline styles (numbers, cleaned font family, grid tracks without `:` or `/`), the import's work file named after the session, the Canva kind from the bytes and PDF refusal.

## Issues opened
- #11705: files a start stored are left in the file manager when the window is closed, a Canva page is removed, or a Figma finish fails (decision: delete or say so).

## What was not run?
- No database or Figma here: the frame step, finish and Stop were not run; pinned by `tests/figma_start_test.php` (pure helpers, one source check) and `tests/canva_start_test.php` (GD measure).

Related: docs/memory/workflow/bug-hunt.md, docs/memory/designer/designer-start-import.md
