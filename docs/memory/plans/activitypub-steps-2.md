---
name: activitypub-steps-2
description: Steps AP5-AP8 of plans/activitypub.md: moderation and blocks, the Fediverse settings screen, API and personal data, close-out; claim as [plan:activitypub] AP<n>
---

## Which steps are there?
- Sizes, tests and memory rules as in `activitypub-steps.md`; AP5 and AP6 run beside AP4.

- AP5 (S) Moderation. After AP2.
  - `ap_blocks` rows (actor or domain) from the comment moderation screen and the Fediverse screen; a block deletes that actor's comments and follower row and refuses its signature; erasure requests from a remote actor (Delete of an actor) handled by the same path. Memory: `platform/activitypub-inbox.md` grows.

- AP6 (S) The Fediverse screen. After AP2 and AP3.
  - Settings › Fediverse (`includes/settings/`): the switch, handle (checked against the people's names), name, summary, avatar, what is published, the followers list, the delivery log with failures and "Try again", a "Look me up" check that fetches the site's own WebFinger through the public address; pages print `<link rel="alternate" type="application/activity+json">`. Memory: `ui/fediverse-settings.md`.

- AP7 (S) API and personal data. After AP4-AP6.
  - Read-only API `GET /fediverse/followers` and `/fediverse/deliveries` with a scope (`api/api-endpoint-rules.md`); the personal data export lists a contact's remote comments by address, erasure removes them. Memory: `api/api-fediverse.md`.

- AP8 (S) Close-out. After AP1-AP7.
  - PR body names `[NEW] Added ActivityPub: the site can be followed from Mastodon and other Fediverse services, posts reach followers and their replies become comments`; decisions to `platform/`; the two plan files and the line in `feature-plans-2026-11.md` deleted; `php tools/memory_index.php`.
