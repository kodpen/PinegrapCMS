---
name: api-discovery-document
description: The credential-free discovery document (.well-known/pinegrap.json and GET integration.php/discovery): its fields and sources, where it is wired, which gates apply, device_signin and pairing
---

## What is answered?
- `{"software":"pinegrap","version","api_version","api_base","title","logo_url","language","api_enabled","device_signin","pairing","https_required","min_app_version"}`, built by the pure `api_discovery_document($facts)` (`includes/api/resources/discovery.php`) from `api_discovery_facts()`.
- Sources: `VERSION`, `api_openapi_base_url()`, `TITLE`, `pg_resolve_config_image(ORGANIZATION_LOGO)` (null without one), `api_text_language()` (the same `language` as `GET /meta`), `api_settings()` for `https_required`. `min_app_version` is the constant `API_DISCOVERY_MIN_APP_VERSION`, null until a release needs one.
- `api_enabled` is false also when the database is missing or `api_apps` is not installed yet.
- `version` is public already (`changelog.txt`, the `Pinegrap/<VERSION>` User-Agent). Nothing about people, keys, scopes or the firewall goes in.

## When is device_signin true?
- When `api_device_app_check()` would let a sign-in through: API on, `api_devices_ready()`, `api_device_app('')` active and not past `expires_at`, and the caller's address admitted by its allow list. The allow-list test is `api_ip_allowlist_admits($list, $ip)`, the boolean `api_check_ip_allowlist()` now calls.
- `pairing` = `device_signin` and a pairing table; no such table exists, so it is false.

## Where is it wired?
- `router.php`: `.well-known/pinegrap.json` beside the OAuth names, with `PG_API_ENTRY`, no session, `init.php` and the API bootstrap, then `api_discovery_well_known()`: plain JSON, never through `api_run()`, so it answers with the API off and over http.
- `integration.php/discovery`: the open route `api_discovery_routes()`, merged in `api_all_routes()` beside `api_open_routes()` and answered before credentials like `docs`. Outside `api_schema()`, so it is in neither OpenAPI nor MCP; no scope, no ceiling.
- Both send `Content-Type: application/json; charset=utf-8`, `Cache-Control: private, max-age=300` (the answer depends on the caller's address) and `Access-Control-Allow-Origin: *`.

## Which gates apply?
- `.well-known`: WAF bans and rate limits; the bot policy does not, because `waf_path_is_always_served()` already exempts every `/.well-known/` path, so `waf.php` needed no change.
- `integration.php/discovery`: the bootstrap gates (503 `api_disabled`, 403 `https_required`, 403 `address_blocked`).

## Where is it tested?
- `tests/api_discovery_test.php`. Added for issue #1641; the client side is recorded in kodpen/PinegrapMobile `docs/memory/server-api/discovery-and-base-url.md`.
