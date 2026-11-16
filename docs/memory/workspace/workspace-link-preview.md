---
name: workspace-link-preview
description: A Workspace message's link preview (LP1, #9843): the ws_link_previews row per message, the general job's one read of the first web address, its refusals and caps, the Link previews switch
---

## What is kept, and where?
- `ws_link_previews` (step 11.0.220), one row per message: `message_id` primary key, `channel_id`, `url`, `title`, `description`, `site_name`, `state` (`waiting`, `shown`, `none`, `removed`), `created_at`, `read_at` (unix seconds). Code: `includes/workspace/link_preview.php`, loaded by the bootstrap.
- `shown` has the card's fields; `none` was read with nothing to show and is never read again; `removed` is LP2's "Remove preview". The card itself is LP2.

## When is a row written or removed?
- `ws_message_send()` writes `waiting` for the first http/https address of the text (`ws_link_preview_first_url()`: not inside `code` or a ``` block, not inside a tag like `<#order:5>`, sentence punctuation left out, at most 2048 bytes). No network call in the request.
- `ws_message_edit()`: the same first address keeps the row (a removed card stays removed); another one replaces it as `waiting`; none deletes it. `ws_message_delete()` deletes it; a discussion purged with its messages (`threads.php`) deletes its rows by `channel_id`.

## How does the general job read it?
- `job.php`, Workspace on: `ws_link_preview_due()` (one small read), then `ws_link_preview_run()`: rows waiting over a day close as `none`; at most 5 rows a pass, 60 reads an hour for the site (`read_at` of the last hour), a pass stops after 20 s. A row is claimed (`read_at` set while `waiting`) so overlapping runs never read it twice; a message deleted or edited to another address since is skipped.
- A link to the site's own page (`ws_link_preview_own_page()` against `HOSTNAME_SETTING` and `PATH`, www. ignored) takes its card from the `page` row (title else name, meta description; a binned page shows nothing) or, for the home page, `TITLE`; the site name is `ORGANIZATION_NAME`, else `TITLE`, else the host. No call.
- Anything else: `ws_link_preview_fetch()` over `api_http_request()` (the resolved address is checked and is the one called), plain http allowed, redirects walked here, at most 3, each checked again.
- `ws_link_preview_url_allowed()` refuses credentials in the address, a port other than 80/443, an address inside the network, a dotless or local name (`.local`, `.internal`, `.lan`…) and a numeric last label. Only `text/html` or XHTML; 8 s for the whole answer; the body is read up to `</head>` or 256 KB (the limit reached with no `</head>`: nothing). No cURL: nothing.
- Fields (`ws_link_preview_fields()`): og:title, twitter:title, else `<title>`; og:description, description, twitter:description; og:site_name, else the host without www. The page's charset is honoured. `ws_link_preview_cut()` keeps them plain (no tags, entities decoded, control and direction marks out) and cuts at 300 / 500 / 100 characters; they are escaped where drawn. No title: `none`.

## Where is it switched off?
- Site Settings, Channels, mail and voice, card "Link previews" (`pgset-ws-link-preview`), switch "Show a card under a message's web address", on by default; saved with the pane's Save by anyone with the Workspace settings right (`ws_link_preview_settings_save()`, the marker field `ws_link_preview_card`). Stored as `config_text` `ws_link_preview_off` = `1`; no column.
- Off, or a demonstration site (`pg_demo()`): no row is written, the job reads nothing (`ws_link_preview_on()`), and LP2 draws no card; rows already there stay.

Tests: `tests/workspace_link_preview_test.php`. Plan: `plans/link-preview.md`.
