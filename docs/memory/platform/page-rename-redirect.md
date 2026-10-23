---
name: page-rename-redirect
description: A renamed page's old address as a 301 short link to the page (2026.11.0): where it is offered, what is skipped, why a redirect of the page's own gives its name back
---

## What happens to the old address when a page is renamed?
- The visual designer's page settings and the classic page screen (`edit_page.php`) offer "Send visitors of the old address here (301 redirect)" under the name while it differs from the stored one, on by default. On save `pg_short_link_page_renamed()` (`includes/fn/short_link_redirects.php`) adds a short link: the old name in the stored form (`pg_short_link_path_name()`), destination the page, answer 301, link mode permanent.
- It leads to the page, not to an address, so after the next rename it still leads to the page; nothing chains.
- One link covers every language: the router takes the language prefix off before it looks for a short link, and `pg_short_link_location()` puts it back (`/en/old` -> `/en/new`).
- Who: the designer's full level (only it can rename) and role < 3 on the classic screen (short links are a manager area). The API, MCP and the AI editor send no `page_name_redirect`, so they add none.
- Folders have no address (a page's address is its name alone), so a folder rename needs nothing.

## When is no link added?
- `pg_short_link_rename_problem()`: `unchanged` when the router finds the new name for the old one (`About` -> `about`, `a b` -> `a_b`: same `pg_short_link_key()` or stored form), since the link would answer at the page's own address and loop; `name` when no short link can have the name or a `*` would make it a pattern.
- `draft`: the page was in the Drafts folder, its old address never on the site. `taken`: a page, file or link has the old name by now. `off`: the box was off. `schema`: before 11.0.12.
- The designer says what happened (`notes` in the save reply, an info toast) and warns for `name` and `taken` (`pg_short_link_page_renamed_messages()`); the classic screen goes to the page as before, the activity log has "short link (x) was created".

## Why may a page take back a name its own redirect holds?
- The router reads short links before pages: a link `old` -> page left in place once the page is named `old` again would send every visitor round in a loop. So a 301/302 link to the same page does not hold its name (`pg_short_link_not_redirect_to_page_sql()`, used by `check_name_availability()`'s `ignore_redirects_to_page` and `pg_designer_page_name_taken_elsewhere($name, $page_id)`), and the rename deletes it (the new name as it came and in the stored form).
- The File Manager's rename, mass edit and the API's page write do not pass the option: there a name held by such a link is refused as before.

## What does it not cover?
- Addresses below the page (`/old-catalog/<product>`, `/old-blog/<record>`): only the page's own address is kept. A `old/*` pattern to `new/$1` can be added by hand; a URL destination keeps no language prefix.

Tests: `tests/short_link_redirects_test.php` (`renamed_page_*`), `tests/short_link_requests_test.php` (the screens' wiring). PR: #1403 (plan `seo-toolkit-redirects` step 4).
