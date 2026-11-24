---
name: bug-hunt-areas-redirects-urls
description: The bug hunt of redirects and URL handling (2026-11-24, hunt 22): the three faults fixed, the findings left for a decision, what was read clean
---

## Which area was hunted?
- Hunt 22, redirects-urls: the short link list as redirects (`includes/fn/short_link_redirects.php`), `router.php` (short link lookup, language directory, old `/pages/` addresses, file redirect), the redirects `get_page.php` answers (main host, a record's list address), `pg_safe_redirect_path()`, `go()`, the automatic language redirect, the 404 log's Create redirect.
- Asked for: loops and chains, an open redirect through a parameter, a redirect that shadows a live page, trailing-slash and letter-case twins, a changed address left dead, query strings kept or lost.

## What did the hunt's PR fix?
- A short link with the answer "as always" (0) and an address destination rebuilt the address from `parse_url()` in `router.php`: the port and user part of a full address were lost (`https://shop.example.com:8443/x` went to the default port) and non-ASCII went into `Location` unencoded. It now asks `pg_short_link_location()` as a 301 link does, still without the visitor's query string.
- `/en/pages/about` (the old address form) was sent to `/about`, out of the visitor's language; it now keeps `LANGUAGE_PATH`.
- A record's list address (`/en/blog/<name>`) answered 301 to `/blog-post/<name>` without the language directory (`pg_sw_route_record_address()`); it is built under `LANGUAGE_PATH`.
- Tests: `tests/router_redirects_test.php`.

## What was left for a decision (PR body findings)?
- A short link named like an address below a live page (`blog/post` while page `blog` lists the record `post`, or `catalog/<product>`) takes that address from the live item: the router reads short links before pages and `check_name_availability()` checks only the whole name. Wanted for imported old addresses, so whether to refuse or warn is a product question.
- A product's or a record's changed address name leaves the old address dead (a 404); only a page rename offers a 301 (`platform/page-rename-redirect.md`).

## What was read clean?
- Loop and chain checks: self, through other links, more than 10 hops, language prefix, trailing slash, case (collation key), query string in the destination, a pattern's sample walk; the 404-time pattern refuses an address that leads back.
- Open redirect: `pg_safe_redirect_path()` (control characters, `//`, `/\`), `go()` (foreign host refused, prefix-sharing host refused), `$n` in a host refused, captures encoded per segment.
- Twins: `/About` and `/about/` draw the page, the canonical link names the stored page name; a short link is found as asked or in its stored form.
- Query strings: a 301/302 link keeps the visitor's after the destination's own and before the fragment, tracking code first; answer 0 drops it (as documented); the language and main-host redirects keep it.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/platform/short-link-redirects.md
