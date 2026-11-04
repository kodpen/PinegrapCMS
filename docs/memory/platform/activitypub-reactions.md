---
name: activitypub-reactions
description: Fediverse likes and boosts of a page or record shown by the Comments widget: its tokens, which objects a thread counts, page cache.
module: activitypub_comments
---

## Which tokens show them?
- Comments widget thread tokens (`pg_comments_reaction_values()`): `__comments_likes_count`, `__comments_boosts_count` (number, '' at none), `__comments_reactions_text` ("3 likes · 1 boost", '' at none). Not placed in the default tree or templates; the designer lists them. E-mails and recent-comment lists draw none.

## Where do the counts come from?
- `pg_comments_widget_reactions()` loads nothing without an `ap_settings` row; account off: none. `pg_ap_thread_objects()` names `<actor>/p/<page>` or `<actor>/r/<record>`; product, group, event threads none. `pg_ap_reaction_counts()` groups `ap_reactions` by kind; a failed query reads as none.

## When does a cached page change?
- The inbox marks the page cache dirty after a Like/Announce and its Undo.
- Stored by the inbox: `platform/activitypub-inbox.md` (one `ap_reactions` row per actor, kind and object).

## Where are the tests?
- `tests/activitypub_comments_test.php`: the thread's objects, the grouped counts, the tokens, the widget and inbox wiring. Counting against a database was not run.
