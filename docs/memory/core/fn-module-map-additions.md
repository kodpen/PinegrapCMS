---
name: fn-module-map-additions
description: The includes/fn/ modules added in 2026.4.8 and 2026.11.0 (errors, wishlist, link_resolve, prototype_links, widgets_wishlist and others) and the memory file that describes each.
---

## Which `includes/fn/` modules were added in 2026.4.8?
- `errors`, `mail_queue`, `backup`, `consent`: [[fn-module-errors-mail-backup-consent]].

## Which were added in 2026.11.0?
- `privacy` (person token, consent log) and `audit` (`pg_audit()`): [[consent-log-and-audit-trail]].
- `privacy_export` (one person's data pack): [[personal-data-export]].
- `widgets_conditions` (value conditions of widget nodes, `pg_sw_cond_*`): [[widget-conditions]]; `widgets_finish` (the finish pass every widget render goes through, `pg_sw_finish_*`): [[widget-finish-pass]]; `widgets_fill` (the typed fill, text filters and date formats after a token, `pg_sw_fill_*`): [[widget-fill-filters]]; `widgets_lists` (lists inside a record: sources, spans, published rows, the pass stage, `pg_sw_list_*`): [[widget-lists]]; `widgets_attrs` (a record's values in an element's attributes, `pg_sw_attr_*`): [[widget-attr-bindings]]. List sources `widgets_lists_product`, `_calendar`, `_order`: [[widget-lists-product]], [[widget-lists-calendar]], [[widget-lists-order]].
- `reviews` (who may review a product, the product rating, AggregateRating): [[product-reviews-verified]].
- `comments`: which thread a posted comment joins ([[comment-thread-verify]]), the record's comment counters ([[comments-moderation-counters]]), which name a member's comment gets (`pg_comment_member_name()`, [[designed-page-comment-names]]), which comment a reply may answer (`pg_comment_parent_ok()`, [[comment-replies]]), the rules a page shows a thread by (`pg_comments_*`, [[comment-thread-rules]]), the links that name one comment (`pg_comment_view_query()`, `pg_comment_return_url()`, [[comments-widget-pages]]).
- `widgets_comments`: the comments system widget, its hand-off with the classic comments block, its add-comment form, replies, watch and staff forms and pages ([[comments-widget]], [[comments-widget-form]], [[comments-widget-replies]], [[comments-widget-watch-staff]], [[comments-widget-pages]]).
- `widgets_comments_recent`: the comments widget's recent mode, a list of the newest comments of a few source pages: options, source decision, gates written into SQL, statements, merge, draw ([[comments-widget-recent]], [[comments-widget-recent-reads]]).
- `lang_files`: language files kept as sorted shards: the shard of a key, the exact file text, adding keys, the three-way merge, shard checks, atomic writes ([[lang-files]]).
- `wishlist`: a visitor's or an account's favourites, the visitor cookie, the merge at sign-in, the toggle and state endpoint (`wishlist.php`): [[wishlist]].
- `link_resolve` (what an href leads to, pure, with the SEO name maps) and `prototype` (loaded by prototype.php only): [[designer-prototype-viewer]].
- `prototype_links` (share links; loaded where needed, by prototype.php before init.php too): [[designer-prototype-share-links]].
- `widgets_wishlist`: the Favourites system widget, the visitor's saved products with add to cart and remove: [[wishlist-widget]].
- `stock_notify`: back-in-stock requests, the `inventory.changed` listener that mails them, the request endpoint (`stock_notify.php`): [[back-in-stock-notices]].
- `designer_vocab` (the Bootstrap vocabulary of the designer's smart completion; loaded by its build tool and test only): [[designer-bootstrap-vocabulary]].
- `widgets_shipping_address`, `widgets_shipping_method`: the classic multi-step checkout's steps as system widgets (address and arrival, shipping method): [[shipping-address-widget]], [[shipping-method-widget]].
- `a11y` (the accessibility report's WCAG checks and its nightly pass; loaded by `seo_a11y_job.php`, `view_accessibility.php` and its test): [[accessibility-report]].

## Where does a module added later go?
- Its line goes here under its version; the kinds of function each older module holds are in [[fn-module-map]].
