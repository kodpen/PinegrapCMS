---
name: designer-publish-overview
description: Start file of the visual designer's Publish sheet and its Publish check: what they are, the decisions that bind them, their parts by label, which file holds each part
---

## What is it?
- Publish in the visual designer opens the Publish sheet: every page of the design with its state, the ticked pages put on the site in one save (`designer-publish-sheet.md`). A held row (an experiment's variant, a bundle's draft, a draft waiting for approval) goes only with "Publish anyway" and a reason (`designer-publish-sheet-held-row.md`).
- The Publish check (#11518, #11528, #11537, #11577, #11607) gives each ticked page a result, "Ready", "{n} to look at" or "{n} blocking", and its findings in five groups: Links, Accessibility, Search engines, Weight, Content.

## What binds it?
- Owner (2026-11-24): the check goes in, no new bar button, defaults need no choice, parts load on first use (`designer-light-and-simple.md`). No table or column: what a page keeps (an ignored finding's reason) lives in its tree.
- No existing check is rewritten: each is read into one finding shape. A check that cannot run says so and blocks nothing. Weight never blocks.

## Which file holds what?
- The sheet, rows, results, groups, a path that selects the element, a blocking page: `designer-publish-check-results.md`.
- The finding shape, the blocking table, a failed check: `designer-publish-findings.md`.
- "Fix", "Show me", "Ignore on this page", the Weight group's bars and unused rules: `designer-publish-check-fix-ignore.md`.
- The weight measure and its budget: `designer-publish-weight.md`; what `seo_check` measures and answers: `designer-publish-weight-seo-check.md`.
- The Content group (starters' sample words and pictures, a link to the page itself): `designer-publish-content.md`.
- The drawing: `designer-publish-check-drawing.md` with boards P1 to P4 (`designer-publish-check-drawing-results.md`) and P5 to P7 (`designer-publish-check-drawing-findings.md`).

## What is still open?
- Component field leftovers: the finding, its blocking and its Fix are built, but no step hands `leftovers` in (`designer-publish-check-results.md`).

## How is a new check added?
- Its answer read into the finding shape in `pg_publish_findings.js` (one reader, a group, a code; blocking only through the fixed table), asked in `style_designer_publishcheck.js` `_pcRun()`, its words through `_sdT()`; a fix through `_pcFixer()` and `_sdPageEdit()`.
