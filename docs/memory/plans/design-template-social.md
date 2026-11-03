---
name: design-template-social
description: Plan for the Social Community template (pinegrap-social): a photo community on member-sent Posts and Profiles collections, comments, tags, saved posts; steps 1-8, likes held (#6296)
---

## What is being built?
- A photo community: latest posts, explore by topic and tag, a page per post with comments, people and a profile per member, a member's own pages, moderation, guidelines, about, FAQ, help; standard pages (blog "Community News"), e-mails. Id `pinegrap-social`, 1.0.0, Bootstrap 5.3.8. No shop.
- Samples once in `parts.php`: six topics, eight fictional members, two posts each.

## How does it work without new product behaviour?
- A post is a record of the Posts form, a collection sent by a member: photo (image kind, `posts` folder), alt text, caption, topic, tags, place. Office: status Live/Hidden (`status is not equal to Hidden` on lists and view), featured. Lists `?topic=`, `tags contains ?tag=` ([[collection-lists]]); the post page is the record view, comments on its Comments widget (`post_comments`).
- A profile is a record of the Profiles form, sent once by the member, edited by them ("Allow submitter to edit…"): display name, bio, city, interests (a topic), avatar, website. The member page lists that profile and the posts by `?member=submitter_name` (the forum's member archive).
- Saved posts: the thread's follow box; Saved lists watched posts ([[form-list-viewer-filter]]).
- A report: a Reports record (`?post=form_item_view`), moderators' notes on its page; staff hide a post by its status.
- Not built (new behaviour, #6296): likes, following members, member-to-member messages (the conversation stays member to site), stories, video.

## Why parts in a folder?
- Steps 1-7 write `includes/design_templates/pinegrap-social/` (not globbed); step 8's main file includes them in one scope. Classes `psc-`, field ids `sc-`. Trap: no part may return into `$topics`, `$members` or `$social_posts`; the blog's card is `$news_card`.
- `member` is the public profile, `my_profile` the member's own; `profile` stays the account page.

## Which steps are there?
- 1 Done (#6297). Data and shared parts: `parts.php` (builders, samples; account bar, header, footer, join band, avatar, post, profile and topic cards, page shells, form fields, comment thread, staff bar); test `design_template_social_test.php`.
- 2 Public pages: `pages_public.php` (home, topics, about, guidelines, FAQ, help, privacy, terms).
- 3 Profiles: `profiles.php` (`my_profile` with the collection and samples, `people`, `member` with the member's posts, `staff_members`).
- 4 Posts: `posts.php` (`post_new` with the collection and samples, `explore`, `tag`, `post`, `my_posts`, `saved`, home lists).
- 5 Done (#6340). Reports and mail: `mail.php`, `reports.php` (`report`, `report_view`, `my_reports`, `staff_reports`, `staff_report` with `report_notes`, `staff_posts`, `email_reply`, `email_comment`, notify e-mails); test `design_template_social_reports_test.php`.
- 6 Standard: `standard.php` (contact, inbox, conversation, newsletter, blog "Community News", blog post, search, error); test `…_standard_test.php`.
- 7 Account and staff: `account.php` (sign-in pages, My Account, staff area, `blog_new`, account e-mails); test `…_account_test.php`.
- 8 Assembly: `pinegrap-social.php` (order 78, icon `bi-camera`, look and palette, contact group `newsletter`, folders `posts` and `avatars`, `psc-` User Styles, search map), shared tests' lists, `designer/design-template-social.md`, plan deleted; PR body names `[NEW] Added the Social Community design template`.
- L Held: likes and follows (1.1.0), only after #6296 decides general features for them.

## Which questions are open?
- #6296: likes, follows, member messages: not built, or general features. Steps 1-8 do not wait.

## Where to start?
- `pinegrap-market/`, `pinegrap-hotel/`, `pinegrap-forum.php` (member posts and archive, reports, live rule), [[design-templates-file-format]], [[design-template-threads]], [[collection-template-refs]]. Steps 2-7 may run in parallel after step 1; the next free step is the lowest one not marked done here.
