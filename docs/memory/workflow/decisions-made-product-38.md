---
name: decisions-made-product-38
description: Decisions list, product part 38 (2026-11-09 on): a translated page's index verdict counts only its own words, never list records or products; a record save opens a translation job
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## What does a translated page's index verdict count under "reviewed"? (2026-11-09)
- Owner (2026-11-09): only the page's own words: its texts, its title and description, its shared components and menus. Texts a form list widget, the catalog or other database values draw (list records, products, `pg_tr_db_text()` swaps) never turn a page noindex, neither missing nor unreviewed machine translations of them: they are drawn in the source language until translated. Google attribution (`-x-mtfrom-`) still counts them.
- A record's own page (a form item view widget showing one record) counts that record's fields as its own words: it stays noindex, and out of sitemap.xml and the alternates, until they are translated and reviewed.
- Saving a record of a form whose records a translated list or record view includes opens a translation job per language with "translate on save" and a ready engine (Pinegrap AI, Claude, Google), the way a page save does. Only for a record saved by a signed-in user who may edit the form's records (panel screens, staff entry on the site, the external API with a key); never for a visitor's submission, so nobody outside spends the engine credit.
- Reason: an indexed page dropped out of search because new list content was untranslated: on kodpen.com a new blog post turned `/en/blog` and `/en/` (the home lists the latest posts) `noindex, follow`, the kept answer took them out of sitemap.xml and the alternates. Noindex removes a page from the results, and a site may have no machine credit, so nothing would ever translate the post.
- Refused: hiding untranslated records from lists; a "once indexed stays indexed" verdict; a percentage threshold.
- Rules: `visitors/translation-index-verdict.md`, `ui/translations-screen-noindex-reason.md`, `core/content-translation-record-save-jobs.md`.

Related: docs/memory/workflow/decisions-made-product-37.md, docs/memory/workflow/decisions-made.md
