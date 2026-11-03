---
name: web-vitals-ai-visibility-decisions
description: Decisions behind the page speed figures and the AI visibility view (owner 2026-11-02, #5040): privacy, where the beacon is off, derived data, table shapes, unverified bots, where the screens go
---

## Why were these features built?
- The site measured nothing in the visitor's browser: an operator learned a page got slow from Google's field report weeks later, or never. And no screen said which AI fetched which page or whether a visit came from an AI assistant's answer, although the firewall tells AI bots apart (`waf/waf-ai-bots-ranges.md`).
- Built in WV1-WV5: #5397 (beacon and rollup), #5618 (figures, `reports/vitals`), #5398 (AI fetch counter), #5601 (AI figures, `reports/ai-visibility`), #5827 (screens and the dashboard tile).

## What binds the page speed figures?
- Privacy: figures per page and hour only, never per visitor; no identifier, no IP, no cookie, so no consent is needed (checked against `platform/cookie-consent-catalogue.md`). No third-party script or library: `pg_vitals.js` is a few hundred bytes of the site's own.
- The beacon is off in the designer, Preview, prototype play and for signed-in staff (`page-vitals-beacon.md`).
- No raw rows and no stored percentile: buckets and sums per hour, the p75 estimated from them (`page-vitals-figures.md`).

## What binds the AI figures?
- Only requests the firewall let through are counted; a verified AI user fetcher or AI search indexer under its catalogue name, an unverified AI user agent under the bot name `unverified`, a count only (decision 2 of #5040; `ai-fetch-counter.md`).
- AI assistant visits come from the referrer host list `pg_visitor_ai_hosts()` until the Visitors overview's `ai` channel (plans/visitors-overview V4) replaces it.

## What holds for both?
- `page_vitals_hourly` and `ai_fetch_daily` are derived data: a wrong bucket is truncated and rebuilt by the next hours, never fixed in place (`visitor-rollup-consistency.md`). Both keep 400 days, swept by the general job (decision 1 of #5040).
- The screens are `visitor_vitals.php` and `visitor_ai.php` until `visitors.php` lands; its `speed` and `ai` sections then call the same functions and the two root screens go (`ui/visitors-speed-section.md`, `ui/visitors-ai-section.md`).
