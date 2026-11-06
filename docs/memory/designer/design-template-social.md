---
name: design-template-social
description: Social Community template (pinegrap-social): member photo posts and profiles as collections, comments, tags, saved posts, reports and the live rule, psc- look, tests.
---

## What does it ship?
- Public: `home` (places `home_featured_posts`, `home_latest_posts`, `home_news`, `newsletter_band`), `topics`, `about`, `guidelines`, `faq`, `help`, `privacy`, `terms`. Posts: `post_new`, `explore` (`?topic=`), `tag` (`?tag=`), `post` (record view + `post_comments`), `my_posts`, `saved`. People: `people`, `member` (`?member=submitter_name`), `my_profile`, `staff_members`. Reports: `report`, `report_view`, `my_reports`, `staff_reports`, `staff_report` (`report_notes`), `staff_posts`, the report e-mails, `email_comment`, `email_reply`. Standard pages with the blog called Community News ([[design-templates-standard-pages]]); account and staff pages, account e-mails.
- Shared: account_bar, notice, header (with the site search), member_bar, footer, join_band, staff_bar, email_header/footer. Order 78, icon `bi-camera`, look `modern-soft`, palette `lavender` (e-mail pink `#be185d` is its secondary). Contact group `newsletter` (Community Newsletter). Public folders `posts` and `avatars` (under root) hold post photos and profile pictures. Built in #6297–#6346.

## Why a main file plus a folder?
- As Marketplace ([[design-template-market]]): `pinegrap-social.php` includes `parts.php`, `mail.php`, then `pages_public.php`, `posts.php`, `profiles.php`, `reports.php`, `standard.php`, `account.php` in one scope, each return kept as `$…_part`.
- Trap: `parts.php` keeps the samples in `$topics`, `$members`, `$social_posts`; a part returning under those names overwrites them. The blog's card is `$news_card`.
- `member` is the public profile, `my_profile` the member's own, `profile` the account page.
- A static sample member card opens `{{page:people}}?query=<name>`, not `member`: `?member=` matches a record's author name, never a sample handle (#7507).

## How does it work without new product behaviour?
- A post is a Posts form record (collection, sent signed in): photo (image kind, `posts` folder), alt text, caption, topic (parts.php's titles), tags (`contains ?tag=`), place; office `status` Live/Hidden and `featured`. Every public list and the post view carry `status is not equal to Hidden` (the live rule); `staff_posts` shows all, and its rows open the record, never the post page (#7509).
- A profile: a Profiles record the member sends again; lists show the newest (`form-list-one-per-submitter`).
- A post's byline is `submitter_name` (the account's name), never the profile's `display_name`; its hint says so (#7510). Saved posts are followed comment threads ([[form-list-viewer-filter]]).
- A report is a Reports record opened with `?post=` (text, never a link); moderators' notes are `staff_report`'s comments, e-mailed to nobody.
- Not built (new behaviour, #6296): likes, following, member messages, stories, video.

## What is the psc- look?
- `psc-brand`, `psc-eyebrow`, `psc-search` (width cap), `psc-avatar`/`-lg`, `psc-thumb`, `psc-post` (feed card width cap), `psc-tile`/`-col`/`-info` (photo grid, hover count), `psc-band` (token tint), `psc-lift`; no motion under `prefers-reduced-motion`. Fonts Figtree (headings) and Inter (body). Field ids `sc-`.

## Which tests cover it?
- `design_template_social_test.php` (data, shared parts) and `…_public_`, `…_posts_`, `…_profiles_`, `…_reports_`, `…_standard_`, `…_account_test.php` load the parts; `…_assembly_test.php` the main file: pages in order, widgets and shared parts placed once, placeholders (page, folder, contact group) resolve, no page needs the shop, contact search sections, `psc-` classes equal rules, the threads (`post_comments`, `news_comments`, `conversation_replies`, `report_notes`) and the e-mail card, samples kept.
- It is in the shared collection-form (`blog_new`, `my_profile`, `post_new`, `staff_entry`), account-email, submitter-name and faded-text test lists; no form has a privacy consent box, so not in the consent list.
