---
name: sms-netgsm-driver
description: Netgsm SMS driver (includes/sms/netgsm.php, PR #600): REST v2 send and msgheader calls, encoding, answer codes, when a send may be retried, refused vs could-not-ask, own cURL call
---

## What does Netgsm take?
- `POST https://api.netgsm.com.tr/sms/rest/v2/send`, Basic auth (subscriber number or API sub-user : password), JSON `msgheader`, `messages[{msg,no}]`, `iysfilter` (`0` informational, `11` commercial), `encoding: TR` for any non-ASCII character (Turkish letters, ₺, typographic quotes would be garbled otherwise). Number: 10 digits `5xxxxxxxxx`, Turkish mobiles only (abroad needs an account permission and an unverified format).
- `GET /sms/rest/v2/msgheader` (no Content-Type) lists approved senders as `msgheaders` or `msgheader` and costs nothing: it is the credential check.
- Endpoints and fields come from client libraries (siberfx/netgsm, mobinova/netgsm-go; Netgsm's SDKs agree on `jobid` and the codes). Not tried against a live account.

## How is an answer read?
- The body is read whatever the status (refusals come as JSON with 406). `json_decode(..., JSON_BIGINT_AS_STRING)`: a numeric `jobid` keeps its digits. `00` without `jobid` is a failure. A bare 401/403 reads as code 30.
- Codes: 20 text, 30 credentials / API permission / IP list, 40-41 sender, 50-51 İYS, 70 parameter, 80 and 85 limits, 100-101 system error; unknown codes keep Netgsm's description, stripped and cut.

## When may a send be retried?
- Only when Netgsm cannot have taken it: cURL 5/6/7 (proxy or host not resolved, no connection: `pg_sms_http_never_sent()`), HTTP 429/503, codes 80/85/100/101. A timeout or a 500/502/504 may come after Netgsm took the message and a doubled message cannot be taken back, so `retry` is false.
- The sender check reports `refused` (Netgsm answered and refused: a code other than 80/85/100/101, or 401/403) apart from "could not ask" (no answer, unreadable answer, limit, outage), which the settings save unchecked.

## Why its own cURL call?
- `pg_sms_http()` is a plain cURL call with `pg_curl_tls()` and no redirects, like the carriers: the address is fixed in the driver, so the SSRF client (`api_http_request()`) adds nothing, and it would not return the cURL error number the retry rule needs.

Related: docs/memory/platform/sms-provider-layer.md
