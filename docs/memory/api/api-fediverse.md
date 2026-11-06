---
name: api-fediverse
description: GET /fediverse/followers and /fediverse/deliveries (fediverse:read): shapes, paging, the state filter, who may hold the scope; the personal data export and erasure of a Fediverse account
---

## Which endpoints are there?
- `includes/api/resources/fediverse.php`, routes `fediverse.followers` and `fediverse.deliveries` in `api_schema()`, objects `FediverseFollower` / `FediverseDelivery`. Read only: blocks and "Try again" stay on Settings › Fediverse (`ui/fediverse-settings.md`).
- Both page by id, newest first (`api_cursor_encode()`), `limit` 1-250. 503 `service_unavailable` before the 2026.11.0 tables (`pg_ap_outbox_ready()`). The ActivityPub modules load only in these handlers (`api_fediverse_load()`).
- Follower: id, actor, name and avatar (null when the server sent none), server (the actor's host), followed_at.
- Delivery: the `ap_outbox` rows with an inbox (the outbox's own log rows are left out), `state` filter queued / sent / failed; type and object read from the activity id by `pg_ap_delivery_view()` (null for one it cannot read), `next_attempt_at` only while queued. No error text: the table keeps none.

## Who may hold `fediverse:read`?
- Group `fediverse` in `api_scope_groups()`, read only; owner ceiling roles 0-2 (`api_owner_scopes()`), the roles that open the settings screen. Not in an assistant's key unless an administrator adds it.

## How does a Fediverse account reach the personal data screens?
- `privacy_export.php` and `privacy_erase.php` take an optional "Fediverse account" (the actor's address, `fediverse`, normalised by `pg_privacy_normalize_actor()`: http(s), fragment dropped, no credentials, 255 at most; a `@user@host` handle is refused, the comment screen shows the address). The e-mail box is no longer required: either finds the person.
- The seed carries `actors` (`pg_privacy_export_seed()`, kept by `pg_privacy_export_merge()`, counted by `pg_privacy_export_is_empty()`); it ties to no user or contact. Export: the `comments` section also holds the rows whose `remote_actor` is one of them; the document's `person` lists `actors`.
- Erasure: `remote_comments` in `pg_privacy_erase_tables()` (table `comments`, `remote_actor` => `actors`, delete), applied by `_pg_privacy_erase_apply_remote_comments()` through `pg_ap_forget_actor()`: comments with their notifications and counters, and the follower row and reactions too. Only when the account has a comment does the run reach it.

## Where are the tests?
- `tests/api_fediverse_test.php` (presenters, routes, scope), `tests/privacy_export_test.php` (seed, actor address), `tests/privacy_erase_test.php` (the pinned tables). Nothing was run against a database.
