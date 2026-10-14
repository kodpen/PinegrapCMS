---
name: erp-edoc-registry-and-service
description: Shared e-document helpers in edoc/registry.php (settings, session, http, mask, log) and edoc/service.php, owner of the invoice send/poll line.
---

## Which shared helpers does the registry offer? (end of `registry.php`, migration 4.62)
- `erp_edoc_settings($code)` / `erp_edoc_settings_save($code, $values)`: `erp_edoc_providers.settings` JSON; `null` deletes a key.
- `erp_edoc_session($code)` / `erp_edoc_session_save($code, $session)`: the session JSON is AES-encrypted in `settings.session_enc`; returns empty if `expires_at` has passed.
- `erp_edoc_http($method, $url, $headers, $body, $timeout)`: curl; `pg_curl_tls()` → `CURL_CA_BUNDLE`; NO insecure fallback; returns `http_code, body (array|null), raw, error, duration_ms`.
- `erp_edoc_mask($text)`; `erp_edoc_log($provider, $doc_type, $doc_id, $method, $url, $result, $request)` (masked 500-character summaries, kept 30 days, 1-in-50 sweep; query-string values replaced with `…`); `erp_edoc_log_for($type, $id, $limit)`.
- `erp_edoc_invoice_party($invoice)`: the copy fields `account_*`, else the live card.
- A new driver never writes its own curl.

## What does `edoc/service.php` own?
It loads the bootstrap and is the single owner of the invoice send line:
- `erp_edoc_invoice_can_send($invoice)` → `ok, reason, provider`.
- `erp_edoc_invoice_send($id, $user_id)`: first `edoc_status='sending'`, then the driver; success → `edoc_provider`, `edoc_external_id`, `sent`, `edoc_sent_at`; failure → `error` + `edoc_error`.
- `erp_edoc_invoice_poll($id)`: `gib_number`, `gib_uuid`, status; an event on status change.
- `erp_edoc_invoice_document($id, 'pdf'|'xml')`; `erp_edoc_status_labels()`.
- Send gate: active provider + `send_invoice`, `direction=sales`, status not draft/cancelled, `edoc_status` ∈ none/error/rejected.
- Questions about an existing document go to `edoc_provider`, NOT to the active provider.

Related: docs/memory/erp/erp-isbasi-driver.md
