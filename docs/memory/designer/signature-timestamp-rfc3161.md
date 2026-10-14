---
name: signature-timestamp-rfc3161
description: RFC 3161 timestamping of signatures: what is stamped, storage, network call policy, config columns, settings-test pitfall, auth list, hand-written ASN.1.
---

## What is stamped and how is it stored?
- Stamp `sha256(document_hash \n image_hash \n signed_at)`; all three are printed on the receipt so a third party can recompute. Never stamp the seal: the HMAC key is ours and cannot be proven from outside. If the recipe changes, the receipt's explanation changes too.
- Store the stamp as returned (base64). Re-encoding breaks the signature on it; verification is an RFC 3161 tool's job, hence the receipt offers the file for download.
- A free stamp is real and verifiable but NOT a "qualified time stamp". Never use that title on screen or in a document; licensed providers sell it per credit and it is configured in the same setting.

## Is the network call allowed in the request flow?
- Yes, a deliberate exception (opposite of the marketplace rule: there a shipment is a state, here it is a moment). Cost is bounded by a 6-second timeout and failure never rejects the signature: store it unstamped and write to the site log.

## Where are the settings?
- In the `config` table, not `data/config.php`: four columns `signature_tsa_url` / `_auth` / `_username` / `_password`; `init.php` defines constants from them (`WAF_ENABLED` pattern, empty URL if the column is missing).
- UI: Site Ayarları -> Özellikler -> İmza Zaman Damgası. With `SIGNATURE_TSA_URL` empty no request is made; the feature ships off.
- A button that tests the settings cannot read the constants: it runs after save while constants still hold the page-load values, so it would try the previous URL. `pg_signature_request_stamp()` therefore takes `$settings`, and `&$reason` carries the failure to the screen (not only the log).

## Which auth formats are listed?
- Only working ones: "Yok" and "HTTP Basic". Kamu SM credentials travel inside the request and their encoding is unverified; not listed until verified (a non-working option is worse than none).

## Why is the ASN.1 hand-written?
- PHP's `openssl` extension exposes no timestamp functions, and the `openssl` command line may be closed on shared hosts (`disable_functions`). The DER reader also descends into octet strings, because the stamp's signed content is an octet string.

Related: docs/memory/designer/signature-receipt-and-output.md
