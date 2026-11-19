---
name: erp-isbasi-driver
description: Logo İşbaşı e-document driver (edoc/isbasi.php): source document, fields and environments, login without getplatform, session and quota, request helper, success envelope, test limits.
---

## What is the source of truth for the İşbaşı driver? (migration 4.62)
- `docs/_isbasi_api_notlari.md`: every endpoint address and field name comes from it (the Swagger file plus what the test environment answered). What it lacks goes onto its "Doğrulanmadı" (unverified) list; no guess enters the code.
- Capabilities: `einvoice`, `earchive`, `inbox`, `accounts`; no `return`, no `ewaybill`.

## What are the fields and environments?
- Fields `api_key`, `username`, `password`, `tenant_id` (optional: pins one company of a user who has several; the login is refused when İşbaşı opens another), `environment` (`select`: `live` | `test`, default live: an unanswered setup is live, not a silent test), `entry_url` (empty = the environment's address; a pasted panel address becomes its API address, `uiv2` → `mwv2`, `erp_edoc_isbasi_base_url()`).
- Addresses: `ERP_EDOC_ISBASI_ENTRY_URL` `https://isbasimw.isbasi.com`; `ERP_EDOC_ISBASI_TEST_URL` `https://soho-isbasi-mwv2-test.logo-paas.com`; test panel `ERP_EDOC_ISBASI_TEST_PANEL_URL`.
- `erp_edoc_isbasi_environment()` → `is_test`, `label`; registry gate `erp_edoc_environment($code)`. The settings card and the invoice card print a yellow "Test ortamı" badge and the send notice adds the same note.
- Credentials are entered by the owner on the settings card; nobody else types keys or passwords there. They are kept on the provider row, never in a file.

## How does login and the session work?
- No `getplatform` call: an integration key gets 403 there ("API yetkilendirilmedi", test and live), so the address Logo gives, or the environment's, is the base. `POST {base}/api/v1.0/user/integrationLogin` (`apiKey` header; `tenantId` in the body when pinned) → `accessToken`, `tenantId`, `userId`, read flexibly (`erp_edoc_isbasi_pick`, one nesting level); when no token is found the error lists the field names.
- The session is kept encrypted (`erp_edoc_providers.settings.session_enc`) for 23 hours; it drops when the credential fingerprint (`erp_edoc_isbasi_fingerprint`, environment and tenant included) changes; a 401 triggers one fresh login and retry.
- Quota: 3000 reads and 7000 writes a month, with no counter screen. The fewest requests per document; a status question is a button, never a loop.

## How is a call made?
- Every call goes through `erp_edoc_isbasi_request($method, $path, $body, $context)`; `context`: doc_type/doc_id for the log, and `content_type` / `accept` overrides (e-document endpoints want `application/json-patch+json` + `text/plain`).
- Success envelope `erp_edoc_isbasi_ok`: 2xx + `isError` false + `code` ∈ {0, 200}. The one exception is the hand-over to GİB (erp-isbasi-invoice-payload.md).

## What can the test environment show?
- Since 2026-11-17 it tells a taxpayer from a non-taxpayer: `gibUser` answers 200 with `pkList` `null` for a number that is no e-Invoice user, and that buyer gets an e-Arşiv (series SBX; internet sale INX) that is signed and can be cancelled on the panel. Before, every VKN was listed as an e-Invoice user and e-Arşiv stayed "HENÜZ İŞLENMEDİ".
- The tenant is shared with other integrators: their later-dated documents can trip the date order, and an e-Fatura may come back "Hatalı (Sistemdeki kesinti s…)" when the test GİB side is down (seen on other integrators' documents too, 2026-11-17).
- A sales return cannot be saved there (erp-isbasi-sales-return.md). Findings per date: `docs/_isbasi_api_notlari.md`.

Related: docs/memory/erp/erp-isbasi-invoice-payload.md
