---
name: dashboard-v2-visitmap-card
description: New dashboard Visitor map card (cards/visitmap.php, #6464): data source, home country, abroad share, bubbles by province, country fallback; visit country and CF-IPCity city
---

## Where does the card read?
- `pg_visitor_breakdown('country' | 'city', $from, $to, 100)` (`visitors/visitor-figures-module.md`): whole past days from `visitor_stats_daily`, today from raw rows. No table of its own (#4896). Period: today, 7 or 30 days (default 30); compare is not used.
- Link: `visitors.php?p=today|7d|30d`.

## Who writes a visit's country and city?
- `init_tracking()` (`includes/fn/seo.php`) inserts `country` = `pg_geo_country_code()` (edge header, web server, the site's IP list; Tor `T1` stored empty) and `city` = `pg_geo_city()` (`includes/geo.php`): Cloudflare's `CF-IPCity` only, believed only through `pg_geo_trusted_edge()`, cleaned by the pure `pg_geo_clean_city()` (valid UTF-8, no control characters, 50 characters, the column's length). There is no other city source.
- A completed order still overwrites both with its billing address (`pg_order_complete_visitor()`). Visits before this change carry no location: the card fills from the upgrade on.

## What does the card show?
- Home country: the country most located visits came from; values are resolved with `pg_sales_map_country_code()` ('TR', 'tr' and names are one country). Headline: "<period> · N visits · abroad X%", X over visits whose country is known; no abroad part when none is.
- Map when the home country has a region map (`pg_sales_map_region_maps()`, today TR) and at least one city matches a province (`pg_sales_map_match_region()`, aliases included): one bubble per province, area by visits, tip "<province>: N visits"; "Cities" bars list the matched places. Otherwise "Countries" bars and no map (decision 2: country level without Cloudflare).
- A summary rest row never becomes a bubble or a bar; it counts in the total only.
- Countries are named by `pg_geo_country_name()` (intl in the panel language, else English): the language shards hold about 10 of the 244 names, so `lang()` showed "Germany" beside "Türkiye" on dev (#6714).

## Why does a site behind Cloudflare show countries only?
- Cloudflare sends `CF-IPCity` only when the zone's Managed Transform "Add visitor location headers" (Rules › Settings) is on; it is off by default. dev.pinegrap.com had it off: `pi.php` showed `HTTP_CF_IPCOUNTRY` but no `HTTP_CF_IPCITY`. Only visits after it is turned on carry a city.
- Behind Cloudflare Tunnel the peer is `::1` or `127.0.0.1`; the header is believed only when that address is in Trusted Proxies (dev lists `::1`; the firewall screen then says the location comes from Cloudflare).

## How is it drawn?
- `pgDash.renderers.visitmap` in `pg_dash_cards_visitors.js`; the map file comes from `pgDash.mapShapes()` (exposed by `pg_dash_cards_shop.js`, one fetch a page). A bubble sits at the middle of its province's `getBBox()`; radius 1.2-4.5 % of the map width by the square root of its share. `pgDash.chartTips()` with the circles as parts gives hover and keyboard figures.
- Tests: `tests/dashboard_v2_visitmap_test.php`, `tests/geo_test.php` (city cleaning).
