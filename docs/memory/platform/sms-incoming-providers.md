---
name: sms-incoming-providers
description: Which SMS providers can receive a customer's message, how and at what cost (Twilio, İleti Merkezi, Netgsm; read 2026-11-13 for WA12, not tried live)
---

## Which SMS providers can receive? (read 2026-11-13, not tried live)
- Twilio: yes. A number with SMS capability (the driver already lists `IncomingPhoneNumbers`); its "A message comes in" URL gets a POST (`From`, `To`, `Body`, `MessageSid`, `NumMedia`), signed in `X-Twilio-Signature` (HMAC-SHA1 of the URL and the sorted fields with the auth token).
- Twilio costs: an incoming message is billed per part; the number has a monthly fee. Twilio itself answers STOP, START, HELP and adds `OptOutType`. A Turkish Twilio number taking SMS was not found: Turkish customers write to a foreign number.
- İleti Merkezi: yes, by poll. `get-inbox` lists the messages the account received (`shortCode`, `from`, `keyword`, `command`), no credit spent; the account's webhook carries delivery reports, and incoming SMS on it is not documented. It is a short number with a keyword, bought from İleti Merkezi; no HMAC, only a token in the URL.
- Netgsm: yes, by poll. "Gelen SMS sorgulama" lists the received messages between two dates for a number of Netgsm's that receives SMS (an 0850 number or a keyword on a short number, bought from Netgsm). No webhook found; the exact call and fields to be read from Netgsm's docs in WA16.
- Built that way (WA16, #9412, #9425): Twilio's webhook, one poll pass in the general job for İleti Merkezi and Netgsm; a provider without a receiving number shows no Channel field and offers no SMS answer (`workspace/mail-sms-reply.md`).

Related: docs/memory/platform/sms-incoming.md, docs/memory/workspace/mail-phone-decisions.md
