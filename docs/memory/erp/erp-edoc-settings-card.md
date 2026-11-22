---
name: erp-edoc-settings-card
description: The E-Invoice card (pgset-invoice, Site Settings › Accounting): e-Document Provider choice (only a connected one), Test the connection, the owner decision to keep it there
---

## Where is the e-document settings card?
- Site Settings › Accounting › `pgset-invoice` (E-Invoice). `includes/settings/prep.php` builds `$output_erp_edoc_options` (radio `edoc_provider` = `''|parasut|isbasi`, `collapse-switcher` + `data-bs-target="#edoc_fields_<code>"`); `includes/settings/accounting.php` prints it; `accounting.save.php` saves it with the dashboard's single Save.
- If the table is missing (`waf_table_has_column('erp_edoc_providers','provider')`) the block is not printed.

## Where are the credentials typed?
- On the provider's page of Connections since #11030 (`ui/connections-accounting-page.md`): Paraşüt's application and grant, Logo İşbaşı's boxes, Disconnect. The card neither draws nor writes them.
- Under each provider (`#edoc_fields_<code>`) the card shows `pg_edoc_card_connection_html()`: "<name> connection", Connected / Not connected, "Manage in Connections" / "Connect in Connections".

## What does Save do with the provider choice?
- `erp_edoc_pick_decide()` (pure, tested): Off is always taken, an unchanged choice stays, another provider is taken only while `erp_edoc_connected()`; else the warning "… is not connected, so it was not made the e-document provider. Connect it on its page of Connections, then pick it here." and the provider in use stays. A change calls `erp_edoc_activate` + `log_activity`.
- `edoc_provider` missing or posted as an array leaves the provider as it is (PR #476; it used to switch it off).
- "Enable Parasut Configuration" (the order screen's Create Invoice and E-Shipment) is switched on only while Paraşüt is connected or renewable; else a warning and it stays off. Switching it off is always taken. Its Invoice Settings (identity number field, Default Product/Service ID, Default Warehouse ID) stay on the card.

## How does "Test the connection" work?
- `get_erp_edoc_test.php` (POST, same form via `formaction`, new tab; `validate_erp_access(..., 'settings')` + token). The card posts no credential box, so it runs `erp_edoc_test()` with what is stored on the provider's page, and the "Last checked" line records the answer. A Paraşüt failure adds "The Paraşüt connection is set on its page of Connections."
- The endpoint still lays typed `edoc_<code>_<field>` boxes over the stored ones without saving; nothing posts them since #11030.

## What does the card show when the test or Connect is refused?
- `get_erp_edoc_test.php` and `parasut_connect.php` answer a `format=json` post before `validate_user()`: no session → 401 "Your session has expired. Reload the page and sign in again.", a token failing `hash_equals()` → 403 "Your session expired. Reload the page and try again.", both as `{tone:'danger', icon:'bi-x-circle', lines:[…]}`. The HTML path (direct post) and `pg_post_body_discarded()` are unchanged.
- The `button.pg-edoc-test` handler (`backend.src.js`) reads the JSON body whatever the status; a redirect or a body without `lines` prints `lang('An error occurred.')`. Before, it printed "HTTP Unexpected token '<'…" (`tests/bell_and_edoc_refusal_test.php`).

## Owner decision: where must the card stay?
- There is NO card in `erp_settings.php`; the Satıcı Bilgileri card has only a note ("… is picked on the E-Invoice card of the accounting settings; its credentials are on its page of Connections.") with a link to `pgset-invoice`.
- Do not put the card back into `erp_settings.php`: keeping ERP-owned settings on the Site Settings dashboard is an owner decision.

Related: docs/memory/erp/erp-edoc-driver-layer.md
