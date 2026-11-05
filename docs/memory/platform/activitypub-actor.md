---
name: activitypub-actor
description: The Fediverse actor: settings and keys in config_text, the addresses router.php hands to activitypub.php, the documents, HTTP Signatures (window, key cache, rotation).
module: activitypub
---

## Where do the settings and the key pair live?
- In `config_text`, not in `config` columns: the stock `config` row has no room left (`data/config-table-no-varchar.md`, `tests/innodb_test.php`), so the `config.ap_*` shape of #5041 became two rows. `ap_settings` is JSON (`enabled`, `handle`, `name`, `summary`, `icon`, `type` Person or Organization, `publish` with the chosen forms and folders), read by `pg_ap_settings()` / `pg_ap_settings_read()`. `ap_keys` is JSON with `public` (PEM) and `private` (`pg_connect_seal()` of `array('pem' => …)`), re-sealed on a key reset (`encryption-key-reseal-registry.md`).
- The pair is made on first use by `pg_ap_keys()` with `pg_dkim_key_pair()` (RSA 2048); `INSERT IGNORE` lets only the first concurrent request write it. No `ENCRYPTION_KEY` or no OpenSSL: no pair, nothing is signed.
- The tables of 2026.11.0, 11.0.163 (`upgrade_2026_11_0_activitypub()`): `ap_followers`, `ap_outbox`, `ap_blocks`, and `comments.remote_actor` / `remote_id` with `idx_remote`; `ap_reactions` in 11.0.167 (`platform/activitypub-inbox.md`).

## Which addresses answer, and how?
- `router.php` sends `/.well-known/webfinger`, `/.well-known/nodeinfo`, `/.well-known/nodeinfo/2.1`, `/@<handle>`, `/@<handle>/followers` and `/@<handle>/outbox` to `activitypub.php` with `PG_AP_ROUTE`, only while `router_activitypub_handle()` reads the feature on with a valid handle (`[A-Za-z0-9_]{1,64}`); otherwise the names go on as before (a page named `@x`, a 404). The router cannot load `functions.php`, so it reads `config_text` itself.
- `/@<handle>` is negotiated: `Accept` with `application/activity+json` (or `ld+json` with the ActivityStreams profile) gets the actor, a browser a 302 to the home page. `activitypub.php` runs with no session and answers 404 at its own address or while off.
- WebFinger and NodeInfo live at the host's root, so a site installed under a sub-path (`PATH` not `/`) is not found by handle.

## What do the documents look like?
- Actor id `<base>@<handle>`, key `#main-key`, inbox and shared inbox `<software dir>/ap_inbox.php` (AP2), `outbox` and `followers` under the actor. Name: `ap_settings.name`, else organization name, else title, else host; summary: `ap_settings.summary`, else the meta description; icon: `ap_settings.icon`, else the organization logo. Written by Settings › Fediverse (`ui/fediverse-settings.md`). Expected shapes are the fixtures `tests/fixtures/activitypub/blog_*.json`, checked against Mastodon's actor (`mastodon_actor.json`).
- Followers collection: the count only, no members. Outbox: empty until AP3 fills it.

## How are requests signed and checked?
- `pg_ap_sign($method, $url, $body, $key, $time)`: draft-cavage HTTP Signatures, rsa-sha256 over `(request-target) host date` and `digest` with a body; returns header lines, false without a key.
- `pg_ap_verify($request, $options)`: refuses no signature, an algorithm other than rsa-sha256/hs2019, unsigned `(request-target)`, host or date, a body without a matching signed Digest, a Date more than `PG_AP_SIGNATURE_WINDOW` (1 hour) from now either way, an unreadable key, a bad signature. A cached key that fails is fetched again once (rotation).
- `pg_ap_remote_key()` fetches the key id's document by a signed GET (authorized-fetch servers ask for it) through `pg_curl_run()` with `check_address`, 1 MB cap, and caches it in `data/cache/ap/` for a day. `pg_ap_key_from_document()` refuses a key whose `owner` (else the document's `id`) is on another host than the key id: before, a key served by any server could name any actor as owner and pass `pg_ap_inbox_authorized()`, so it could Follow, reply or Delete as that actor. A cached entry is read back through the same rule.
