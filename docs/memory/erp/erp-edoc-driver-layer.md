---
name: erp-edoc-driver-layer
description: ERP e-document driver layer (migration 4.61): registry gate, adding a provider, driver contract, encrypted credentials, active provider, test ping, schema.
---

## What is the entry point and how is a provider added?
- `includes/erp/edoc/registry.php` is the single gate; provider files are `edoc/<code>.php`, function prefix `erp_edoc_<code>_`.
- The provider name does not appear in the rest of the module: sending code asks `erp_edoc_supports('send_invoice')` and calls `erp_edoc_call('send_invoice', array($invoice, $lines, $options))`.
- New provider = one file + one row in `erp_edoc_drivers()`. The code (`parasut`, `isbasi`) is stored in `erp_edoc_providers.provider` and `erp_invoices.edoc_provider`, and never changes once a document has passed.

## What is the driver contract?
- `info()` (label, description, docs_url, capabilities, settings_note); `fields()` (name, label, type text|password, help, required); `ping($credentials)` → `['success','message','details']` (`details['pending']=true` = "not connecting yet, but setup is done"; the screen prints it in the info colour); `check_taxpayer($vkn)` → is_einvoice_user + aliases; `send_invoice`, `poll`, `cancel_invoice`, `fetch_document`, `send_waybill` → all `['success' => bool, 'error' => string, …]`.
- An undefined operation = unsupported.

## How are credentials and the active provider handled?
- Credentials: `erp_edoc_providers.credentials_enc` ("cipher:iv", AES-256-CBC, `encrypt_string_with_iv` / `decode_ssl_keys`, `ENCRYPTION_KEY`). The Paraşüt driver declares NO fields; it uses the `PARASUT_*` constants and `parasut_get_token()` (the phase -1 card is the source). A password-type field sent empty keeps the stored value (`erp_edoc_credentials_save`). Credentials are NOT written to `config` (row size, see docs/memory/data/config-table-no-varchar.md).
- Active provider = the row with `is_active=1`, read once per request (`erp_edoc_active()`); without 4.61, `ERP_PARASUT_ENABLED` is the backward-compatible fallback. Only one active: two providers would send the same document to GİB twice.
- `erp_edoc_test()` calls ping AND writes the result to the row (`checked_at/check_status/check_message`); the settings card's "Son kontrol" line prints from there.

## What schema does it add?
- `erp_invoices` / `erp_waybills`: `edoc_provider`, `edoc_external_id`; `erp_edoc_queue`: `provider`, `external_job_id`. `parasut_*` columns stay.
- No provider-specific column is ever opened; a provider keeps its extra data in `erp_edoc_providers.settings` (JSON) or in `edoc_external_id`.

## What is known about provider endpoints?
- Endpoint addresses are never guessed. The İşbaşı driver was written from its document; for Paraşüt `apidocs.parasut.com` is open, sandbox/credentials awaited. Phase 3 work is now "fill the Paraşüt driver".

Related: docs/memory/erp/erp-edoc-settings-card.md
