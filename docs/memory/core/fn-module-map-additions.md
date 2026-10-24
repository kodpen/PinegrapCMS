---
name: fn-module-map-additions
description: The includes/fn/ modules added in 2026.4.8 and 2026.11.0 (errors, mail_queue, backup, consent, privacy, audit, privacy_export, reviews, comments) and where each is described.
---

## Which `includes/fn/` modules were added in 2026.4.8?
- `errors`, `mail_queue`, `backup`, `consent`: [[fn-module-errors-mail-backup-consent]].

## Which were added in 2026.11.0?
- `privacy` (person token, consent log) and `audit` (`pg_audit()`): [[consent-log-and-audit-trail]].
- `privacy_export` (one person's data pack): [[personal-data-export]].
- `reviews` (who may review a product, the product rating, AggregateRating): [[product-reviews-verified]].
- `comments`: which thread a posted comment joins ([[comment-thread-verify]]), the record's comment counters ([[comments-moderation-counters]]), which name a member's comment gets (`pg_comment_member_name()`, [[designed-page-comment-names]]).

## Where does a module added later go?
- Its line goes here under its version; the kinds of function each older module holds are in [[fn-module-map]].
