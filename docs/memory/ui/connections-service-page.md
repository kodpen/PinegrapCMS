---
name: connections-service-page
description: A service's own page on Connections (connections.php?service=<key>, CX7): Status and Where it is used from the registry, the Connections row in Site Settings
module: connections_screen
---

## What is a service's page?
- `connections.php?service=<registry key>`, built in CX7 (plan `plans/connections-merges-build.md`) as the owner accepted it in poll 149 (`workflow/decisions-made-product-77.md`). Breadcrumb System tools › Connections › the service; heading its name, under it the group and its uses.
- Every registry service the user may see has one; `pg_connections_screen_page_id()` refuses any other key (an account provider, a hidden or unknown one) and the screen sends it back to the list.

## What does it show?
- "Status" ("Durum"): the state pill, why no state is read, and for an account reader the accounts with Connect again (the service's purpose) and Disconnect for the site's own, the last error on file, a link to the provider's drawer. Then where its settings are and "Open its settings".
- "Where it is used" ("Kullanıldığı yerler"): one row from the registry's `uses` and place, with the state, a link to the place.
- No field of a setting yet: each service's step adds "Bağlantı bilgileri" and moves its fields here (`plans/connections-merges-panel-rules.md`).

## Where does a write from it return?
- The page's hidden `connections_return` sends Check/Disconnect back to `?service=<key>`; Connect again returns there through `pg_connect_start_fields()`'s return path.

## How is Connections reached from Site Settings?
- A "Connections" ("Bağlantılar ›") row just above "System tools ›" at the end of the Site Settings list, for a manager only (`pg_settings_connections_link()` in `includes/settings/modal.php`), owner poll 149.

Tests: `tests/connections_screen_test.php`, `tests/connections_layout_test.php`, `tests/settings_system_tools_test.php`.
