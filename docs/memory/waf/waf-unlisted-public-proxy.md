---
name: waf-unlisted-public-proxy
description: A public proxy Trusted Proxies does not list: the finding, the staff-only state file, the Add this proxy to Trusted Proxies box, the System Status tile, not counted while it stands (#9326)
---

## What is found?
- `waf_unlisted_proxy_finding($server, $trusted, $edges)` (pure): the peer (`REMOTE_ADDR`) is a public address, not on Trusted Proxies and not a Cloudflare edge, and the request names a client through X-Forwarded-For (its last entry) or, without it, a vendor header (`waf_proxy_headers()`), and that client is another public address. A private client (an office forward proxy naming a desk) or the peer itself finds nothing.
- Why: behind an unlisted public proxy every visitor is one address, so the first ban shut out the whole site (#9125). A private peer was already left uncounted (`waf-counter-subjects-and-endpoints.md`).

## Whose finding is believed?
- Only the operator's: anyone can send the headers, and a believed visitor would exempt their own address. The Trusted Proxies card reads it for the request that opens it; `waf_unlisted_proxy_record()` in `waf_run('init')` (before the off switch: a fact, not a decision) keeps it from a signed-in staff request (`waf_user_is_staff()`, asked only when the state would change).
- State: `data/temp/waf_unlisted_proxy.json`, peer => header, first, last. `waf_unlisted_proxy_next()` (pure) records a peer, refreshes it at most hourly, keeps eight; a staff request from a recorded peer without a finding forgets it (that address is a person). `waf_unlisted_proxy_standing()` drops a peer unseen for 30 days (`waf_unlisted_proxy_ttl()`) or listed since. A write also deletes the System Status cache.

## What does it change?
- `waf_ip_is_counted()` is false for a standing peer (`waf_ip_is_unlisted_proxy()`): no rate limits, in-flight cap, offences or automatic bans, as for a private peer. The IP lists and the signature scan still apply.

## Where is it shown?
- Settings > Firewall > Trusted Proxies: a warning naming the peers and the box "Add this proxy to Trusted Proxies" (`waf_trust_unlisted_proxy`).
- The entries are `<peer> x-forwarded-for`, or the vendor header when only that was sent (`waf_unlisted_proxy_offer()`). `firewall.save.php` works the entries out again from the request and the state, never from the post, adds them to the list before `waf_proxy_list_on_save()` and logs "The proxy ... was added to Trusted Proxies from the unlisted proxy warning".
- The text says to list a CDN's published ranges too: it moves between addresses. Unlike the Secure Mode offer (`platform/secure-mode-proxy-offer.md`), a public peer is offered here, because the firewall reads the visitor through it.
- Dashboard, System Status: the job "Unlisted Proxy" (short label "Proxy", warning, not scored) with one row per peer, linking to the Trusted Proxies card.
- Tests: `tests/waf_unlisted_proxy_test.php`.

Related: docs/memory/waf/waf-trusted-proxy-headers.md, docs/memory/waf/waf-counter-subjects-and-endpoints.md
