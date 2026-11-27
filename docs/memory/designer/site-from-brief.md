---
name: site-from-brief
description: A whole site from one brief, the "Describe it" start: what it may and may not make, where it starts, what it costs, where nothing is stored, and which file holds each part
---

## What is it, and where was it asked?
- Owner (2026-11-27, poll 246, `workflow/decisions-made-product-117.md`): a whole site from one brief, made by AI: pages, menu and theme.
- The "Describe it" start of the New design window: the brief (`describe-start.md`), the plan of pages (`site-plan-request.md`, `site-plan-screen.md`), then the editor's run (`site-run.md`). Also opened from the set-up card's line (`ui/launch-journey-describe-line.md`).

## Which rules does every change to it keep?
- It always makes a new design; every page is a draft tab; it never claims the home page and never writes the live site. Save puts the pages in Drafts, Publish puts them on the site, as for any design (`page-drafts.md`).
- The plan of pages is shown and approved before anything is made.
- It makes pages, the menu, the header and footer and the theme only. No products, records or forms: those are live data, not drafts of a design. Pictures stay the builder's sample photos.
- A run counts against the daily cap of "Write with AI" (`ui/ai-write.md`), with no cap of its own: the plan screen says how many of the day's drafts the run takes and refuses to start when fewer remain.
- The brief and the plan live in the browser (`pg_site_brief`, `pg_site_plan`, the sketch); nothing is kept on the server, no table and no column.
- Pinegrap AI only. Without it, or with "Write with AI" off, the start is shown disabled with its reason, and the set-up card leaves its line out. The browser's model is not used.
- The existing AI marks on a page's title and description apply (`ui/ai-write-marks.md`); no AI note of its own.

## Why is it not in the installer?
- The installer's design step runs before the panel exists, and Pinegrap AI (Workspace module, application, licence with AI) is not set up on a fresh install. A design template install writes its pages live and takes the home page, which this flow never does.

## What does a site cost?
- One plan request, then one draft a page (its drawing) plus one a section (its texts), `pg_site_plan_cost()`; a page of 6 to 8 sections takes 7 to 9 of the 200 daily drafts.
- Six pages: about 50 requests and, estimated from the prompts (not measured), 45,000 to 80,000 tokens of the licence's day; about three minutes at the Sketch tool's 20 a minute. The licence's daily token figure lives at the gateway, not in the code (`workspace/workspace-ai-token-counter.md`).

## Which file holds each part?
- Brief and its checks: `includes/fn/site_brief.php`, `assets/js/designer_describe.js`; tests `tests/site_brief_test.php`.
- Plan request and screen: `includes/fn/site_plan.php`, action `designer/site_plan`; tests `tests/site_plan_test.php`, `tests/site_plan_screen_test.php`.
- Run: designer part `siterun` (`includes/designer_parts/siterun.php`, `assets/js/style_designer_siterun.js`), reusing the Sketch tool's draw, build and write; tests `tests/site_run_test.php`.
- Set-up card line: `pg_launch_journey_describe_line()`; test `tests/launch_journey_test.php`.

## What was never tried?
- A run against a live Pinegrap AI: every step was tried with stand-in answers. Real token counts and the model's quality on a whole plan are the owner's first live try.
