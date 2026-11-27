---
name: describe-start
description: The "Describe it" start of the New design window (SB2): its row and disabled state, the brief's fields, their checks and the browser twin, where the brief is kept, what "Plan my site" does
module: site_brief
---

## Where is it?
- "Describe it" in "Start new" of the New design window, after Template (`pg_designer_start_kinds()`), icon `bi-stars`, line "A whole site from your words", pane head "Describe your site". Plan: `plans/site-from-brief.md`.
- Also opened from the set-up card's line "Make your site from a description" (`ui/launch-journey-setup-card.md`), shown only while the start is offered.
- The pane is drawn on the server (`pg_designer_describe_pane()` → `pg_site_brief_pane()`, `includes/fn/site_brief.php`); the part `assets/js/designer_describe.js` (loaded on first choice, `data-part="describe"`) presses the chips and the tone, checks, keeps the brief and draws the foot's "Plan my site".

## When is it disabled?
- Without Pinegrap AI or with "Write with AI" switched off: `pg_site_brief_problem()` is `pg_ai_write_problem()`. The row is `aria-disabled`, its line reads "Needs Pinegrap AI", the whole reason is its `title`; no fields are drawn and `?start=describe` falls back to Blank. The browser's model is never used.
- A kind's disabled line is its `off`, its reason `why` (Template's `off` is "There are no templates.").

## Which fields does the brief have?
- Business name (the site's `ORGANIZATION_NAME` first), Kind of site (the set-up card's eight purposes; the stored `purpose`, else `suggested`, else Other), "What do you do?", "Who are your customers?" (optional), Tone (Friendly, Formal, Plain as `pg-seg`), Brand colour (optional, hex or picker), "Pages you want" as chips plus "Another page".
- Chips: 14 keys (`pg_site_brief_page_keys()`); a kind presses its set (`pg_site_brief_pages_for()`), changed with the kind until the person presses a chip. The language is a line: the pages are written in the site's language (`pg_ai_write_site_language()`).

## Which checks apply?
- `pg_site_brief_check()`: name 1-80, what 20-2,000, customers up to 300, 1-12 pages of up to 40 each (duplicates in any case and empty ones dropped), colour `#rrggbb` or empty; unknown kind is Other, unknown tone Friendly. Too long is refused, never cut. Lengths in `pg_site_brief_limits()`.
- It returns the clean brief, a code a field (`codes`) and the words. The browser twin `sdStartParts.describe.check()` gives the same brief and codes; `tests/site_brief_test.php` runs both on the same briefs.

## Where is the brief kept, and what does "Plan my site" do?
- In `localStorage` `pg_site_brief` (the brief plus `keys`, chip label to page key); the pane fills from it when opened again. Nothing is stored on the server.
- "Plan my site" checks, marks the fields (`aria-invalid`, the words under each) and focuses the first; while valid it keeps the brief and asks the plan of pages, shown on the plan screen (`site-plan-screen.md`).

Related: docs/memory/designer/new-design-window.md, docs/memory/ui/ai-write.md, docs/memory/ui/launch-journey-setup-card.md
