---
name: designer-publish-check
description: Plan (owner 2026-11-24): one check in the Publish sheet over links, accessibility, search engines, page weight and left sample content, with Fix and Ignore; steps PC1 to PC6
priority: 7
---

## What is asked?
- Owner (2026-11-24): the seven additions go in (`workflow/decisions-made-product-106.md`). This one: what is wrong with a page, in one place, before it goes live.

## What is there today?
- Four places: the Publish sheet lists pages and states (`designer/designer-publish-sheet.md`), the audit measures contrast, target size and tree rules (`designer/editor-audit-panel.md`), the SEO check renders the draft (`designer/seo-check-draft-render.md`), the Prototype view lists link problems (`designer/prototype-problems.md`). Nothing measures weight.

## What is built?
- The Publish sheet's rows get a result: "Ready", "n to look at" or "n blocking". A row opens its findings in five groups: Links, Accessibility, Search engines, Weight, Content.
- A finding: one sentence of what, the element's path (a click closes the sheet and selects it), and "Fix" where one press can do it, else "Show me". "Ignore on this page" asks for a reason and keeps it.
- Weight, new, measured on the draft's server render: bytes of markup, styles, scripts and the pictures of the first screen; a picture more than twice its drawn size; a picture without width and height; scripts that hold the first paint; rules of the design's own stylesheets no page uses (listed, never removed by itself). Green, amber, red against a budget whose numbers the sheet shows.
- Content, new: a starter's sample words or pictures still on the page, an empty heading, a link that leads to itself.
- Blocking is short and fixed: a link to a deleted page, a form sent nowhere, a page without a title, a left-over value of a closed component field. A blocking row publishes only with "Publish anyway", as a held row does (`designer/designer-publish-sheet-held-row.md`).
- Checked when the sheet opens, for the ticked pages, and kept per page until its tree changes.

## What must it keep?
- No existing check is rewritten: each is read into one finding shape. A check that fails to run says so in its group and blocks nothing.
- "Fix" changes the tree as an edit does: one undo step, saved by the same Publish.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- PC1 (M) The finding shape and the four existing checks read into it, pure; the blocking table.
- PC2 (M) The weight measure, pure over a rendered page and its files' sizes; the budget.
- PC3 (S) The content check from the starters' own sample values.
- PC4 (M) after PC1 and DD7: the sheet's results, groups, select the element.
- PC5 (M) after PC4, PC2, PC3: Fix, Show me, Ignore with its reason kept with the page's draft.
- PC6 (S) Close-out: memory in `designer/`, this file deleted, the `[NEW]` line named.

## What does every step keep?
- No table or column; a step that needs one stops and asks the architect. The drawing: `designer-drawings.md`.
- Claim as `[plan:designer-publish-check] <step> — <topic>`.
