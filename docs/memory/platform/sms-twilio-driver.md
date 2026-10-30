---
name: sms-twilio-driver
description: Twilio SMS driver (includes/sms/twilio.php, PR #NNNN): Basic auth, Messages.json form POST, IncomingPhoneNumbers check, Messaging Service SID, sender rule, error codes, retry and uncertain rules
---

## What does Twilio take?
- `POST https://api.twilio.com/2010-04-01/Accounts/{AccountSID}/Messages.json`, Basic auth Account SID : Auth Token, form-encoded `To` (+E.164), `From` (an account number) or `MessagingServiceSid` (`MG…`), `Body`. Success is 201 with `sid` (`SM…`, 34 characters, fits `sms_outbox.provider_id`).
- Every country `pg_sms_number()` reads goes out (`pg_sms_twilio_number()` adds the plus); the account's Geo permissions decide (21408). A national `5xx…` still reads as a Turkish mobile; foreign numbers need `+`/`00`.
- One active provider: no routing of Turkish numbers to Netgsm and the rest to Twilio (a product question if wanted).
- Sources: twilio.com/docs/{usage/requests-to-twilio, messaging/api/message-resource, phone-numbers/api/incomingphonenumber-resource, usage/twilios-response, api/errors}, cited in the file header. Not tried against a live account.

## How is the account checked?
- `GET …/IncomingPhoneNumbers.json?PageSize=1000` costs nothing: the `phone_number`s whose `capabilities.sms` is not false are the senders. A Messaging Service SID is accepted as it is (services live on another API host; a wrong one fails the first message with 21705).
- Card: Account SID / Auth Token, sender label "Sender", maxlength 34. Provider keys `sender_pattern` (`+E.164` or `MG` + 32 hex), `sender_error`, `sender_maxlength`, `sender_label`, `sender_compact` (spaces, dashes, dots and brackets dropped from a copied `+1 (415) 555-0100`) in `pg_sms_providers()`; `pg_sms_sender_valid($sender, $provider)` reads them, Netgsm and İleti Merkezi keep the 11-character name.
- `refused_fields`: 20003 → Auth Token field; 20404 (unknown SID) → Account SID.

## How is an answer read?
- A 2xx with JSON is the resource; otherwise `code` from Twilio's error JSON, a bare 401/403 reads as 20003. A 2xx without `sid` is `uncertain`; a `sid` with status `failed`/`undelivered` is a refusal with its `error_code`.
- Codes with their own sentence: 20003, 20008 (test credentials), 20404, 20429, 21211/21614 recipient, 21212/21606/21659 sender, 21408 country, 21602/21617 text, 21608 trial account to an unverified number, 21610 STOP, 21612 cannot route, 21703-21705 messaging service. Others keep Twilio's message, stripped and cut.

## When may a send be retried?
- Only 20429, a bare 429/503, or a transport error where `pg_sms_http_never_sent()` says nothing left. A timeout after the request, a 500/502/504 or an unreadable 2xx is `uncertain`, never resent (as `sms-netgsm-driver.md`).
- The sender check: `refused` for any coded answer but 20429; no answer or an unreadable one is "could not ask" (saved unchecked).

Related: docs/memory/platform/sms-provider-layer.md, docs/memory/platform/sms-iletimerkezi-driver.md
