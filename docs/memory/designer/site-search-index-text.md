---
name: site-search-index-text
description: What text the site search index stores for a drawn page: main content only, no cookie window, shared header/menu/footer or link addresses (#4096)
module: site_search
---

## What text does a search item hold?
`update_search_index.php` draws the page with `get_page_content()` and passes it through `pg_search_index_text()` (`includes/fn/site_search.php`, pure). All four drawn paths use it: pages, calendar events, legacy form records, designer records. It keeps:
- with a `<main>`: only the content of each top-level `<main>`; a `<header>` inside it stays (Kafe's page title sits there);
- without one: the page minus every `<header>`, `<nav>` and `<footer>`, except one inside an `<article>` or `<section>`;
- never the head, scripts, styles, comments, SVG, `<textarea>`/`<select>` or the cookie window (`div#pg-cc`);
- link text only, never the address; images give nothing; blocks are parted by a line break, spaces folded, entities read.

Title, meta description and keywords are still added by the job around this text, as before.

## Why not convert_html_to_text()?
It is the plain-text e-mail body converter (`includes/fn/mail.php`): it keeps every region and writes links as `text (url)`. The index used it, so the consent window ("Reject all"), the top strip, sign-in links, the menu, the sitemap and the footer columns were in every page's text, and a word found only there returned the whole site (#4038, fixed by #4096). Leave the mail converter as it is; index text changes go into `pg_search_index_text()`.

## Is the cookie window drawn for the index?
No. `get_page_content()` skips `pg_consent_inject()` when `UPDATE_SEARCH_INDEX` is true or `pg_seo_rendering()` is on (the SEO pass, the widget preview render), so the SEO word counts no longer read it either. Every visitor render keeps it (crawlers: `IS_BOT` branch in `consent.php`).

## When does an existing index pick it up?
At the next rebuild: the daily `update_search_index` job, or Pages → tools → "Update Search Index" in the panel. No migration step.

## Tests?
`tests/site_search_index_text_test.php`: a Kafe-like page (main only, consent, header/nav/footer, no URLs), a page without `<main>`, spacing, unclosed and look-alike tags, and source checks of the four call sites and the consent guard. The rebuild itself never ran in CI (no database).
