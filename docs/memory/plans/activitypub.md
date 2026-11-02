---
name: activitypub
description: Plan (owner 2026-11-02): the site as a Fediverse actor over ActivityPub: WebFinger and actor, followers, posts delivered to inboxes, replies as comments, moderation, a settings screen; steps AP1-AP8
---

## What is wrong now?
- A blog's readers follow it by RSS, e-mail (campaign profiles) or nothing. Mastodon, Threads, Pixelfed and the other Fediverse services can follow a site directly when it speaks ActivityPub; Ghost 6 ships it in the core and WordPress needs a plugin. Pinegrap answers neither `/.well-known/webfinger` nor an actor document, so a search for `@blog@example.com` finds nothing.
- The pieces exist apart: the router already answers fixed well-known names (`router.php`, the OAuth and `pinegrap.json` branches), RSA key pairs are made for DKIM (`pg_dkim_key_pair()`), secrets are sealed (`core/security-helpers-and-secret-storage.md`), outgoing HTTP goes through `pg_curl_run()`, records of a form have addresses and feeds (`designer/form-feed-item-guid-enclosure.md`), comments have a table, threads and moderation (`designer/comment-thread-rules.md`), and `pg_announce()` fires on `page.updated` and record changes.

## What is proposed?
- One actor per site (handle, name, summary, avatar from the site logo) with a key pair, answered at `/.well-known/webfinger`, `/.well-known/nodeinfo` and `/@<handle>` by content negotiation (`Accept: application/activity+json`; a browser gets the site's page). Incoming requests are verified by HTTP Signatures against the sender's public key (fetched and cached); outgoing ones are signed.
- An inbox (`ap_inbox.php`) that accepts Follow (answered with Accept), Undo, Delete, Like, Announce and Create(Note) replies; a followers table; an outbox queue that delivers one activity to each follower's shared inbox with retries and backoff, as the mail queue retries, sent by the general job.
- Published content goes out as Create(Article): records of the forms the operator chooses (the News and Magazine templates' Articles form) and pages in chosen folders; an edit sends Update, an unpublish or bin sends Delete. A reply from the Fediverse becomes a comment under the post (moderated by the comment settings, shown with the sender's name and address by the Comments widget); likes and boosts show as counts.
- Settings › Fediverse: switch, handle, profile, what is published, followers, the delivery log, blocked actors and domains.

## Which decisions bind it?
- Off by default; a site that does not turn it on answers 404 as today. No library: JSON-LD is written by hand to the shapes Mastodon accepts (tested fixtures), signatures through `openssl_sign()`.
- Content goes out sanitized (`pg_sanitize_rich_text()`), with absolute addresses (`platform/hostname-in-mail-links.md`), never a private-folder page or a draft; a record under a sign-in is never published.
- A remote reply is a comment: the comment rules, moderation, notification mails and erasure apply as to any comment; nothing from the Fediverse writes anywhere else.
- Counting and delivery obey the WAF: the inbox has its own rate bucket, a blocked domain is refused at the signature check.

## Which questions are open? (architect, before AP1 and AP2)
1. Tables `ap_followers` (actor id, inbox, shared inbox, name, avatar, followed at), `ap_outbox` (activity id, inbox, body, attempts, next at, state), `ap_blocks` (actor or domain), and `comments.remote_actor` / `comments.remote_id` for replies, in the open version's migration; recommended as written.
2. Which content may be published: records of chosen forms and pages of chosen folders (recommended), or pages only.

## Where does it start?
- AP1 alone, then AP2 and AP3 together. Read `core/README.md` and `designer/README.md` key rules, `designer/comment-thread-rules.md`, `designer/comments-widget-contract.md`, `designer/form-feed-item-guid-enclosure.md`, `core/mail-queue-permanent-errors.md`, `router.php` (the well-known branches). Steps: `activitypub-steps.md`, `-steps-2.md`.
