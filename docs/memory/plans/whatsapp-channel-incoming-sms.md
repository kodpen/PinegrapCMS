---
name: whatsapp-channel-incoming-sms
description: Incoming SMS per provider (Netgsm, İleti Merkezi, Twilio), the WhatsApp message kinds shown, stop words, the SMS kind and consent of an answer (WA12; decided #9286)
---

## Which SMS providers can receive? (read 2026-11-13, not tried live)
- Twilio: yes. A number with SMS capability (the driver already lists `IncomingPhoneNumbers`); its "A message comes in" URL gets a POST (`From`, `To`, `Body`, `MessageSid`, `NumMedia`), signed in `X-Twilio-Signature` (HMAC-SHA1 of the URL and the sorted fields with the auth token).
- Twilio costs: an incoming message is billed per part; the number has a monthly fee. Twilio itself answers STOP, START, HELP and adds `OptOutType`. A Turkish Twilio number taking SMS was not found: Turkish customers write to a foreign number.
- İleti Merkezi: yes, by poll. `get-inbox` lists the messages the account received (`shortCode`, `from`, `keyword`, `command`), no credit spent; the account's webhook carries delivery reports, and incoming SMS on it is not documented. It is a short number with a keyword, bought from İleti Merkezi; no HMAC, only a token in the URL.
- Netgsm: yes, by poll. "Gelen SMS sorgulama" lists the received messages between two dates for a number of Netgsm's that receives SMS (an 0850 number or a keyword on a short number, bought from Netgsm). No webhook found; the exact call and fields to be read from Netgsm's docs in WA16.
- So WA16 builds Twilio's webhook and one poll pass in the general job for İleti Merkezi and Netgsm; a provider without a receiving number shows no channel field, and WA17 offers no SMS answer for it.

## Which WhatsApp messages arrive, and which are shown?
- Shown as text: `text`, a template's quick reply (`button.text`), `interactive` button or list reply (its title), `contacts` (name and number), `location` (name, address, a map link).
- Shown as a file: `image`, `video`, `audio`, `document`, `sticker`, with a caption as text. Fetched by media id with the token while Meta keeps it.
- A `reaction` is a line under the message it answers, never a conversation of its own. `order`, `system`, `unsupported`, `request_welcome`: a line "This WhatsApp message cannot be shown here."
- Every arrival still writes `whatsapp_inbox` (WA4, #9085).

## Which words stop messages?
- WhatsApp: its own stop (`user_preferences`, error 131050) as WA0 has it. The same words below written in WhatsApp count too (#9286).
- SMS and WhatsApp words, the whole message alone, any case, Turkish letters folded: STOP, STOPALL, UNSUBSCRIBE, CANCEL, END, QUIT, IPTAL, İPTAL, RET, DUR.
- Effect: an SMS marketing refusal row in `consent_log` (the source "incoming message"), pushed to İYS as any refusal; the conversation still opens, so the team sees it. Not a stop for transactional messages (orders, codes).

## Which SMS kind and consent rule does an answer have? (owner, 2026-11-13, #9286)
- A new kind `reply`: transactional, exempt from İYS like order messages, no promotion text, never `marketing`.
- Allowed only to the number of the conversation it answers, and only within 7 days after that number's last incoming SMS; after that the drawer offers nothing. No consent asked, a marketing refusal does not stop it (it answers the customer's own question).
- WhatsApp needs no such rule: Meta's 24 hours and approved templates decide; a marketing template after the window keeps WA10's consent gate.

Related: docs/memory/platform/sms-and-iys-rules.md, docs/memory/platform/whatsapp-cloud-api-webhooks.md, docs/memory/plans/whatsapp-channel-census.md
