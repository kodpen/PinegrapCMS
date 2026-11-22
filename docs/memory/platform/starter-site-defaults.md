---
name: starter-site-defaults
description: Starter dump defaults (empty AddThis code, no Twitter feeds, Turkish title and arrival name) and the scheduled jobs a new install switches on (search index, structure, accessibility).
---

## What sharing code do the starter sites ship?
- None: `config.social_networking_code` is `''` in both dumps, with `social_networking` 1 and the advanced type kept. `get_page_content.php` prints nothing for an empty code, so the pages look the same.
- Why: both dumps shipped an AddThis script. AddThis closed on 2023-05-31, so every page sent a request that only failed (#3330, decision #3567, PR #3581). 2026.1.12 replaced AddThis for the simple sharing type only.

## What happens on running sites?
- Step `upgrade_2026_11_0_starter_addthis()` (11.0.133) empties the code only where it is still one of the two stock scripts, byte for byte (`BINARY ... IN`). A code the owner wrote or edited stays. A second run changes nothing.
- The title and arrival names are never migrated, since an owner may have written them.

## What takes the place of the starter sites' Twitter feeds?
- Footer column `twitter-feed`: a widget headed "Bizi Takip Edin" / "Follow Us" with the theme's `social-list` of `ti-*` icons linking to `#`; the footer keeps four columns. `home-firm-twitter-feed` and `home-event-twitter-strip` are empty; the rows and layout tags stay.
- The home sets no longer load `twitterfetcher.min.js` and the dumps' `scripts.js` starts no Twitter feed; the file and its `files` row stay, unused.
- Why: Twitter no longer serves the timelines, so the regions stayed empty and every page logged a 429, a script error and CSP reports (#11055; owner decision of 2026-11-22, decisions list product part 99).
- Running sites: step `upgrade_2026_11_0_starter_twitter()` (11.0.269) changes a region only where its content is one of the six stock values, byte for byte (`BINARY`). An edited region stays; a second run changes nothing. Their styles and `scripts.js` are not migrated: with no `.tweets-feed` left nothing calls Twitter.

## Which Turkish defaults changed?
- `config.title` is `'Web Sitem'` (was `'My Website'`), and arrival date 1 (`AT-ONCE`) is named `'En kısa sürede'` (was `'Bir defada'`). Its description stays. The English dump keeps its title `'liveSite'`.
- `tests/starter_defaults_test.php` checks the dumps and the AddThis step; `tests/starter_twitter_test.php` the Twitter regions and their step. The fixture `'Bir defada'` in `tests/order_view_rows_test.php` is test data, not the dump.

## Which scheduled jobs does a new site run?
- The search index (`update_search_index`), the HTML structure check (`seo_analyze_job`) and the accessibility check (`seo_a11y_job`) through the general job: `install/index.php` sets `job_dispatch_enabled = 1` and adds the three to `job_dispatch` (`pg_search_audits_new_site_list()`: stored names kept, catalogue order, nothing doubled). The SEO score job stays off. Install step names Site Settings › Updates and maintenance › Cron Jobs.
- Only a starter-site install (`get_starter_site_language() !== ''`), both columns checked with `install_column_exists()`. No upgrade step: a running site and a restored backup keep their switch.
- Why: the dumps predate the columns and the upgrade adds them off and empty, so a new site ran none of the three until the operator found the switch (owner decision 2, `workflow/decisions-made-product-18.md`). Tests: `tests/search_audits_test.php`, `tests/starter_defaults_test.php`.
