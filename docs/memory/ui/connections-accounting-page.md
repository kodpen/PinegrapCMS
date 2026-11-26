---
name: connections-accounting-page
description: Paraşüt and Logo İşbaşı on Connections (CX11h): Connect, Save, Save and try, Disconnect on their pages; the E-Invoice card picks only a connected provider
module: connections_screen, parasut
---

## Where is an e-document provider's connection set?
- On its page (CX11h #11030, `connections-one-place.md`): `connections.php?service=parasut`, `?service=isbasi`. The list is `pg_connections_screen_accounting_pages()`; each key is the driver code (`erp_edoc_drivers()`). Built by `connections_edoc_page()` in `connections.php`.
- Gate: who may open Site Settings › Accounting (`connections_accounting_may_manage()`, `$can['accounting']` in `pg_connections_screen_page_cards()`); the write checks the same.

## What does Paraşüt's page hold?
- "Connection details" (#11694): "A client from the Developer Portal" (`connections_parasut_devhub_part()`): `connections_app_form()` (Client ID, Client secret, Redirect address with Copy and `redirect_help`, Save), then with a saved client "Connect", a form to `connect.php` back to this page (`erp/erp-parasut-devhub-connection.md`).
- Then `parasut_page_fields_html()`: the fold "A client from before the Developer Portal" (the three steps, user name and password), open only while the site's token is of that kind; "Advanced" (company number); "Save" (`erp/erp-parasut-connect-card.md`).
- Save posts `parasut_save`: `parasut_page_save()` writes client id, user name, company number (empty keeps the saved one), the secret and password only when typed, then exchanges a code left in the box, as Connect does.
- Status: the connection line (`parasut_connection_line()`: "Paraşüt is connected. · e-mail · company" for a portal connection), Last checked, a test-environment pill, and "Disconnect" (`parasut_remove` → `parasut_disconnect()`: ends the portal connection, drops the older token, secret and password; client ids, company number and user name stay).
- Where it is used: the ERP's e-documents (the provider picked on the card) and the order screen's "Create Invoice" and "E-Shipment" (the "Enable Parasut Configuration" switch).

## What does Logo İşbaşı's page hold?
- The driver's own boxes (`connections_edoc_fields()`): text, select, password drawn empty with "Stored. Leave empty to keep it." and a "Remove the saved value" tick. Buttons "Save" and "Save and try" (`edoc_save`; try runs `erp_edoc_test()`, which writes Last checked).
- `erp_edoc_page_post()` (pure) reads the post as the card did before; `erp_edoc_page_missing()` (pure) lists required fields empty after the merge: each is marked "… is required." and nothing is stored.
- "Disconnect" (`edoc_remove` → `erp_edoc_credentials_forget()`): empties `credentials_enc`; the row, last check and the provider choice stay.

## What does the E-Invoice card keep?
- The e-Document Provider radio, Test the connection, the GİB hand-over switch, "Enable Parasut Configuration" with Invoice Settings (identity number field, Default Product/Service ID, Default Warehouse ID). It stores no credential and exchanges no code (`erp/erp-edoc-settings-card.md`).
- Per provider and for Paraşüt's switch a block `pg_edoc_card_connection_html()` (`#pgsub-edoc-<code>-connection`): "<name> connection", Connected teal / Not connected gray, "Manage in Connections" / "Connect in Connections".
- Connected: `erp_edoc_connected()`: a driver with `erp_edoc_<code>_connected()` says (Paraşüt: a working or renewable token), else every required field stored.

## Registry and data
- `parasut`, `isbasi`: `place` `connections.php`, `found_at` `#settings/accounting/pgset-invoice`; `pg_connections_count()` unchanged. No data step, table or column: the same `config` columns, `parasut_credentials_enc`, the sealed token and `erp_edoc_providers` rows.

Tests: `tests/connections_accounting_test.php`, `tests/settings_accounting_test.php`, `tests/parasut_connection_card_test.php`.
