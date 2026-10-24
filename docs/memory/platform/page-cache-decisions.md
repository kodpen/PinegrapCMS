---
name: page-cache-decisions
description: Why Pinegrap has its own anonymous page cache and the lasting decisions behind it (PRs #1382, #1402): no CDN dependence, off by default, what is never cached, how to measure
---

## Where does it come from?
- kodpen.com, 2026-10-21: every page `Cache-Control: no-store, no-cache`, `cf-cache-status: DYNAMIC`; 0.25-0.45 s alone, 3-26 s at ~20 concurrent anonymous requests.
- Nothing was cacheable: `init.php` starts a session on every request (PHP's `nocache` limiter, a `PHPSESSID` on a cookieless answer) and pages carry per-visitor HTML (CSRF token, CAPTCHA, cart and member widgets, liveform notices, language redirect, consent, mobile theme, private pages).

## What was decided?
- A global open-source product with no dependence on Cloudflare: the cache works on every server.
- Primary path: Pinegrap's own file cache (`page-file-cache.md`), off by default, lifetime from Settings (`PAGE_CACHE_TTL`).
- Also standard `Cache-Control` / `s-maxage` / `Vary` headers and no `Set-Cookie` on cacheable anonymous GET answers, so any CDN or reverse proxy (Cloudflare, Varnish, nginx) can cache too (`page-cache-gate.md`, `page-cache-proxy-rules.md`).
- Never cached: pages with forms, cart or member widgets; private pages; personal fragments (currency, offer code, PHP regions); non-200 answers. Mobile theme and consent-choice variants bypass.
- Visitor statistics keep counting through a small signed beacon (`visitors/page-view-beacon.md`).
- No single save point exists for content, so stored pages are dropped by a generation number (`page-cache-invalidation.md`), not per page.

## How to measure?
- Before and after: `curl -w` TTFB, 20 concurrent requests (`hey` / `ab`) on `/` and a content page, queries per page (`perf_stats`). Check the PHP-FPM pool (`pm.max_children`) first.
- Per request, `X-Pinegrap-Cache` says `hit`, `stale`, `cacheable` or `bypass` (with the reason for staff).
