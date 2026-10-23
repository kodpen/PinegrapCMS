---
name: design-template-news
description: News Portal template (pinegrap-news): what it ships, the Articles form contract, the look, ?r= addresses, sample dates, preview, platform limits.
---

## What does it ship?
- 53 pages: front page, 9 sections, Opinion, `blog`, `blog_post` (article), `author` (`?author=`), search, static and legal pages; accounts; the author desk (`blog_new`, `my_articles`, `submission`) in `contributors`; `staff*` in `private`; 11 e-mail pages.
- `contributors` is a private child of `private`. Shared: topbar, masthead, sections_nav, breaking, footer, staff_bar, email_header/footer. Tests: `tests/design_template_news_test.php`. Shapes: [[design-template-news-layout]].

## What is the Articles form contract (reused by the magazine)?
- Form `News Articles`, page `blog_new`, widget `article_form`: section (role category), title (title), summary (description), dateline, body (rich), cover_image (media), image_caption, image_credit, tags, byline, byline_bio, byline_photo, note_to_editors, rights. Office use only: status, publish_date (date and time, not required), placement, breaking, reading_time, correction.
- Statuses: Awaiting review (default), In review, Changes requested, Live on the site, Not accepted, Withdrawn. Placements: Standard, Lead Story, Featured. Breaking: No, Yes. All come from one `lang()` vocabulary at the top of the file.
- Each office control sits alone in a div with its label: a legend or help around it would stay on an author's form (only empty wrappers are pruned).
- Mail pages: `email_article_new` / `_received`, `email_desk_message` (comment mail of `submission`), `email_tip_new` / `_received`, `email_reply`; mail record views are `submitter_only`.

## How does the look ship?
- One User Styles entry: the block rules it uses plus own `pn-` rules. `--pg-primary-fg` always has a fallback; a heading-font rule covers the plain look; section colours are `--pn-cat-*`, light and dark. Fonts (in the picker): Barlow Condensed, Public Sans, Source Serif 4. No JS.
- The strip drops `pg-marquee-fade` while still, and stops at its start while a headline has keyboard focus, so the headline scrolls into view.
- Section pills: `scroll-padding-inline:40%` (Chromium leaves a partly visible pill unscrolled on focus, so it centres one outside the middle) and no fade while a pill has focus.
- Text over photos sits on the own `.pn-scrim` (≥ .72 black, lower 70%); `pg-shade-bottom` fails AA on bright photos.

## Why are article addresses `?r=`?
- `pretty_urls 0`. With it on, every panel save rebuilds the address from the headline (old links 404) and a taken headline is refused.

## Why do the sample dates drift?
- `publish_date` is set when the template file is read, `submitted_timestamp` at the form's first Publish. The breaking sample shows for about 46 hours after opening.

## Why does the installer preview repeat a loop three times?
- `_pg_tpl_preview_tree()` fills each loop three times: the lead grid fits, the featured row shows 3 of 4 (idea: `min(3, max_results)`). Cards take a sample of their own section.

## What can the platform not do yet?
- A byline link to the writer's archive; "more in this section"; offsets; share links; a status-change e-mail; author edits after sending; an empty message for an unknown writer; feeds dated by publish_date ([[design-template-news-publishing]]).
- Core a11y follow-ups: list-search icon buttons unnamed; `account_security` starter: outline-danger "log out everywhere" 3.4:1 in dark, h4/h6 right after the page's h2; comment form's watcher e-mail and `publish` controls unlabelled; the rich-text iframe has no title.

## Which owner question does it inherit?
- CKEditor's `oembed` plugin loads unpinned jQuery 1.x from ajax.googleapis.com on `/submit-an-article`, the first page with a visitor rich field ([[custom-form-rich-text]]).
