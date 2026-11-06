---
name: activitypub-outbox-collection
description: What the public ActivityPub outbox collection shows: each object's newest logged activity only, so withdrawn or rewritten content is not served.
module: activitypub_outbox
---

## What does the outbox collection show?
- `activitypub.php` route `outbox` → `pg_ap_outbox_collection($site, pg_ap_outbox_items(20))`. Anyone can read it, no signature.
- `pg_ap_outbox_items()` reads the newest log rows with a body (`inbox = ''`, five times the page size) and passes them to `pg_ap_outbox_visible()`, which keeps only each object's newest activity (object read from the activity id by `pg_ap_activity_key()`); ids this outbox did not write are kept as they are.

## Why not the last rows as they are?
- A Create or Update carries the Article's content as it was then. Once the page or record is taken off the site (closed folder, recycle bin, drafts, filtered out of its record page) the sweep logs a Delete, but the older Create stayed in the collection and handed its content to anyone reading the outbox; an Update left the rewritten-away text in the older Create too.
- Now a deleted object shows only its Tombstone, a rewritten one only its newest Article, a republished one only its new Create.

## Where are the tests?
- `tests/activitypub_outbox_visible_test.php`.
- The outbox itself (sweep, log, delivery): `activitypub-outbox.md`.
