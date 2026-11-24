---
name: connections-address-merchant-page
description: Address autocomplete providers and Merchant Center on Connections (CX11g4): keys, server, account, country on their pages; the card keeps switch and provider
module: address_autocomplete, merchant_center, connections_screen
---

## Where are an address provider's key and Merchant Center's account set?
- On their pages (CX11g4 #11007, `connections-one-place.md`): `connections.php?service=google_places`, `mapbox`, `photon` (`pg_connections_screen_address_pages()`) and `merchant_center`. This closed the Ecommerce group; payment and shipping: `connections-payment-gateways-page.md`, `connections-shipping-services-page.md`.

## What does an address provider's page do?
- "Connection details": the key (password box, never drawn back, an empty box keeps it) or Photon's "Server Address", "Test Address", "Save" and "Save and try" (suggestions through this provider even while another is on, `pg_addr_suggest(…, $through)`). Posts `addr_save`.
- `pg_addr_page_decide()` (pure): key required while none is stored, https host for Photon, a test address for "Save and try". `pg_addr_page_save()` keeps the stored switch.
- "Disconnect" (`addr_remove`, `pg_addr_account_forget()`) deletes the `addr_<key>` row while one exists.

## What does the Address Autocomplete card keep and refuse?
- The switch, the Provider select and a connection block per provider (`pg_addr_settings_connection_html()`): Connected teal / Not connected gray, "Manage in Connections" / "Connect in Connections"; only the chosen one shows.
- The switch is disabled by script while the chosen provider is not connected. `pg_addr_settings_decide()` (pure) writes only a change: on needs a connected provider ("… is not connected. Connect it in Connections, then switch address autocomplete on."), Photon's row is made on first switch-on, off always saves, the others go off.

## What does Merchant Center's page do?
- Status shows the chosen Merchant Center account; "Connect a Google account" is the Status button for the purpose `merchant_center`.
- "Connection details" posts `merchant_save` to `pg_merchant_save_from_screen()`: choose the account and the country, then the account link, the data source line, the issues, Country, "Save", "Register the feed again", "Check issues now", "Stop using this account".
- The Product Feeds card (`includes/settings/commerce_merchant.php`) only shows `pg_merchant_card_connection_html()` and posts no Merchant field; `commerce.save.php` no longer saves it. The Marketplaces drawer's note links the page.

## Who may change them, and the registry?
- Who may open Site Settings › Ecommerce (`connections_commerce_may_manage()`).
- Registry: `place` `connections.php`, `found_at` `#settings/commerce/pgset-address-autocomplete` or `#settings/commerce/pgset-feeds`; states unchanged. The OpenAI feed stays on the card. No row added: `pg_connections_count()` stays 84.
- No legacy anchor for the moved field ids: no link points at them. No data step, table or column.

Tests: `tests/connections_address_merchant_test.php`, `tests/address_autocomplete_test.php`.
