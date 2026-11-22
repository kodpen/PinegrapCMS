---
name: user-docs-2026-11
description: Plan (owner 2026-11-23): pinegrap.com documentation from 2026.4.10 (#500) to 2026.11.0; six new pages, steps UD1 to UD15, one page per session
---

## What is asked?
- Owner (2026-11-23): "pinegrap dökümantasyonu 2026.11.0'a göre ayarlayalım": some features belong in the documentation and are missing. This file says how the documentation will look; the pages are written in the steps below.

## What is there today?
- 28 documentation pages, every one written up to #500 (2026.4.10); nothing merged after it is on a page (`workflow/user-docs-pages.md`).
- The 2026.11.0 section of `pinegrap/changelog.txt` has 182 `[NEW]` lines, the backbone of this plan. Smaller user-visible changes are found per page with the git log recipe of `user-docs-pages.md`.
- Which page gets which feature: `user-docs-2026-11-pages.md`, `user-docs-2026-11-modules.md`; the new pages: `user-docs-2026-11-new-pages.md`.

## What binds the work?
- Everything in `workflow/user-docs-writing.md` (English only, structure, Access lines, bold labels, Publish is live, never a `/tr/` page).
- The site runs 2026.4.10 until 2026.11.0 is released, so a new section opens with a note "Available from 2026.11.0" (`theme-info`); the note is removed in the release round.
- A page is written from the feature's overview file where one exists, else from its topic files; the panel is checked on dev only where memory is unclear.
- A new section's id is the help anchor a screen links to: before naming one, read the screen's row in `pinegrap/get_help_url.php`; an anchor a screen needs but no page has goes into `user-docs-help-anchors.md`.
- A step moves the page's mark in `user-docs-pages.md` to the highest PR it covers, in a docs-only PR; a new page gets its line there and in the coverage file.
- A page that grows past ~15,000 words is split into a `-cont` page, as `/commerce-cont` was.
- Design templates are listed once, on `/templates`; other pages link there.

## Which steps are there?
One step is one page (or one new page), one session on the owner's machine, one docs-only PR for the mark. Order: the screens every user meets first, then the big modules, then the new pages.
- UD1 `/introduction`, done 2026-11-23 (four new sections). Left: smaller changes since #500, the new logo; Content review moved to UD14 `/pages`.
- UD2 `/site-settings`, `/system`, done 2026-11-23 (six sections). Left: Docker and the importers (to UD14 `/installation`), smaller changes since #500.
- UD3 `/visual-page-editor`, part 1 done 2026-11-23 (eight sections; the page is ~21,000 words, its split into `-cont` is still open). Left: the `-cont` subjects in the modules file.
- UD4 `/templates` (new).
- UD5 `/commerce`, `/commerce-cont`, done 2026-11-23: sections `returns`, `cart-quotes`, `subscriptions`, `checkout` (help anchors), six h3s. Left: order report dashboard, POST /orders (UD6).
- UD6 `/marketplaces` (new) and the commerce part of `/api`.
- UD7 `/erp` and `/erp-cont`: automatic invoices and the ERP changes since #500 (git log recipe).
- UD8 `/workspace`: channel changes, calls, e-mail, live pages, the canvas, notes groups and export.
- UD9 `/workspace-cont`: assistants and agents, MCP connectors, voice answers, GitHub link.
- UD10 `/flows` (new). UD11 `/workflow-tables` (new).
- UD12 `/communication` (new) and `/campaigns`.
- UD13 `/privacy` (new) and `/security`: passkeys, CAPTCHA providers, personal data export and erasure.
- UD14 `/visitors`, `/seo`, `/multilingual`, `/pages-cont`, `/files`, `/folders`, `/calendars`, `/users`, `/contacts`, `/installation`: their rows in the pages file.
- UD15 release round, when 2026.11.0 is released: remove the "Available from" notes, `/changelog` article `v2026-11-0`, the home page's Recent releases box.

## Where does a step start?
- This file, the page's row (pages or modules file), the page's tree in the editor, then the overview files the row names.
