---
name: connections-registry
description: Registry of outside services (pg_connections_registry()): a row, the count rule, the figure (78 + 12 MCP connectors), the difference from the census's 79, adding a service
module: connections_registry
---

## What is the registry?
- One row per outside product or API the site connects to: `name`, `group` (`pg_connections_groups()`), the one `place` of its setting, a `state` reader or the reason in `no_state`, `uses` (English, a few words), and `covers`: the keys of the lists that already exist which the row stands for. Pure data in `includes/fn/connections_registry.php`; nothing reads the database or calls a service. Each row's place and reader: `connections-registry-rows.md`.
- A place is a Site Settings card `#settings/<category>/<card>`, a System tools screen, or a screen of `pg_connections_other_screens()` (only `search_console.php`). Group labels and `uses` are English source text; the screen that draws them adds their Turkish.

## What follows the registry elsewhere?
- Search (#10153): every service's name finds its place in the Site Settings search and the palette (`pg_connections_place_keys()`, `ui/settings-modal-search.md`); `search_console.php` is not in either, as before.
- Integrations › Related screens (`pg_settings_integration_links()`): Application Access and the registry's System tools screens, in System tools' order and under its gates, then Workspace Settings for its right.

## Which state readers are there?
- `pg_connections_state_kinds()`: `account` (a connected account of a provider for a purpose; the only kind that reads "needs attention", from `reauth`), `app` (an enabled `connect_apps` row), `service` (`pg_connect_service_get()`), `config` (constants set, or the first equal to `equals`), `reader` (a function answering true from stored data), `marketplace`, `edoc`, `model`, `mcp`, `notify`.
- The Connections screen reads them (`ui/connections-screen-layout.md`). A state nothing stored can tell is left out and named (`no_state`), never kept in a new table.

## What is one service, and what is not counted?
- One outside product or API the code was written for, with a place to set it up, counted once however many drivers, purposes or screens reach it: iyzico's card and redirect drivers are one row; Google Drive and Google Analytics are two products behind one Google account.
- Not counted: generic protocols (SMTP, IMAP, FTP, S3-compatible storage, iCal, RSS, Web Push, time stamps), an address the operator types (the custom model provider, any MCP server, speech to text's OpenAI-compatible address), tracking links, what runs only in the browser (Chrome's translator), and anything planned.
- The 12 ready-made MCP connectors are rows of group `mcp`, counted apart: `pg_connections_connector_count()`.

## What is the figure?
- `pg_connections_count()`: 78 services, beside 12 MCP connectors; `tests/connections_registry_test.php` holds the number, so a PR adding a service changes it there and here.
- Architect (2026-11-17, agent channel message 7524): the public figure is the registry's count, kept by a test and never typed in a text.

## How does it differ from the census's 79? (`plans/integrations-census-today.md`)
- Left out, 2: Unsplash (only a constant in `data/config.php`, no place to set it), the WordPress, WooCommerce and Shopify importers (file uploads, no call to the service).
- Added, 1: OneNote (purpose `notes`, a person's pages into Workspace notes), which the census missed.

## How is a service added?
- A row in `pg_connections_registry()` with its `covers`. The test fails while a key of a covered list has no row (payment and label drivers, `mp_providers()`, SMS, connect providers and purposes, model providers, the MCP catalogue, mail transports, address and captcha providers, cloud pickers, e-document drivers, chat notification kinds), unless `pg_connections_not_services()` names it with its reason.
- The test also fails for a place that is no card of its category nor a tool screen, and for a `reader` function that is declared nowhere.
