---
name: site-from-brief-census-proposal
description: Site from a brief census proposal: the "Describe it" start of the New design window, screen by screen (brief, plan of pages, making, editing), what is stored, the cost a site, the steps it suggests
---

## Which way, in one line?
- A start "Describe it" in the New design window: the brief's questions, a plan of pages to approve, then one run that writes sketch pages, builds them as draft tabs of a new design and writes their texts. It reuses Describe, the sketch builder, the five questions and "Write the texts"; it never saves a page or touches the live site.

## Screen 1: the brief (the start's pane)
- Head "Describe your site". Fields: business name (the site name prefilled), "What do you do?" (2,000 characters, as Describe), kind (the set-up card's eight purposes, the stored one chosen), "Who are your customers?", tone (Friendly, Formal, Plain), "Pages you want" as chips (Home, About, Services, Contact... prefilled by the kind), brand colour (optional). Language: the pages' language.
- Without Pinegrap AI the row is shown disabled with the reason from `pg_ai_write_problem()` / `pg_ai_state()`, as Template without templates.
- Foot "Plan my site": one request.

## Screen 2: the plan of pages
- The model answers a list: each page's name, address, one-line purpose, its sections in words, its place in the menu; and a theme proposal (feel, type feel, room, second colour). Shown as rows the person can rename, remove, reorder, add to; the theme as the five questions' answers, changeable.
- Nothing is made before "Make my site". Back keeps the brief.

## Screen 3: the making
- The editor opens on a new design (the Canva start's path: sketch pages handed through the browser). The run, one page after the other: describe (boxes) with the plan's section words, the reader's kinds, "Build the page" (draft tab), then "Write the texts" section by section. A progress line "Making your site: page 3 of 6"; Stop keeps what is made.
- The header and footer are made once by the builder from the plan's menu (`opts.site`); the theme from the answers (`pg_design_variables_from_answers()`).
- Every page is a draft tab, unsaved, as "Build the page" leaves it today; texts carry the AI mark where a page field (title, description) is written (`ui/ai-write-marks.md`).

## Screen 4: after
- The person edits in the editor as usual; each built page keeps its sketch, so "Build again" and "Write the texts" work on one page. Save writes the design with its pages as drafts (Drafts folder); Publish, with its sheet and holds, puts them on the site.
- A design with drafts is never the home page until published; the flow never claims the home page.

## What is stored?
- Proposed: nothing new. The brief and the plan live in the browser with the sketch (`pg_proto_sketch_<id>`), the pages in `page` and `page_drafts` on Save, the theme in the design's variables file. A brief kept on the server (to make again later, or for the API) would need a `config_text` row or a column: asked.

## What does a site cost?
- One plan request, then about 7 to 9 requests a page (`site-from-brief-census-describe.md`): 6 pages about 50 requests of the 200 daily drafts and 45,000 to 80,000 tokens of the licence's day; about three minutes at 20 a minute. A run counts against the same daily cap as "Write with AI"; a separate cap or a one-run exception is asked.

## What could the build steps be? (for the architect)
- 1: the start's row and pane, the brief's fields and checks (pure, tested). 2: a server action `site_plan` (one call, pages and theme as JSON, checked and cut). 3: the plan screen and the hand-over of sketch pages. 4: the editor's run (build all, write all, progress, stop). 5: the set-up card's line. Each under 400 lines.

## What could the code not settle?
- Real token counts and the model's quality on a whole plan: no live Pinegrap AI here. The licence's daily token figure lives at the gateway.
