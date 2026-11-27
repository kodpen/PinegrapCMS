---
name: site-from-brief-census
description: Site from a brief SB1 census from the code: what "Describe the page" sends and costs, how a design template installs, where a flow could start, the proposal, the owner's questions
---

## What is this census?
- Step SB1 of `site-from-brief.md`: what the code has today for making a whole site from one brief, with a proposal for the architect. Paths are relative to `pinegrap/`. No product code or schema changed.
- Parts: this file (findings in short, the owner's questions), `site-from-brief-census-describe.md`, `site-from-brief-census-templates.md`, `site-from-brief-census-doors.md`, `site-from-brief-census-proposal.md`.

## What did it find?
- "Describe the page" is one Pinegrap AI call: words in (2,000 characters), at most 40 boxes out; "Write the texts" is one call a section. Both count against "Write with AI"'s 200 daily drafts and 20 a minute; Pinegrap AI only, with the Workspace module and AI in the licence. A page costs about 7 to 9 requests and 6,000 to 12,000 tokens, estimated (`site-from-brief-census-describe.md`).
- A design template install writes its pages live and takes the home page; its page save, the builder's header and footer and the design variables' answers are reusable, the live writes are not (`site-from-brief-census-templates.md`).
- After the installer the owner lands on the panel, where "Set up your site" asks the site's purpose. The New design window has a place for "Describe it" (icon chosen, never built); the Canva start already hands sketch pages to a new design (`site-from-brief-census-doors.md`).
- Missing: the brief, a plan of pages, building and writing every page in one run.

## What does it propose?
- "Describe it" in the New design window, also opened from the set-up card: brief, plan of pages to approve, then one run of describe, build and write per page into draft tabs of a new design; theme from the five questions' answers. No table, no column (`site-from-brief-census-proposal.md`).

## Which questions are the owner's?
- Is the flow a start of the New design window ("Describe it", proposed), also linked from the set-up card, or a screen of its own?
- Is it offered in the installer's design step too, though Pinegrap AI is not set up there yet (proposed: no)?
- Does a run count against "Write with AI"'s daily cap of 200 like every Describe and text (proposed), or get its own cap?
- Is the plan of pages shown for approval before anything is made (proposed), or made at once?
- May the flow make sample content beyond pages (products, collection records, a contact form), which is live data and not a draft?
- Is the brief kept on the server to make the site again later (a `config_text` row), or only in the browser (proposed)?
- Without Pinegrap AI, is the start shown disabled with the reason (proposed), or hidden? The browser's model is not used for it.
- Does a site made from a brief get the AI note on its pages (`ui/ai-write-note.md`), or only the existing marks on page title and description?
- May the flow pick pictures (stock or AI-made), or leave the builder's sample photos (proposed)?
