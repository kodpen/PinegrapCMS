---
name: design-template-shop-comments
description: Shop templates (store, boutique, bookshop 1.2.0, hello 2.6.0) on the Comments widget: post_comments, conversation_replies, the reply e-mail card before the System region, why explicit trees
---

## Which comments widgets do the store, the boutique, the bookshop and hello ship?
- `post_comments` on blog_post, under the post (journal entry) view: oldest, featured `first`, threaded, max_depth 2, 20 a page; Reply, Share, the follow box.
- `conversation_replies` on conversation, under the conversation view: oldest, featured `none`, flat, all on one page; no Reply, Share or follow box (two sides: the sender gets the submitter e-mail of each answer, the staff the page's staff mail; a designed record page counts as `form item view` in `add_comment.php`). The record view's `submitter_only` access hides the thread and refuses a post for anyone else ([[comments-widget-contract]]).
- `email_comment` (`$mail_comment_tree`, flat, featured none) on email_reply, right before the System region, which stays ([[comments-widget-email]]).
- `product_reviews`: starter tree ([[comments-widget-product-page]]); unchanged in the three shops, new in hello.
- The fixed `comments_block` region is gone; `$comments_section($name, $widget_key)` places the widget in the reading column.
- Store 1.2.0 (#4467), boutique 1.2.0 (#4482), bookshop 1.2.0, hello 2.6.0 ([[design-template-hello]]). Decision: owner, 2026-11-01 (`workflow/decisions-made-product-16.md`).

## Why explicit trees?
- The e-mail card must be one: a `'tree' => 'starter'` widget drops its page from `pg_design_template_preview_pages()`, and email_reply is in the installer's e-mail row ([[design-template-forum-replies]]).
- The threads use each template's `$comment_thread($w)`, with the template's look (store and bookshop: `bg-body-tertiary rounded-4` cards, `btn-primary`; boutique: square `p-4 bg-body-tertiary` panels, a bordered form, `btn-dark`; hello: white `shadow-sm` cards, [[design-template-hello]]) and its own words (blog: "Leave a comment", "Post comment"; conversation: "Write a reply", "Send reply"); the starter says "Add comment" on both. Labels stay `Comment` and `reply` ([[design-template-comment-labels]]).
- They do not follow later changes of `pg_comments_default_tree()`: the test pins actions, values, flags and draws.

## What does the e-mail card keep?
- The View or Reply button bound to `__comment_url` with no flag over it, so `pg_comments_email_drew()` takes the draw ([[comments-widget-email-design]]); colours inline (a `var()` keeps its fallback); no form, control or Messages node.

## What happens to sites made from an older version?
- Nothing: rows are never reused; a 1.1.x site (hello 2.5.x) keeps the block on blog_post and conversation. Make designable there gives the editor's widget, not this layout.

## How was it checked?
- `tests/design_template_shop_comments_test.php`, per template in its table: no fixed block in any tree, the four comments widgets and their options, each thread under its view, flags of the thread mode, pure draws for a member, a visitor and staff, the e-mail card's order, link, draw and installer preview.
- No running instance.
