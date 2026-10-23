---
name: erp-edoc-driver-layer
description: ERP e-document driver layer (4.61): registry gate, adding a provider, contract and capabilities, credentials, the one active provider, the ERP without a provider, endpoints never guessed.
---

## What is the entry point and how is a provider added?
- `includes/erp/edoc/registry.php` is the single gate; provider files are `edoc/<code>.php`, function prefix `erp_edoc_<code>_`.
- The provider's name does not leak into the rest of the module: code asks `erp_edoc_supports('send_invoice')` / `erp_edoc_has_capability('return')` and calls `erp_edoc_call('send_invoice', array($invoice, $lines, $options))`.
- New provider = one file + one row in `erp_edoc_drivers()`. The code (`parasut`, `isbasi`) is stored in `erp_edoc_providers.provider` and `erp_invoices.edoc_provider` and never changes once a document has gone through it.
- No provider-specific column: extra data goes to `erp_edoc_providers.settings` (JSON) or `edoc_external_id`.
- The whole operation list and the return shapes are the comment at the top of `registry.php`: besides the contract below, `cancellable`, `date_refusal`, `inbox`, `inbox_document`, `accounts`, `account`, `account_save`; `service.php` also asks `submit_invoice`, `party_missing`, `document_missing` and the registry `environment`. Screens ask `erp_edoc_has_capability()` before they offer a step, never the provider's name.

## What is the driver contract?
- `info()` (label, description, docs_url, capabilities, settings_note); `fields()` (name, label, type text|password, help, required); `ping($credentials)` → `['success','message','details']` (`details['pending']=true` = "not connecting yet, but setup is done"; the screen prints it in the info colour); `check_taxpayer($vkn)` → is_einvoice_user + aliases; `send_invoice`, `poll`, `cancel_invoice`, `fetch_document`, `send_waybill` → all `['success' => bool, 'error' => string, …]`.
- An undefined operation = unsupported.
- Capabilities (`erp_edoc_has_capability()`): `einvoice`, `earchive`, `ewaybill`, `inbox`, `accounts`, `return`, `ubl` (`fetch_document` hands back the UBL, not only the PDF; the invoice screen draws the UBL button by it). Screens ask the capability, never the provider's name.

## How are credentials and the active provider handled?
- `erp_edoc_providers.credentials_enc` ("cipher:iv", AES-256-CBC, `encrypt_string_with_iv` / `decode_ssl_keys`), never in `config` (row size: docs/memory/data/config-table-no-varchar.md). `erp_edoc_credentials_save()` writes each posted field as given (an emptied box clears it) and keeps one not posted. The Paraşüt driver declares no fields: its secrets are on the Paraşüt card (erp-parasut-connection.md).
- One active provider: the `is_active=1` row, read once per request (`erp_edoc_active()`); before 4.61 `ERP_PARASUT_ENABLED`. Two would send one document to GİB twice. A past document's questions go to its own `edoc_provider`.
- `erp_edoc_test()` pings AND writes `checked_at/check_status/check_message`; the card's "Last checked" line prints from there.

## What must the ERP do without a provider?
- Work completely: most stores never have one. A screen only a provider can fill (incoming e-invoices, account sync) gets its menu link through `pg_erp_provider_offers('inbox'|'accounts')` (`includes/fn/ecommerce.php`); a new such screen = a new branch there.

## What schema does it add?
- `erp_invoices` / `erp_waybills`: `edoc_provider`, `edoc_external_id`; `erp_edoc_queue`: `provider`, `external_job_id` (nothing processes the queue yet). The older `erp_invoices.parasut_*` columns (4.43) stay: the Paraşüt driver keeps its e-document id and job there. No new one is added.

## Where do endpoint addresses come from?
- Never guessed: from the provider's document, else onto its unverified list. İşbaşı: `docs/_isbasi_api_notlari.md` ("Doğrulanmadı"); Paraşüt: the published swagger, checked live on a demo company (erp-parasut-driver.md), unread fields marked UNVERIFIED in the driver.

Related: docs/memory/erp/erp-edoc-settings-card.md
