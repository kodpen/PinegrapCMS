---
name: seo-toolkit-redirects
description: Plan (approved 2026-10-19; step 1 redirect manager done, PR #PRNUM): left: auto 301 on slug change, 404 log, page canonical, llms.txt and robots AI section, API
---

## Why?
- Redirects exist only as short links with a single path segment (`router.php:440-525`); there is no 404 log, no bulk import and no automatic redirect when a page is renamed. `page.custom_jsonld` is written only from the visual designer's page settings (#582), not from `edit_page.php`; canonical is automatic with no override. AI-bot policy lives only in WAF toggles; Google states it does not use `llms.txt` (a courtesy for other agents), and Cloudflare now blocks training and agent bots by default on new zones. Wix imports 500 redirects per CSV; Webflow has wildcard rules.

## What is decided?
- Redirect manager: table `redirects` (from_path, to_url, code 301/302, is_pattern, hits, last_hit, created_by, created_at); matched where a page request the router handed to `get_page.php` is about to answer 404, so pages, files and short links always win (exact first, then patterns with `*` → `$1`). Loops and self-redirects refused on save. CSV import/export (`from,to,code`), bulk delete, search. Short links stay as they are.
- Automatic redirect: when a page or folder slug changes in the designer or the page settings, a 301 row is offered (checked by default) from the old path, translated prefixes included.
- 404 log: `not_found_log` (path, referrer, count, first/last seen, user agent class) written by `output_error()` for 404s only, grouped by path, with "Create redirect" per row; retention 90 days and a row cap like the WAF log; bot hits excluded by the WAF classification.
- Per-page SEO extras in page settings: canonical override (URL), optional `noarchive`/`max-snippet` directives. The `custom_jsonld` editor with validation (JSON parse + `@type` present) is in the visual designer since #582 (`pg_page_custom_jsonld_check()`); the classic `edit_page.php` can reuse the same check.
- `llms.txt`: generated like `robots.txt` (`get_robots.txt.php` pattern) from site name, description and the sitemap's top pages; editable extra text. robots.txt gains a UI section "AI crawlers" that writes `User-agent` blocks for training, agent and search bots consistent with the WAF toggles, plus the `Content-Signal` line.
- API: `/redirects` CRUD with dry run; `/seo/not-found` read.

## What remains?
1. Done (PR #PRNUM): migration step 11.0.11 (`redirects`, `not_found_log`), the match where a routed page request is about to 404, manager screens, CSV import and export. Behaviour: docs/memory/platform/redirect-manager.md.
2. Slug-change hook in designer save and page edit (S).
3. 404 logging with retention (S): `not_found_log` exists (11.0.11) with `path_hash` as its key; write it beside `pg_redirect_not_found()` (same three call sites), and link each row to `edit_redirect.php?from=`.
4. Page settings fields + render (S).
5. `llms.txt`, robots AI section, Content-Signal (S).
6. API routes + schema (S).

## Open questions?
- Should redirects apply before the WAF? No: after the WAF, like every page.

## Where to start?
- Step 2 or 3; read docs/memory/platform/redirect-manager.md (save through `pg_redirect_save()`, which checks loops) and `docs/memory/waf/waf-ai-bots-ranges.md` for step 5.
