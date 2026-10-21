---
name: erp-edoc-registry-and-service
description: Shared e-document helpers in edoc/registry.php (settings, session, http, mask, log) and edoc/service.php, owner of the invoice send/poll line.
---

## Which shared helpers does the registry offer? (end of `registry.php`, migration 4.62)
- `erp_edoc_settings($code)` / `erp_edoc_settings_save($code, $values)`: `erp_edoc_providers.settings` JSON; `null` deletes a key.
- `erp_edoc_session($code)` / `erp_edoc_session_save($code, $session)`: the session JSON is AES-encrypted in `settings.session_enc`; returns empty if `expires_at` has passed.
- `erp_edoc_http($method, $url, $headers, $body, $timeout)`: curl; `pg_curl_tls()` → `CURL_CA_BUNDLE`; NO insecure fallback; returns `http_code, body (array|null), raw, error, duration_ms`.
- `erp_edoc_mask($text)`: secret, token, tax number, name, e-mail, phone and address keys → `"***"`, any other 10-11 digit run → `**********`, except the value of an `id` / `detail_id` key: a provider's record ids (Paraşüt's are ten digits) stay readable (#940; until then a log line could not say which invoice or card it was about).
- `erp_edoc_log($provider, $doc_type, $doc_id, $method, $url, $result, $request)` (masked 500-character summaries, kept 30 days, 1-in-50 sweep; query-string values replaced with `…`); `erp_edoc_log_for($type, $id, $limit)`.
- `erp_edoc_invoice_party($invoice)`: the copy fields `account_*`, else the live card.
- A new driver never writes its own curl.

## What does `edoc/service.php` own?
It loads the bootstrap and is the single owner of the invoice send line:
- `erp_edoc_invoice_can_send($invoice)` → `ok, reason, provider`.
- `erp_edoc_invoice_send($id, $user_id)`: first `edoc_status='sending'`, then the driver; success → `edoc_provider`, `edoc_external_id`, `sent`, `edoc_sent_at`; failure → `error` + `edoc_error`, plus the provider and id when the driver names a record it wrote and could not take back (`external_id` in its refusal).
- `erp_edoc_invoice_poll($id)`: `gib_number`, `gib_uuid`, status; an event on status change.
- `erp_edoc_invoice_document($id, 'pdf'|'xml')`; `erp_edoc_status_labels()`.
- Send gate: active provider + `send_invoice`, `direction=sales`, status not draft/cancelled, `edoc_status` ∈ none/error/rejected, and no record the provider still holds (`erp_edoc_invoice_held_refusal()`: `error` with an id is asked after, not sent again; erp-parasut-send-failures.md).
- Questions about an existing document go to `edoc_provider`, NOT to the active provider.
- `erp_edoc_account_missing($account)` (account list, the order card's invoice gate, the automatic invoice) asks the driver's `party_missing` with the fields `erp_edoc_invoice_party()` gives for the live card. Until #911 it left out `tax_office` and `country_code`: under Paraşüt every company card read "Tax office" missing, so the order card would not issue a company's invoice.

Related: docs/memory/erp/erp-isbasi-driver.md
