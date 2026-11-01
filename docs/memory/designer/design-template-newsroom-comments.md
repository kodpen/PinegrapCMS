---
name: design-template-newsroom-comments
description: News template (1.1.0) on the Comments widget: post_comments, conversation_replies, desk_messages, the e-mail card on both comment e-mails, why explicit trees, older sites
---

## Which comments widgets does the news template ship?
- `post_comments` on blog_post, under the article view, in the article's column: oldest, featured `first`, threaded, max_depth 2, 20 a page; Reply, Share, the follow box. Reader comments stay held for an editor (page setting).
- `conversation_replies` on conversation, under the conversation view: oldest, featured `none`, flat, all on one page; no Reply, Share or follow box (two sides: the sender gets each answer, the newsroom each new message).
- `desk_messages` on submission, under `submission_view`: as the conversation, but the follow box stays: `email_desk_message` goes to the author and to every editor who follows the article ([[design-template-news-publishing]]).
- `email_comment` (`$mail_comment_tree`, flat, featured none), owned by email_reply and placed on email_reply and email_desk_message, right before the System region, which stays ([[comments-widget-email]]).
- The fixed `comments_block` region is gone; `$comments_section($name, $widget_key)` places the widget in the reading column. Decision: owner, 2026-11-01 (`workflow/decisions-made-product-16.md`): public and private threads move, comment e-mails get the card and keep the System region.

## How does it look?
- `$comment_thread($w)`: square `p-4 bg-body-tertiary border` cards and form, `btn-primary` send, filled `btn-secondary` small buttons (an outlined grey one is too faint on the plain palette's dark scheme). Words per thread (blog: "Leave a comment", "Post comment"; conversation: "Write a reply"; desk: "Write to the desk", "Send message").
- `title_tag`: the article view and `submission_view` close with their own h2 ("Comments", "Messages with the desk"), so those threads print the count as a small line; the conversation has no heading above, so its thread opens with an h2.

## Why explicit trees?
- A `'tree' => 'starter'` widget drops its page from `pg_design_template_preview_pages()`, and both comment e-mails are in the installer's e-mail row ([[design-template-forum-replies]]). Explicit trees do not follow later changes of `pg_comments_default_tree()`: the test pins actions, values, flags and draws.

## What happens to sites made from an older version?
- Nothing: rows are never reused; a 1.0.x site keeps the block on its three pages. Make designable there gives the editor's widget, not this layout. No migration.

## How was it checked?
- `tests/design_template_newsroom_comments_test.php` (table of templates): no fixed block in any tree, the four comments widgets and their options, each thread under its view, flags of the thread mode, pure draws for a member, a visitor and staff, the card's order on both e-mails, its link, draw and installer preview. `tests/design_template_news_test.php` keeps the comments alone in the article column. No running instance.
