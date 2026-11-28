---
name: workspace-link-preview-read
description: The general job's read of a Workspace message's link preview: the pass and its hourly cap, the own-page card, the addresses refused, redirects, time and size limits, charset, the fields kept
---

## How does the general job's pass run?
- `job.php`, Workspace on: `ws_link_preview_due()` (one small read), then `ws_link_preview_run()`: rows waiting over a day close as `none` with no `read_at`, so they never count in the cap (`ws_link_preview_expire_sql()`); at most 5 rows a pass, 60 reads an hour for the site (`read_at` of the last hour), a pass stops after 20 s.
- A row is claimed (`ws_link_preview_claim_sql()`: `read_at` set while `waiting`, unclaimed, still for the address the pass read) so overlapping runs never read it twice and an edit's new address waits for the next pass. A message deleted or edited to another address since is skipped.

## What does a link to the site's own page show?
- A link to the site's own page (`ws_link_preview_own_page()` against `HOSTNAME_SETTING` and `PATH`, www. ignored) takes its card from the `page` row (title else name, meta description) only for a page every visitor opens (`ws_link_preview_own_card()`, #11408) or, for the home page, `TITLE`; the site name is `ORGANIZATION_NAME`, else `TITLE`, else the host. No call. Such a link gets its row even on a local host (`ws_link_preview_wanted()`).
- Public folder, not archived, not binned; a private, member, registration or guest page gives nothing: the card reaches every reader of the channel, guests included.

## How is any other address read?
- Anything else: `ws_link_preview_fetch()` over `api_http_request()` (the resolved address is checked and is the one called), plain http allowed, redirects walked here, at most 3, each checked again.
- `ws_link_preview_url_allowed()` refuses credentials in the address, a port other than 80/443, an address inside the network, a dotless or local name (`.local`, `.internal`, `.lan`…) and a numeric last label. A host in its own letters (`çiçek.com.tr`) is checked and called in its xn-- form (`ws_link_preview_ascii_url()`, intl).
- Only `text/html` or XHTML; 8 s for the whole read, redirects included (`ws_link_preview_time_left()`: each hop gets what is left); the body is read up to `</head>` or 256 KB (the limit reached with no `</head>`: nothing). No cURL: nothing.
- Fields (`ws_link_preview_fields()`): og:title, twitter:title, else `<title>`; og:description, description, twitter:description; og:site_name, else the host without www. The page's charset is honoured, by any name mbstring takes (`ws_link_preview_charset()`). `ws_link_preview_cut()` keeps them plain (no tags, entities decoded, control and direction marks out) and cuts at 300 / 500 / 100 characters; they are escaped where drawn. No title: `none`.

Row, switch: `workspace-link-preview.md`. Tests: `tests/workspace_link_preview_test.php`.
