---
name: canvas-one
description: Plan (owner 2026-11-12, #8850): the Workspace canvas as one thing with a drawing, a live page and data; the guarded bridge to the tables bound to it; tables as Data; steps in canvas-one-steps.md
---

## What is it for?
- Owner (2026-11-12): "tuval claude uygulamasındaki yapıt özellikleri (uzaktan api ilede kullanma ile), workspace tablolar da data bölümüne dönüşüyor. arayüzleri sadeleşiyor, mobil kullanırlık için iyileşiyorlar." The canvas does what an artifact of the Claude app does, over the API too; workflow tables become its Data part; the screens get simpler and work on a phone.
- The decisions: `workflow/decisions-made-workspace-18.md` (#8850). The steps: `canvas-one-steps.md`.

## What is a canvas after this?
- Architect (2026-11-12, for all that follows). One thing in a channel with up to three parts: a drawing (layers, opened in the Design Canvas; `agent-canvas-design.md`), a live page (HTML an agent wrote, run in the guarded frame; `agent-live-page.md`) and data (workflow tables bound to it). One card, one name, one list of versions, one set of routes; a part that is not there is not shown.
- What exists stays: a canvas made as a drawing and a page made as a page keep their routes and cards until the step that joins them; nothing made before is lost or converted by hand.
- Not the Page Design Studio: that tool designs the site's pages and is no part of this.

## How does a page reach data?
- Through the frame's shell, never by itself: the page still has no connection, no storage and no way to the panel (`platform/guarded-frame.md`). It posts a message to the shell; the shell, which is the site's own, asks the site with the reader's session and posts the answer back.
- Only what is bound: a canvas names the tables it uses and what for (read, or read and write). The shell answers for those tables and nothing else; a page cannot name a table by itself.
- With the rights of the person looking: a row the reader may not read is not given, a write the reader may not make is refused, as on the table's own screen. A guest gets nothing.
- The first write is asked: before a page's first write in a reader's name the shell asks that person once for this canvas (what is written, to which table); a new version of the page asks again. The card says which tables the page uses.
- Every write is the table's own write (`ws_wtable_row_create()`, `ws_wtable_row_update()`): its checks, its bin, its history, with "through <canvas>".
- Bounds: requests a minute, rows a read, the size of an answer; a page that passes them is stopped and says so.
- Who binds: a person or agent that may edit both the canvas and the table; an agent's binding shows in the channel as a line with Undo.

## What is not in it yet?
- A share link outside the channel, a page that asks an AI in the reader's name, a file a page hands the reader, live pushes: each its own question when wanted.
- The 5000 rows of a table and the cost of formulas on every read: measured in CO5, asked only if a limit must move.
