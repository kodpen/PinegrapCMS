---
name: web-vitals-ai-visibility
description: Plan (owner 2026-11-02): real-user web vitals per page from a beacon into an hourly rollup, and an AI visibility view (AI crawler fetches, AI referrals); steps WV1-WV6
---

## What is wrong now?
- The site measures nothing in the visitor's browser. `page_view.php` (the signed beacon that counts a visit served from the page cache, `visitors/page-view-beacon.md`) carries no timing; the visitor tables hold views, sources and orders but no loading figure. An operator learns that a page got slow from Google's field report weeks later, or never; an agency cannot show a client a before-and-after.
- The firewall tells AI user fetchers (ChatGPT-User, Claude-User, Perplexity-User) and AI search indexers apart from training crawlers and verifies them by IP range (`waf/waf-ai-bots-ranges.md`), robots.txt and llms.txt carry the operator's AI policy (`visitors/ai-crawlers-llms-txt.md`), but no screen says which AI fetched which page, how often, or whether a visit came from an AI assistant's answer. Competitors sell this as an "AI visibility" dashboard.

## What is proposed?
- One more beacon from the page: `pg_vitals.js`, a few hundred bytes printed by `output.php` for public pages, measures LCP, INP and CLS with `PerformanceObserver` and sends them once with `navigator.sendBeacon()` on `visibilitychange` to `page_view.php` (the same signed address, a `v=` part). No library, no third-party script, no cookie: the request carries the page id, the signed token, the three figures, the connection kind and the device width class.
- An hourly rollup, never raw rows (`visitors/high-volume-counter-rules.md`): `page_vitals_hourly`, key `sha1(date|hour|page_id|device)`, with counts and sums of the three figures and their good/needs-work/poor buckets, written with one `INSERT ... ON DUPLICATE KEY UPDATE`. From it: p75 per page and site, the share of good pages, the slowest pages, a trend.
- AI crawler figures: every request the firewall classed as an AI fetcher or AI indexer (verified) is counted into `ai_fetch_daily`, key `sha1(date|bot|page_id)`, written by the firewall's own counter path. Visits from AI assistants come from the referrer rule (the `ai` channel that `plans/visitors-overview.md` V4 defines; until it lands, this plan ships the same host list in `pg_visitor_ai_hosts()` and V4 absorbs it).
- Screens: a "Speed" and an "AI visibility" section for the Visitors overview (`visitors.php`, PR #4890) when it exists, the API routes `reports/vitals` and `reports/ai-visibility` from the start, and the new dashboard's `perf` card reading the site p75 instead of the server response time alone.

## Which decisions bind it?
- Privacy: the figures are per page and hour, never per visitor; no identifier, no IP, nothing stored before consent is needed because nothing is personal (checked against `platform/cookie-consent-catalogue.md`).
- The beacon is off while the page is in the designer, in Preview, in prototype play and for signed-in staff (their pages carry the panel bar).
- Both tables are derived data: wrong buckets are truncated and rebuilt from nothing (the next hours fill), never fixed in place (`visitors/visitor-rollup-consistency.md`).

## Which questions are open? (architect, before WV1 and WV3)
1. The two tables as named above, in the open version's migration (a schema question by the fixed rules); recommended as written, retention 400 days by the general job.
2. Whether the AI fetch counter also keeps unverified AI user agents under a separate `unverified` bot name (recommended yes, as a count only).

## Where does it start?
- WV1 and WV3 touch different files and start together after their questions; WV2 and WV4 follow. Read `visitors/README.md` key rules, `visitors/page-view-beacon.md`, `visitors/visitor-rollup-tables.md`, `waf/waf-ai-bots-ranges.md`, `includes/fn/seo.php` from `init_tracking()`. Steps: `web-vitals-ai-visibility-steps.md`, `-steps-2.md`.
