---
name: design-template-shop-comments
description: Store template (1.2.0) comment threads on the Comments widget: post_comments, conversation_replies, the reply e-mail card before the System region, why explicit trees, older sites
---

## Which comments widgets does the store ship?
- `post_comments` on blog_post, under the post view: oldest, featured `first`, threaded, max_depth 2, 20 a page; Reply, Share, the follow box.
- `conversation_replies` on conversation, under the conversation view: oldest, featured `none`, flat, all on one page; no Reply, Share or follow box (two sides: the sender gets the submitter e-mail of each answer, the staff the page's staff mail; a designed record page counts as `form item view` in `add_comment.php`). The record view's `submitter_only` access hides the thread and refuses a post for anyone else ([[comments-widget-contract]]).
- `email_comment` (`$mail_comment_tree`, flat, featured none) on email_reply, right before the System region, which stays ([[comments-widget-email]]).
- `product_reviews` unchanged: starter tree ([[comments-widget-product-page]]).
- The fixed `comments_block` region and its section helper are gone; `$comments_section($name, $widget_key)` places the widget in the reading column.
- Decision: owner, 2026-11-01 (`workflow/decisions-made-product-15.md`).

## Why explicit trees?
- The e-mail card must be one: a `'tree' => 'starter'` widget drops its page from `pg_design_template_preview_pages()`, and email_reply is in the installer's e-mail row ([[design-template-forum-replies]]).
- The two threads use `$comment_thread($w)`: the template's look (`bg-body-tertiary rounded-4` cards and form), its own words (blog: "Leave a comment", "Post comment"; conversation: "Write a reply", "Send reply"); the starter says "Add comment" on both. Labels stay `Comment` and `reply` ([[design-template-comment-labels]]).
- They do not follow later changes of `pg_comments_default_tree()`: the test pins actions, values, flags and draws.

## What does the e-mail card keep?
- The View or Reply button bound to `__comment_url` with no flag over it, so `pg_comments_email_drew()` takes the draw ([[comments-widget-email-design]]); inline colours with their `var()` fallback; no form, control or Messages node.

## What happens to sites made from an older version?
- Nothing: rows are never reused; a 1.1.x site keeps the block on blog_post and conversation. Make designable there gives the editor's widget, not this layout.

## How was it checked?
- `tests/design_template_shop_comments_test.php`: no fixed block in any tree, the four comments widgets and their options, each thread under its view, flags of the thread mode, pure draws for a member, a visitor and staff, the e-mail card's order, link, draw and installer preview. Boutique and bookshop join its template table when they move.
- No running instance.
