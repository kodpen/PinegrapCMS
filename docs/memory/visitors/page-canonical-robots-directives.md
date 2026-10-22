---
name: page-canonical-robots-directives
description: A page's own canonical address and noarchive / max-snippet (page.canonical_url, noarchive, max_snippet; 11.0.22, #802): storage, where it applies, languages, editors, PHP/JS twin.
---

## What is stored?
- `page.canonical_url` (TEXT NULL; '' or NULL = automatic): a full http(s) address on any host, or a path starting with one `/`, resolved against `URL_SCHEME . HOSTNAME_SETTING` when drawn so a host change keeps it. `pg_page_canonical_url_check()`: trimmed, at most 2000 bytes, no white space, control characters or `"<>\`, no `//host`, http/https with a host.
- `page.noarchive` (TINYINT) and `page.max_snippet` (INT NULL): NULL no directive, -1 no limit, 0 no text snippet, 1 to `pg_page_max_snippet_limit()` (10000).
- `pg_page_seo_extras_ready()` probes all three columns by name.

## When does the override apply?
- In `get_page_content()`, before Open Graph, only when the automatic canonical is the page's bare address (`PATH` for the home page, `PATH` + name otherwise), the type is not `error`, no record or product was resolved (`$pg_blog_form_id`, `$pg_og_sw_product_image_name`), the item is not missing and the status is 200 (or none, CLI). Items, numbered or searched lists and error answers keep the automatic address: one override for all would make every item a copy.
- It replaces `<link rel="canonical">` and `og:url` only; JSON-LD urls, share buttons and search-section anchors keep the page's own address.
- `pg_page_canonical_href()` percent-encodes non-ASCII after the host; a stored value that no longer passes the check is ignored.

- Designed list pages keep applied URL-filter params, and then `page_number` > 1, in the automatic canonical; a list without an address value keeps the bare one (`designer/form-list-url-filters.md`).

## What do the site languages see?
- `pg_tr_render_finalize()` reads the canonical link to know the page (alternates, index verdict). When the head names another address, `get_page_content()` keeps the own one in `pg_page_own_url()` (reset on every draw that is not an e-mail) and the language pass asks there first. A same-host override gets the language prefix like any link; another host is left alone.

## How are the robots directives written?
- `pg_page_robots_content()`: noindex wins and says only `noindex, follow|nofollow`; otherwise `noarchive`, `max-snippet:N`; '' writes no tag. For every address the page draws, items included; spliced before `</head>` when the placeholder is gone, like noindex.

## Who edits them, and where?
- `edit_page.php` (classic pages; a visual page redirects to the designer), SEO card. Hidden `seo_extras=1` says the form carried the three; a refused value goes back as typed (`mark_error` + `assign_field_value`) and nothing is written.
- Visual designer: Settings > Search Engines > Indexing (`designer_screen.php`). Keys `page_canonical_url`, `page_noarchive`, `page_max_snippet` are in `_PG_PAGE_FIELDS` and every page literal (a missing key is read back as '' and wipes the value). `pg_designer_save_page()` refuses in its dry run and writes through `$c_extra`, which now takes NULL, only when sent. Every role that may edit the title.
- JS twin: `_sdCanonicalCheck()` / `_sdMaxSnippetCheck()` mirror the PHP checks; change both together.
- Unicode: both trim Unicode white space and zero-width characters (U+00A0, U+FEFF, U+200B…) at the ends and refuse `\p{Z}` / `\p{Cf}` inside (`chars`); a port above 65535 or not a number is `scheme` in both. `pg_page_canonical_href()` re-checks a stored value. Before PR #1112 (issue #861) `…/urun` + U+00A0 printed as `…/urun%C2%A0`. Tests compare PHP and JS on one list.
- Copied by duplicate page, duplicate design and the designer's tab copies; part of a revision's settings.

## What is not covered yet?
- `add_page.php`, the file manager's page window, the external API and MCP do not show the fields.
- sitemap.xml still lists a page whose canonical names another address; no SEO check reports it.

Tests: `tests/seo_page_canonical_robots_test.php`.
