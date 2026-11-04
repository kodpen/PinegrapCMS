---
name: activitypub-moderation
description: Fediverse blocks (ap_blocks) by actor or domain: what a block removes, how values are stored, the comment screen's buttons, the erasure path shared with a Delete of the actor.
module: activitypub_moderation
---

## What does a block do?
- `pg_ap_block_add($kind, $value)` writes an `ap_blocks` row (`INSERT IGNORE` on kind and value) and calls `pg_ap_forget_actor()` for every known actor it covers: its `ap_followers` row, its `ap_reactions` and its comments go. A domain block also drops the `ap_outbox` rows still queued for inboxes on that domain. Blocking again writes no second row and removes what arrived since.
- From then on the inbox refuses the actor before its key is fetched (`pg_ap_blocked()`, `platform/activitypub-inbox.md`). Lifting a block (`pg_ap_block_remove($id)`) brings nothing back.
- A domain covers its subdomains (`eu.spam.example` under `spam.example`), never a look-alike (`notspam.example`, `spam.example.org`). Known actors are read with a `LIKE` prefilter and decided by `pg_ap_block_actors()` (pure).

## How is a value stored?
- `pg_ap_block_normalize()`: an actor is its http(s) address without the fragment; a domain is a lowercase host with at least one dot, typed as a host, an address on it or a handle `@user@host` (port and path dropped). Anything else is '' and not saved.

## How are a remote actor's comments removed?
- `pg_ap_forget_comments($actor_id)`: the `comments` rows with that `remote_actor`, their `notifications`, then `pg_comment_refresh_record_info()` per thread and `pg_page_cache_mark_dirty()` (the inbox is no signed-in POST). `pg_ap_forget_actor()` calls it when loaded, so `ap_inbox.php` loads `activitypub_moderation.php` (which loads the inbox module): a Delete of the actor, the remote erasure request, removes the same things as a block.

## Where can a block be made?
- `edit_comment.php`: a comment with `remote_actor` shows "From the Fediverse" and, for Manager and above (`role < 3`; a block is site-wide while comment editing follows folder access), "Block Account" and "Block Server" (`submit_block` = actor / domain). Both confirm, then return to the comment list with a notice; the comment itself is gone with the rest.
- The Fediverse settings screen (AP6) lists `pg_ap_block_list()` and adds and lifts blocks with the same functions.
