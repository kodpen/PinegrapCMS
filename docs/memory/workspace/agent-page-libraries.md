---
name: agent-page-libraries
description: The site's libraries a live page names (Chart.js first, #8830): the list, how a call names one, how live_page.php puts the site's own copy in, never an address
---

## How does a page use a library?
- `libraries` (a list of names, at most 5) on create and edit; left out on an edit, the version before's stay. Only names in `ws_page_libraries()` (`chartjs`: Chart.js 4.4.7, `assets/lib/chartjs/`), else 422 on `libraries`. Kept in the version's document; GET answers `libraries`. Built in #8830.
- `live_page.php` puts the site's own copy in ahead of the page (`ws_page_with_libraries()`): an inline `<script>` right after the doctype. A source holding `</script` or `<!--` is left out. The frame allows no script from any host, so a page never loads one from an address.
- Another library: its file under `assets/lib/`, a row in `ws_page_libraries()`; `tests/workspace_agent_page_test.php` checks it.
