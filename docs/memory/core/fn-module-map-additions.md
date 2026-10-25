---
name: fn-module-map-additions
description: The includes/fn/ modules added in 2026.4.8 and 2026.11.0 (errors to comments, widgets_comments and wishlist) and the memory file that describes each.
---

## Which `includes/fn/` modules were added in 2026.4.8?
- `errors`, `mail_queue`, `backup`, `consent`: [[fn-module-errors-mail-backup-consent]].

## Which were added in 2026.11.0?
- `privacy` (person token, consent log) and `audit` (`pg_audit()`): [[consent-log-and-audit-trail]].
- `privacy_export` (one person's data pack): [[personal-data-export]].
- `reviews` (who may review a product, the product rating, AggregateRating): [[product-reviews-verified]].
- `comments`: which thread a posted comment joins ([[comment-thread-verify]]), the record's comment counters ([[comments-moderation-counters]]), which name a member's comment gets (`pg_comment_member_name()`, [[designed-page-comment-names]]), which comment a reply may answer (`pg_comment_parent_ok()`, [[comment-replies]]), the rules a page shows a thread by (`pg_comments_*`, [[comment-thread-rules]]), the links that name one comment (`pg_comment_view_query()`, `pg_comment_return_url()`, [[comments-widget-pages]]).
- `widgets_comments`: the comments system widget, its hand-off with the classic comments block, its add-comment form, replies, watch and staff forms and pages ([[comments-widget]], [[comments-widget-form]], [[comments-widget-replies]], [[comments-widget-watch-staff]], [[comments-widget-pages]]).
- `wishlist`: a visitor's or an account's favourites, the visitor cookie, the merge at sign-in, the toggle and state endpoint (`wishlist.php`): [[wishlist]].

## Where does a module added later go?
- Its line goes here under its version; the kinds of function each older module holds are in [[fn-module-map]].
