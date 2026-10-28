---
name: decisions-made-seo
description: Decisions list, SEO part (2026-10-27/28): large image preview, site icon, main-host redirect, per-form structured-data type and author source; settings whose defaults keep running sites
---

## Where do these come from?
- Architect issues #2471-#2475 of 2026-10-27 (asked from the SEO issues #2175, #2176, #2177, #2179, #2182), confirmed by the owner on 2026-10-28; the list's rules: `decisions-made.md`. Each is a setting whose default keeps a running site's output as it is: a "missing default" or "no per-page override" finding here is not a finding.

## What was decided?
- `max-image-preview:large` (#2471, #2175): one `config` column in the SEO card, on for new installs, off for running sites, no per-page override; a noindex page never gets it. A running site changes only when its operator turns it on.
- Site icon (#2472, #2176): one `config` column naming a square source image; on save the image engine makes 48, 180, 192 and 512 px PNGs, the head gets `rel="icon"`, `rel="apple-touch-icon"` and `rel="manifest"`, a small route serves `site.webmanifest`. Nothing changes while the setting is empty.
- Author of a record page's BlogPosting (#2473, #2177): a per-form choice (one `custom_form_pages` column): the record's contact (default, today's rule in `platform/structured-data-open-graph.md`) / the site as Organization with `url` the site root / a form field. The default keeps running sites' output; the operator matches the schema to the visible byline.
- Redirect to the main host (#2474, #2179): one `config` setting, off by default. When on, a 301 only for GET and HEAD page renders whose Host is not `HOSTNAME_SETTING`; never for language domains, an IP or localhost Host, the panel, the API, `health.php`, cron, webhooks or file downloads.
- Structured-data type of record pages (#2475, #2182): one `custom_form_pages` column: BlogPosting (default) / Article / NewsArticle / None. Same builder, another `@type`; None leaves the page's own JSON-LD as the only block. WebApplication / SoftwareApplication only if a later plan asks.

## Which columns do these add?
- `workflow/decisions-made-schema.md` lists them; each lands through a migration step of the open version.
