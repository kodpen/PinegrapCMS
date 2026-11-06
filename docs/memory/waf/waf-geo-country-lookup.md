---
name: waf-geo-country-lookup
description: pg_geo_country() (includes/geo.php): edge header, server module or the site's DB-IP database, trust rules, no network call on a request; how data/geo is fetched, built and kept (includes/fn/geo.php).
---

## Where does a request's country come from?
- `pg_geo_country()`, once per request: `code` (two letters, `T1`, or '' when unknown) and `source` (`cloudflare`, `cloudfront`, `server`, `database`). The first source with an answer wins:
  1. `CF-IPCountry` / `CloudFront-Viewer-Country`, each only from a peer that stands for that edge (`pg_geo_edge_sources($raw, $remote)`, cached by `pg_geo_trusted_sources()`): a Cloudflare edge address sets Cloudflare's headers (country, `CF-IPCity`); a Trusted Proxies entry naming `cf-connecting-ip` (Cloudflare Tunnel) the same; an entry saved without a header both edges; any other entry (`x-forwarded-for`, `true-client-ip`, ...) none, and the country comes from the database by the resolved address.
  - Why: such a proxy passes the visitor's own `CF-IPCountry` through, so before this a visitor behind a load balancer listed as `x-forwarded-for` chose the country the firewall's country restriction, the language redirect and visitor statistics read. A Cloudflare edge likewise passes a client `CloudFront-Viewer-Country` through.
  2. `GEOIP_COUNTRY_CODE` / `MM_COUNTRY_CODE` set by a web server module, only when no proxy stands between (`waf_client_ip()` equals `REMOTE_ADDR`): such a module places the address it was connected from.
  3. The site's own database under `data/geo/`, looked up by the real address (`pg_geo_db_lookup()`: binary search on disk, IPv6 by its /64, an IPv4-mapped address as IPv4).
- `XX`, `ZZ` and `--` mean unknown; a private, loopback or reserved address has no country.
- Readers: the firewall's country restriction and its settings card, and the language redirect (`includes/fn/language_redirect.php`).

## Which constraints does includes/geo.php keep?
- It is loaded by `waf.php` (gate `WAF_LOADED`) and reached from `router.php` first: no `functions.php`, no session, no `lang()`, and never a network call. A request is never slowed down or sent to a third party to learn its country.

## How is the database fetched and built?
- `includes/fn/geo.php`, from DB-IP's monthly "IP to Country Lite" list (CC BY 4.0; the settings note credits DB-IP). `pg_geo_db_source_urls()` tries this month's list, then last month's.
- Who fetches: the general job, only while a feature reads it (`job.php` → `pg_geo_db_due()`: `pg_geo_db_needed()`, i.e. the firewall on with country mode `allow`/`block` or the language redirect by location, and no copy of this month, and no attempt in 12 h, `attempt.txt`); and the Firewall and Languages settings screens (`pg_geo_db_refresh_for_screen()`): the "Download the location database now" button, or a save of such a setting while there is no database (at most once in 12 h). Never a visitor's request. The download goes through `pg_curl_tls()` with `pinegrap_user_agent()`.
- A failed fetch or build keeps the database that was there; fewer than 1000 records per family, ranges out of address order or a tiny download are refused.
- `meta.json` names the current pair `country-<stamp>-4.dat` / `-6.dat` (16-byte `PGGEO1` header, sorted fixed-size records). The new pair is written first and `meta.json` last by `rename()` (retried on Windows while a reader holds it); older pairs are deleted after, so a request reading the old pair keeps reading it. `refresh.lock` (`flock`, `pg_geo_db_refresh_lock()`) lets one refresh run at a time; a lock file that cannot be opened is a folder that cannot be written and the refresh fails with that reason, while only a lock held by another request answers "busy" (before #5975 every unwritable folder read as "being downloaded by another request").
- Tests: `tests/geo_test.php`.

Related: docs/memory/waf/waf-country-restriction.md
