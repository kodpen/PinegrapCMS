---
name: activitypub-decisions
description: Why the Fediverse (ActivityPub) feature is shaped as it is: off by default, one actor, no library, what is published, remote replies as comments, WAF rules, the decided tables; where each part lives
module: activitypub
---

## What is it, and where is each part?
- The site as one Fediverse actor (owner, 2026-11-02): found as `@<handle>@<host>`, followed from Mastodon and the like, its posts delivered to followers, their replies kept as comments. Ghost ships this in the core, WordPress needs a plugin.
- Actor, WebFinger, NodeInfo, signatures: `activitypub-actor.md`. Inbox and followers: `activitypub-inbox.md`. Publishing and the queue: `activitypub-outbox.md`. Replies as comments: `activitypub-comments.md`. Blocks and erasure: `activitypub-moderation.md`. Settings › Fediverse: `ui/fediverse-settings.md`. API and personal data: `api/api-fediverse.md`.

## Which decisions bind it?
- Off by default: a site that never turns it on answers 404 at every Fediverse address, as before.
- No library: the JSON-LD is written by hand to the shapes Mastodon accepts (fixtures in `tests/fixtures/activitypub/`), signatures through OpenSSL (`pg_ap_sign()`, `pg_ap_verify()`).
- One actor per site; its name, summary and avatar fall back to the organization's.
- Published (owner): records of the forms and pages of the folders the operator chooses; never a draft, a private-folder page or a record behind a sign-in. Content goes out sanitized (`pg_sanitize_rich_text()`) with absolute addresses (`hostname-in-mail-links.md`). What was published before the switch was turned on is not sent.
- A remote reply is a comment: the comment settings, moderation, notification mails and erasure apply as to any comment; nothing from the Fediverse writes anywhere else.
- The WAF holds: the inbox has its own rate bucket (`ap_inbox`), a blocked actor or domain is refused before its key is fetched.

## Which tables were decided? (#5041, #6391)
- `ap_followers`, `ap_outbox`, `ap_blocks` and `comments.remote_actor` / `remote_id` (2026.11.0, 11.0.163). Settings and the sealed key pair went to `config_text` rows (`ap_settings`, `ap_keys`), not `config.ap_*` columns: the stock `config` row had no room (`data/config-table-no-varchar.md`).
- Likes and boosts: one `ap_reactions` row per actor, kind and object, counted with `COUNT(*)` (11.0.167; architect, `workflow/decisions-made-schema-10.md`).

## What was left out?
- Object ids (`<actor>/p/<id>`, `/r/<id>`, `/c/<id>`) are not dereferenceable; a site under a sub-path is not found by handle (WebFinger lives at the host's root); likes from site members belong to the general plan of #6296, not to `ap_reactions`.
