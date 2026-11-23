---
name: user-docs-2026-11
description: Plan (owner 2026-11-23): pinegrap.com docs from #500 to 2026.11.0; features, help anchors, how-to pass and three new pages done; left the smaller changes and UD15
---

## What is asked?
- Owner (2026-11-23): "pinegrap dökümantasyonu 2026.11.0'a göre ayarlayalım": some features belong in the documentation and are missing. This file says how the documentation will look; the pages are written in the steps below.

## What is there today?
- 28 documentation pages, every one written up to #500 (2026.4.10); nothing merged after it is on a page (`workflow/user-docs-pages.md`).
- The 2026.11.0 section of `pinegrap/changelog.txt` has 182 `[NEW]` lines, the backbone of this plan. Smaller user-visible changes are found per page with the git log recipe of `user-docs-pages.md`.
- Which page got which feature: `user-docs-2026-11-pages.md`, `user-docs-2026-11-modules.md`; the new pages: `user-docs-2026-11-new-pages.md`.

## What binds the work?
- Everything in `workflow/user-docs-writing.md` (English only, structure, Access lines, bold labels, Publish is live, never a `/tr/` page).
- The site runs 2026.4.10 until 2026.11.0 is released, so a new section opens with a note "Available from 2026.11.0" (`theme-info`); the note is removed in the release round.
- A page is written from the feature's overview file where one exists, else from its topic files; the panel is checked on dev only where memory is unclear.
- A new section's id is the help anchor a screen links to: before naming one, read the screen's row in `pinegrap/get_help_url.php`; an anchor a screen needs but no page has goes into `user-docs-help-anchors.md`.
- A step moves the page's mark in `user-docs-pages.md` to the highest PR it covers, in a docs-only PR; a new page gets its line there and in the coverage file.
- A page that grows past ~15,000 words is split into a `-cont` page, as `/commerce-cont` was.
- Design templates are listed once, on `/templates`; other pages link there.

## Which steps are there?
One step is one page, one session on the owner's machine, one docs-only PR. State on 2026-11-23, all published in English:
- Done, UD1 to UD14: every 2026.11.0 `[NEW]` feature of the plan's pages and module files is on a page, and every help anchor of `get_help_url.php` has its section (`workflow/user-docs-help-anchors.md`).
- Done, the how-to pass: every 2026.11.0 part says what it is for and how to use it, step by step (`workflow/user-docs-writing.md`).
- New pages: `/templates` (UD4), `/visual-page-editor-cont` (the newer editor subjects of UD3) and `/artifact-canvas` (owner, 2026-11-23: the canvas got a page of its own; its help button is issue #11185).
- The other planned pages became sections, so no help anchor moved: marketplaces on `/api` (`marketplaces`, `marketplace-products`); Flows and Workflow Tables on `/workspace-cont`; Communication, SMS, WhatsApp and surveys on `/campaigns`; privacy on `/contacts` (personal data export and erasure) and `/security` (`audit-trail`); bounces and transports on `/site-settings#smtp-settings`.
- Left: the smaller changes since #500 per page (git log recipe of `user-docs-pages.md`), which move each page's mark; then UD15.
- UD15 release round, when 2026.11.0 is released: remove the "Available from" notes, `/changelog` article `v2026-11-0`, the home page's Recent releases box.

## Where does a step start?
- This file, the page's row (pages or modules file), the page's tree in the editor, then the overview files the row names.
