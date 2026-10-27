---
name: waf-counter-subjects-and-endpoints
description: A private visitor address is not counted, the web root's index.php is the router, shipworks.php and workspace_guest.php are credential endpoints, the api.php allowance exempts staff only
---

## Which addresses are never counted or auto-banned?
- `waf_ip_is_counted($ip)` is the one question the rate limits, the in-flight cap and `waf_register_offence()` ask: not infrastructure (`waf_ip_is_infrastructure()`: loopback, the listed Trusted Proxies) and not private or reserved (`waf_ip_is_private_or_reserved()`: 10/8, 172.16/12, 192.168/16, fc00::/7, link-local, unspecified, documentation ranges; the same test as `pg_cf_visitor_address_counted()`). A private address is the peer of a reverse proxy, a load balancer or a container network not listed under Trusted Proxies, or a local network, shared by every visitor behind it: counted, the whole site lived on one visitor's allowance, and six refusals banned everybody. The IP lists and the signature scan still apply. `waf_ip_is_infrastructure()` keeps its meaning (the server or a proxy), which the maintenance card reads: on a local network a private address is a person's own.
- The Firewall settings screen warns beside Trusted Proxies when the operator's own request resolves to such an address (not loopback): the proxy in front of the site should be listed so visitors are told apart again.
- An intranet site whose visitors all have private addresses has no per-address limits; the lists and the scan remain.

## Why does the home page not count as the panel sign-in?
- The web root's `index.php` holds one line, a require of `router.php`, and runs when the server's directory index answers `/` (the rewrite rules leave a real directory alone). `basename(SCRIPT_NAME)` named it `index.php`, the panel sign-in, so every home page view counted on the sensitive allowance (30/min) and the ceiling on open requests (3). `waf_script_name_from($script_name, $script_filename, $software_dir)` answers `router.php` for an `index.php` outside the software directory (paths resolved and compared without case on Windows); the panel's own `pinegrap/index.php` keeps its name. `tests/waf_subjects_test.php`.

## Which endpoints joined the credential list?
- `shipworks.php` (the ShipWorks client posts one call per order with a password the sign-in throttle counts, from a warehouse's address) and `workspace_guest.php` (a guest room's page posts every few seconds with the room's token; the endpoint has its own limits and a wrong-token offence). Both counted on the 30/min sensitive allowance and the in-flight cap and were banned from them. In `waf_is_api_request()` they skip the bot policy (the attack-tool check still runs) and both counters, and keep the global limit; neither is in `waf_is_site_api_request()`, so the `api.php` allowance is not theirs (`waf-bot-policy-credential-endpoints.md`).

## Whom does the api.php allowance exempt?
- Staff (`waf_user_is_staff()`), like the sensitive limit and the in-flight cap; before, any session (`waf_user_is_authenticated()`), which a form's auto-registration hands to anyone who types an address, so a member had an unlimited, offence-free `api.php`. A member with the chat open is now a visitor with the chat open, inside the 180/min default; behind a shared address a crossing is one offence per minute (`waf-offence-fold-and-staff-address.md`).

Related: docs/memory/waf/waf-bot-policy-credential-endpoints.md, docs/memory/waf/waf-ip-subject-ipv6-64.md, docs/memory/plans/waf-legit-traffic-auto-ban.md
