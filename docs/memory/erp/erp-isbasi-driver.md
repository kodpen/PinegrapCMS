---
name: erp-isbasi-driver
description: Logo Isbasi e-document driver (edoc/isbasi.php): source document rule, fields and environments, login flow and session, request helper, success envelope.
---

## What is the source of truth for the İşbaşı driver? (migration 4.62)
- `docs/_isbasi_api_notlari.md`: every endpoint address and field name comes from it. If something is needed that the document lacks, it goes onto the document's "Doğrulanmadı" (unverified) list; no guess enters the code.

## What are the fields and environments? (`edoc/isbasi.php`)
- Fields `api_key`, `username`, `password`, `environment` (`select`: `live` | `test`, default LIVE: an unanswered setup is live, not a silent test), `entry_url` (empty = the environment's address).
- Addresses: `ERP_EDOC_ISBASI_ENTRY_URL` `https://isbasimw.isbasi.com`; `ERP_EDOC_ISBASI_TEST_URL` `https://soho-isbasi-mwv2-test.logo-paas.com`; test panel `ERP_EDOC_ISBASI_TEST_PANEL_URL` (`soho-isbasi-uiv2-test.logo-paas.com`).
- In the test environment the returned `baseUrl` is IGNORED.
- `erp_edoc_isbasi_environment()` → `is_test`, `label`; registry gate `erp_edoc_environment($code)`. The settings card and invoice card print a yellow "Test ortamı" badge and `erp_edoc_invoice_send()`'s notification adds the same note.
- The fingerprint includes the environment (a changed environment drops the session). Field type `select` (`options` = value → label) renders as `<select>` in `prep.php`.
- Credentials are entered by Erdal; the agent never fills password/key boxes.
- Test-environment limitation: its GİB simulation shows EVERY VKN as a taxpayer, so the e-Arşiv path cannot be tried there.

## How does login and the session work?
- Flow: `getplatform` (apiKey header, `{isonedayvalidtoken:false, username, password}` → `data.baseUrl`) → `integrationLogin` → token `accessToken|access_token|token|Token`, tenant `tenantId|…`, read flexibly (`erp_edoc_isbasi_pick`, one nesting level); if none is found the error lists the field names.
- Session lasts 23 hours; it drops when the credential fingerprint (`erp_edoc_isbasi_fingerprint`) changes; a 401 triggers one re-login.

## How is a call made?
- Every call goes through `erp_edoc_isbasi_request($method, $path, $body, $context)`; `context`: doc_type/doc_id for the log, and `content_type` / `accept` overrides (e-document endpoints want `application/json-patch+json` + `text/plain`).
- Success envelope `erp_edoc_isbasi_ok`: 2xx + `isError` false + `code` ∈ {0, 200}.

Related: docs/memory/erp/erp-isbasi-invoice-payload.md
