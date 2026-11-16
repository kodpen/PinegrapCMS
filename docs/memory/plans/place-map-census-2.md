---
name: place-map-census-2
description: Census MP1 of place-map, part 2: what OpenStreetMap's own tiles allow (tile usage policy, read 2026-11-16) and the security-header change the map needs (waf_csp_policy())
---

## What do OpenStreetMap's own tiles allow?
- OSMF Tile Usage Policy (https://operations.osmfoundation.org/policies/tiles/, read 2026-11-16). Address `https://tile.openstreetmap.org/{z}/{x}/{y}.png`; plain http is refused.
- Required: the credit visible on the map (usually bottom right), never hidden behind a toggle; a valid Referer from a browser, and no Referrer-Policy that stops it; the cache headers obeyed, no `no-cache` sent.
- Forbidden: bulk download or prefetch of tiles nobody is looking at, offline use, a stripped Referer, a spoofed or generic User-Agent.
- Commercial use is not banned, but heavy use may be blocked without notice and "commercial services" are warned that access may be withdrawn at any time; there is no SLA. The policy points such users to OSM-based providers or their own tile server.
- For the plan: Leaflet loading tiles in the visitor's browser as they pan fits the policy; the tile address as a setting (X13) is the way out when a site outgrows it. A preview image on the canvas (X15) must be a still picture, not tiles fetched by the panel.

## What would another tile host change?
- A provider's raster tiles under its key are the owner's option (`place-map.md`, "What does the owner decide?"). Their terms were not read here: the host named in MP4 is read then.
- A Mapbox raster style is "a Mapbox map", so only with Mapbox tiles may a Mapbox coordinate be shown (`place-map-census.md`).

## What do the security headers need?
- The CSP is sent from `init.php` (`waf_send_csp_header()`) before the page is drawn, in Report-Only mode by default (`security_csp_mode` `report`), so it cannot know whether the page holds a map.
- The built-in policy (`waf_csp_policy()`, `waf.php`) already allows what the plan uses: `img-src 'self' data: blob: https:` lets any https tile host through; Leaflet vendored under `assets/lib/leaflet/` is `script-src 'self'` and `style-src 'self'`; markers drawn by Leaflet are inline styles, allowed; the places endpoint and the typed-address lookup are `connect-src 'self'`.
- So with the built-in policy the exact change is none: no directive, no host. X14's "widen only on a map page" has nothing to widen.
- An operator's own policy (Firewall settings, `security_csp_policy`) replaces the built-in entirely. If its `img-src` lacks `https:` or the tile host, the tiles are blocked (enforce) or reported. The pattern that exists for this is the CAPTCHA's: `pg_captcha_csp_text()` gives a line to copy and the settings warn when the site's own policy misses it.
- `Referrer-Policy: strict-origin-when-cross-origin` (`waf_send_security_headers()`) sends the site's origin to the tile host, which meets OSM's Referer rule. A map page must not tighten it to `no-referrer`.
- `Permissions-Policy: geolocation=(self)` already lets "Near me" (X11) ask the browser; nothing to change.

Next: `plans/place-map-census-3.md`.
