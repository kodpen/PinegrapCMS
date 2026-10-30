---
name: sms-iletimerkezi-driver
description: İleti Merkezi SMS driver (includes/sms/iletimerkezi.php, PR #3623): REST v1 send-sms and get-sender, panel-issued key and hash, answer codes, retry and uncertain rules
---

## What does İleti Merkezi take?
- `POST https://api.iletimerkezi.com/v1/send-sms/json` and `/v1/get-sender/json`, JSON body `request.authentication {key, hash}`; send adds `order {sender, iys, message {text, receipents {number[]}}}` (`receipents` is their spelling; `recipients` gives 452). `iys` is required: `"0"` informational, `"1"` + `iysList: BIREYSEL` commercial.
- Number: `905XXXXXXXXX`, Turkish mobiles only (abroad is a panel mode, as with Netgsm).
- `get-sender` costs nothing and lists `response.senders.sender` (a list; a single name taken too): it is the credential check. An empty list means approval pending.
- Sources: iletimerkezi.com/en/docs/api/{authentication, send-sms, get-sender, error-codes} and toplusmsapi.com/durum-kodlari-ve-mesajlar, cited in the file header. Not tried against a live account.

## Why API Key and API Hash, no HMAC?
- The docs (2026-08-05) say the panel issues a precomputed hash and "do not generate the hash yourself"; computing one gives 401. Older guides showing MD5/SHA/HMAC are wrong. So the card asks for both and the hash is sealed like a password (`client_secret`), the key is `client_id`.
- 401 also means "Allow API access" is off in the panel or the server IP is not listed; the error text says so.

## How is an answer read?
- `response.status.code` (int or string) mirrors the HTTP status; the body is read whatever the status. `JSON_BIGINT_AS_STRING` keeps a numeric order id. A bare 401/403 without JSON reads as 401.
- 200 with `response.order.id` is success; 200 without an id is a failure marked `uncertain`.
- Codes with their own sentence: 400/404/422 request, 401 credentials, 402 balance, 450/459/460 sender, 451 repeat (same text to the same number within minutes), 452/466 recipient, 453/454 text, 467-470 İYS, 503 out of service; others keep İleti Merkezi's message, stripped and cut.

## When may a send be retried?
- Only code 503, a bare HTTP 429/503, or a transport error where `pg_sms_http_never_sent()` says nothing left. A timeout after the request, a 500/502/504 or an unreadable 2xx is `uncertain`, never resent (same rule as `sms-netgsm-driver.md`). 451 is a refusal, not retried: an earlier copy already went.
- The sender check: `refused` for any code but 503; no answer or an unreadable one is "could not ask" (saved unchecked).

Related: docs/memory/platform/sms-provider-layer.md, docs/memory/platform/sms-netgsm-driver.md
