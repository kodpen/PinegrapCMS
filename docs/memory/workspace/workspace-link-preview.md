---
name: workspace-link-preview
description: A Workspace message's link preview (#9843): the ws_link_previews row, when it is written, the switch; pointers to the job's read, the card and the API
---

## What is kept, and where?
- `ws_link_previews` (step 11.0.220), one row per message: `message_id` primary key, `channel_id`, `url`, `title`, `description`, `site_name`, `state` (`waiting`, `shown`, `none`, `removed`), `created_at`, `read_at` (unix seconds). Code: `includes/workspace/link_preview.php`, loaded by the bootstrap.
- `shown` has the card's fields; `none` was read with nothing to show and is never read again; `removed` is the writer's "Remove preview".

## When is a row written or removed?
- `ws_message_send()` writes `waiting` for the first http/https address of the text (`ws_link_preview_first_url()`: not inside `code` or a ``` block, not inside a tag like `<#order:5>`, sentence punctuation left out, at most 2048 bytes). No network call in the request.
- `ws_message_edit()`: the same first address keeps the row (a removed card stays removed); another one replaces it as `waiting`; none deletes it. `ws_message_delete()` deletes it; a discussion purged with its messages (`threads.php`) deletes its rows by `channel_id`.

## How does the general job read it?
- Its pass, the own-page card and the read of a web address (refused addresses, redirects, limits, charset): `workspace-link-preview-read.md`.

## Where is the card?
- `workspace-link-preview-card.md`; over the API `api/workspace-api-link-preview.md`.

## Where is it switched off?
- Site Settings, Channels, mail and voice, card "Link previews" (`pgset-ws-link-preview`), switch "Show a card under a message's web address", on by default; saved with the pane's Save by anyone with the Workspace settings right (`ws_link_preview_settings_save()`, the marker field `ws_link_preview_card`). Stored as `config_text` `ws_link_preview_off` = `1`; no column.
- Off, or a demonstration site (`pg_demo()`): no row is written, the job reads nothing (`ws_link_preview_on()`), and no card is drawn anywhere; rows already there stay.

Tests: `tests/workspace_link_preview_test.php`. Asked by the owner in poll 54 (`workflow/decisions-made-workspace-35.md`).
