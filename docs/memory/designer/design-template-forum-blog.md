---
name: design-template-forum-blog
description: Forum template 1.3.0: the standard blog pages in the forum's look (blog, blog_post, blog_new, home_posts), the Blog Posts form and its samples, the post comments thread
---

## What does the blog ship?
- `blog` (public): `blog_list` (10 a page, search over title, summary, content) beside a named aside, "More from the community". Its lead is the oldest sample's summary.
- `blog_post` (public, out of the sitemap): the `blog_post` view (`title_field` title, a not-found state with an h1, the record toolbar, cover, rich text in `.pf-post .pf-measure`, an h2 Comments under `record_found`), then `post_comments` in `.pf-replies d-print-none`, then a sticky aside.
- `blog_new` (private, staff bar, breadcrumb to Moderation): the Blog Posts form (`collection` 1, confirmation to the blog, `share_image_field` cover_image, four `sample_records`).
- `home_posts`: the three newest posts in a `pf-vanish` band "From the blog" after the featured guides.
- Links: masthead bar and drawer, footer Community column, staff bar and dashboard shortcut (New Blog Post), a staff note. Decision: owner, 2026-11-01 (`workflow/decisions-made-product-16.md`).

## How are the lists built?
- `$post_pub` holds the shared list settings; `$post_cards` draws `ul.row > li.col` cards (`$post_card`: cover, relative date, a stretched title link, the summary). A `div.row` grid would take the search box and page links in as half-width columns (`_split_widget_tree`).

## How do the comments work?
- `post_comments`: `$reply_thread($post_words)`, oldest, featured first, threaded, max_depth 2, 20 a page, like a topic's replies. Label `Comment`, published at once, e-mailed to followers (`{{tab:email_reply}}`) and to the site's address (moderators follow no other list of them).
- Following is "Follow this post", not the store's "Notify me": its Turkish has "haber ver", which `test_forum_turkish` keeps out.
- The new-reply e-mail's paragraph names a comment under a blog post too.

## What is unlike other templates?
- The post text is rich (wysiwyg) like topics and guides; other templates use a plain textarea.
- Not in `$search_sections`: that map holds in-page anchors, not list pages.

## How is it checked?
- `test_forum_blog_pages`, `test_forum_blog_form`, `test_forum_blog_widgets`; `post_comments` sits in `_test_forum_threads()`, so the generic thread tests and draws cover it; `test_forum_preview_html` (15 pages). No browser run: five masthead links at lg with a long site name may wrap.
