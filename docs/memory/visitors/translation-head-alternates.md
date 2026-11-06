---
name: translation-head-alternates
description: When a drawn page names its language versions (hreflang links) in its head: own address, none named yet, and not noindex under any language policy
---

## When does a page get hreflang alternates in its head?
- `pg_tr_render_finalize()` (`includes/translate/render.php`) asks `pg_tr_head_alternates_wanted($html, $canonical)` (`includes/translate/seo.php`): the page has an own address (`pg_page_own_url()` or its canonical link), names no hreflang link yet, and its robots meta says neither `noindex` nor `none` (`pg_tr_robots_says_noindex()`).
- Which languages are named then follows `pg_tr_index_decide()` (`translation-index-verdict.md`).

## Why not on a noindex page?
- sitemap.xml leaves `page.noindex` pages out, so they belong to no language cluster. Under the "all" policy the head still printed the source, every target and x-default on such a page and on each of its language copies, all of them noindex; under "reviewed" each copy answered `0` and none was named. One rule now for both.
- A target language drawn under the "none" policy (noindex added by the pass) names none either.

Tests: `tests/translate_index_test.php` (`test_translate_index_head_alternates_wanted`).
