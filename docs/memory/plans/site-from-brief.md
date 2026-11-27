---
name: site-from-brief
description: Plan (owner 2026-11-27, poll 246): a whole site from one brief, "Describe it" in the New design window; the census is done, steps SB2 to SB7 need no table and no column
priority: 5
---

## What is asked?
- Owner (2026-11-27, poll 246, `workflow/decisions-made-product-117.md`): a whole site from one brief. The list it belongs to and its rules: `plans/global-features.md`.
- What the code has today and the flow screen by screen: `plans/site-from-brief-census.md` and its parts (SB1); the screens' words are in `site-from-brief-census-proposal.md`.

## What did the architect settle after the census? (2026-11-27; not the owner's words)
- The flow is the start "Describe it" of the New design window, whose place is drawn (`designer/designer-home-tidy-drawing.md`), also opened from the "Set up your site" card. Not in the installer: Pinegrap AI is not set up there.
- It always makes a new design; every page is a draft tab; it never claims the home page and never writes the live site.
- The plan of pages is shown and approved before anything is made.
- It makes pages, the menu, the header and footer and the theme. No products, records or forms: those are live data. Pictures stay the builder's sample photos.
- A run counts against the daily cap of "Write with AI", no cap of its own: the plan screen says how many of the day's drafts the run takes and refuses to start when fewer remain.
- The brief and the plan live in the browser with the sketch; nothing is kept on the server.
- Without Pinegrap AI the start is shown disabled with the reason, as "Template" without templates. The browser's model is not used.
- The existing AI marks on a page's title and description apply (`ui/ai-write-marks.md`); no new note.
- No owner question is left: no table, no column, no new limit.

## Which steps? (S under 400 lines, M under 1,000; each waits for the one before it)
- SB1 Census: done, `plans/site-from-brief-census.md`.
- SB2 (S) The start's row and pane: "Describe it" in "Start new", the brief's fields (business name, what you do, kind from the set-up card's purposes, customers, tone, pages wanted as chips, brand colour, language), their checks as pure functions, the disabled state.
- SB3 (S) The server action `site_plan` in a new module `includes/fn/site_plan.php`: one Pinegrap AI call; the answer (pages with name, address, purpose, sections in words, place in the menu; the theme as the five questions' answers) checked and cut by pure functions; counted as one draft.
- SB4 (S) The plan screen: rows to rename, remove, reorder and add, the theme's answers, the line on the day's drafts; "Make my site" hands sketch pages to a new design, as the Canva start does.
- SB5 (M) The editor's run: a page after the other described, built as a draft tab and written section by section; the header and footer once from the plan's menu, the theme from the answers; a progress line and Stop, which keeps what is made; a refused request ends the run with its reason.
- SB6 (S) The set-up card's line "Make your site from a description", which opens the New design window on the start with the chosen purpose.
- SB7 (S) Close-out: the lasting rules to `designer/`, the plan and the census deleted.

## What does every step keep?
- SB2, SB4 and SB5 are tried in a browser on a sandbox at 1440 and 390 px, panel light and dark: no unit test reaches the window and the tabs. Without a live Pinegrap AI the run is tried with a stand-in answer, and the PR says so.
- The figures `designer/designer-light-and-simple.md` asks for; `node --check` after `style_designer.src.js`; a new part of the editor in its own file where the bundle allows.
