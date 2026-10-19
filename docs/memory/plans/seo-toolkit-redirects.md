---
name: seo-toolkit-redirects
description: Plan (approved 2026-10-19, in progress: step 1 done, redirects are short link options): short link CSV, File Manager choice, slug-change 301, 404 log, canonical, llms.txt, AI-bot robots
---

## Why?
- Short links could not stand for an old address: one-segment ASCII names only, no 301/302 choice, no patterns. There is no 404 log, no bulk import and no automatic redirect when a page is renamed. `page.custom_jsonld` is written only from the visual designer's page settings (#582), not from `edit_page.php`; canonical is automatic with no override. AI-bot policy lives only in WAF toggles; Google states it does not use `llms.txt` (a courtesy for other agents), and Cloudflare now blocks training and agent bots by default on new zones. Wix imports 500 redirects per CSV; Webflow has wildcard rules.

## What is decided?
- Owner decision 2026-10-19: no separate redirect manager (PR #654 closed). A redirect is a short link that answers 301 or 302; old addresses, Turkish letters and `*` patterns are short link names: `docs/memory/platform/short-link-redirects.md`.
- CSV on the short link list: export `from,to,code`; import makes URL short links with that answer, 5 MB and 10,000 rows at most. A name a page, file or folder holds is refused per line; a short link of the same name is skipped, or replaced when asked. Every line passes the save checks against one set kept current (`pg_short_link_prepare()`, `pg_short_link_prepared_put()`). The CSV reader of the closed PR #654 (`pinegrap/includes/fn/redirects.php` there: BOM, Windows-1254, `;` or tab, heading line) is the model.
- Automatic redirect: when a page or folder slug changes in the designer or the page settings, a 301 short link from the old path is offered (checked by default), translated prefixes included.
- 404 log: `not_found_log` (path, referrer, count, first/last seen, user agent class) written by `output_error()` for 404s only, after `pg_short_link_not_found()` had its chance, grouped by path, with "Create redirect" per row (a 301 short link); retention 90 days and a row cap like the WAF log; bot hits excluded by the WAF classification.
- Per-page SEO extras in page settings: canonical override (URL), optional `noarchive`/`max-snippet` directives. The `custom_jsonld` editor with validation is in the visual designer since #582 (`pg_page_custom_jsonld_check()`); the classic `edit_page.php` can reuse the same check.
- `llms.txt`: generated like `robots.txt` (`get_robots.txt.php` pattern) from site name, description and the sitemap's top pages; editable extra text. robots.txt gains a UI section "AI crawlers" that writes `User-agent` blocks for training, agent and search bots consistent with the WAF toggles, plus the `Content-Signal` line.
- API: short links carry `redirect_code` and `is_pattern` (step 1); `/seo/not-found` read.

## What remains?
1. Done: redirects as short link options (11.0.12, router, 404-time patterns, classic screens, File Manager server side, API).
2. CSV import and export on the short link list (S).
3. File Manager short link window: the answer choice (`view_folders.php`, after the open Excel cell edit PR on that file merges) (S).
4. Slug-change hook in designer save and page edit (S).
5. 404 logging with retention (S).
6. Page settings fields + render (S).
7. `llms.txt`, robots AI section, Content-Signal (S).
8. API `/seo/not-found` (S).

## Open questions?
- Should redirects apply before the WAF? No: after the WAF, like every page.

## Where to start?
- Step 2; read `docs/memory/platform/short-link-redirects.md`, `view_short_links.php` and `docs/memory/commerce/short-link-recycle-bin.md`.
