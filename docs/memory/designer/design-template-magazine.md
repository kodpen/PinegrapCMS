---
name: design-template-magazine
description: Long-Read Magazine template (pinegrap-magazine): what it ships, the contract shared with news, what it adds or drops, samples, no "haber", platform limits.
---

## What does it ship?
- 47 pages: home, 6 sections (Culture, Ideas, Travel, Food, Design & Home, Wellbeing), `blog` (Every story), `blog_post` (the story), `issues` (`?issue=`), `author` (Contributors, `?author=`), search, about, write for us, contact, newsletter, privacy, terms, accessibility, error; 12 account pages; the author desk (`blog_new`, `my_articles`, `submission`) in `contributors`; `staff`, `staff_directory`, `staff_entry` in `private`; 9 e-mail pages.
- Shared: masthead (drawer inside: not sticky), footer, staff_bar, email_header/footer. Order 80, look `editorial`, palette `sage`, thumbnail `_pg_design_thumb_magazine()`. Tests: `tests/design_template_magazine_test.php`. Shapes: [[design-template-magazine-layout]]; issues: [[design-template-magazine-issues]]; publishing: [[design-template-magazine-publishing]]; access: [[design-template-magazine-a11y]].

## Which contract does it share with the news template?
- The page keys of the desk and the story, widget `home_posts`, folders (`contributors` a private child of `private`), the e-mail keys `email_article_new` / `_received` / `email_desk_message`, the six statuses in order, the three-row live rule and the form's field names, kinds and office flags ([[design-template-news]]).
- `test_magazine_shares_the_news_contract` compares both files and names the only differences: `issue` and `pull_quote` are the magazine's, `dateline` and `breaking` the news template's. The shipped news file wins on every shared name.

## What does it add or drop?
- Adds: the office pick list `issue` and the author's `pull_quote`; placements Cover story, Editor's letter, Editors' pick; a desk list With the writer (`Changes requested`; Being edited is `In review` only); Last activity on every desk row (`newest_activity_date_and_time`, relative).
- Drops: dateline, breaking strip, tips form, page and e-mails (a pitch is a contact topic), a Standards page (About `#how-we-work`), series.
- Comment labels: `Letter` on the story (held, sign-in), `Message` on `submission` and `conversation`.

## Why do the samples carry no reading time?
- 17 stories of about 200 words (three issues of five or six, one Online only). A computed time would say "1 min read" on every cover; an invented one is false data. The "N min read" line shows once an editor fills `reading_time`.
- `publish_date` is set when the file is read (now − (4i+1) days + 2 h). Names stay in the byline fields; pull quotes are sentences of the body in English and Turkish (test 7, `test_magazine_sample_stories`, reads tr.json).

## Why does the Turkish never say "haber"?
- Many news keys translate "article" as haber. `test_magazine_turkish_never_says_news` reads every `lang()` literal of the file and fails on a tr.json value with /haber/ or on the key `Story` (Hikâye). Layer names are `Card`, `List`, `Story Row`; slugs `yazarlar-ve-editorler` and `gonderdigim-yazilar` differ from the news ones (test 24).

## Why do single-story bands carry `pm-one`?
- The DB-less preview repeats every loop three times; `.pm-one article:not(:first-of-type){display:none}` shows one cover, quote and letter there. Live lists have `max_results 1`. On the canvas each row has its own `.sd-wrap`, so every row shows (editor only).

## What can the platform not do yet?
- A byline link to the contributor archive (needs a link with a parameter); "more from this issue" (no current-record filter); per-value sitemap entries; a status-change e-mail; author edits after sending; italic web fonts (the loader asks `:wght@` only); a letter name other than the login username (7r, [[design-templates-newsroom]]); `ugc` on author links and blocking remote pictures in visitor HTML ([[custom-form-rich-text]]).
- Lists fail closed on a missing or invalid filter row since #1299 ([[form-list-filters-fail-closed]]).
