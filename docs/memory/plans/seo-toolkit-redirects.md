---
name: seo-toolkit-redirects
description: Plan (approved 2026-10-19, in progress: steps 1-7 done; redirects are short link options with CSV, the File Manager choice, the rename 301 and the 404 log): the 404 API left
---

## Why?
- Short links could not stand for an old address: one-segment ASCII names only, no 301/302 choice, no patterns. There is no 404 log, no bulk import and no automatic redirect when a page is renamed. `page.custom_jsonld` is written only from the visual designer's page settings (#582), not from `edit_page.php`; canonical is automatic with no override. AI-bot policy lives only in WAF toggles; Google states it does not use `llms.txt` (a courtesy for other agents), and Cloudflare now blocks training and agent bots by default on new zones. Wix imports 500 redirects per CSV; Webflow has wildcard rules.

## What is decided?
- Owner decision 2026-10-19: no separate redirect manager (PR #654 closed). A redirect is a short link that answers 301 or 302; old addresses, Turkish letters and `*` patterns are short link names: `docs/memory/platform/short-link-redirects.md`.
- CSV on the short link list: `docs/memory/platform/short-link-csv.md`.
- Automatic redirect (built, #1403): a renamed page's old name is offered as a 301 short link to the page (checked by default), every language prefix covered: `docs/memory/platform/page-rename-redirect.md`. Folders have no address, so a folder rename needs none.
- 404 log (built, #1420): `not_found_log`, one row per missing address with Create redirect, 90 days, a row cap, bots out and verified crawlers counted apart: `docs/memory/platform/not-found-log.md`.
- Per-page SEO extras in page settings: canonical override (URL), optional `noarchive`/`max-snippet` directives. The `custom_jsonld` editor with validation is in the visual designer since #582 (`pg_page_custom_jsonld_check()`); the classic `edit_page.php` can reuse the same check.
- `llms.txt`: generated like `robots.txt` (`get_robots.txt.php` pattern) from site name, description and the sitemap's top pages; editable extra text. robots.txt gains a UI section "AI crawlers" that writes `User-agent` blocks for training, agent and search bots consistent with the WAF toggles, plus the `Content-Signal` line.
- API: short links carry `redirect_code` and `is_pattern` (step 1); `/seo/not-found` read.

## What remains?
1. Done (#699): redirects as short link options (11.0.12, router, 404-time patterns, classic screens, File Manager server side, API).
2. Done (#699): CSV import and export on the short link list.
3. Done (#799): File Manager short link window offers the answer, with the classic screens' name and address checks.
4. Done (#1403): the rename 301 in the designer save and the page edit screen.
5. Done (#1420): the 404 log, its screen and retention.
6. Done (#802): page canonical address, noarchive and max-snippet in the classic page screen and the designer, drawn by `get_page_content()` (11.0.22, `docs/memory/visitors/page-canonical-robots-directives.md`).
7. Done (#739): `llms.txt`, robots AI section, Content-Signal (`docs/memory/visitors/ai-crawlers-llms-txt.md`, 11.0.21).
8. API `/seo/not-found` (S).

## Open questions?
- Should redirects apply before the WAF? No: after the WAF, like every page.
- Should a page whose canonical names another address leave sitemap.xml (as noindex does), with an SEO check for it? Not decided; today it stays listed.
- Should a rename keep the addresses below the page too (`/old-catalog/<product>`, `/old-blog/<record>`)? Not decided: a `old/*` URL pattern loses the language prefix, a page destination with captures is new router behaviour. Today only the page's own address is kept.

## Where to start?
- Step 8: `GET /seo/not-found` over `not_found_log` (`docs/memory/platform/not-found-log.md`), next to `seo.issues` in `includes/api/schema.php` and `includes/api/resources/seo.php`.
