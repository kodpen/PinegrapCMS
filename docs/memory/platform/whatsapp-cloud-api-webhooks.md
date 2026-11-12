---
name: whatsapp-cloud-api-webhooks
description: Meta's WhatsApp webhooks and error codes as read on 2026-11-12: the verify call, X-Hub-Signature-256, retries, fields, delivery and template states, usernames and BSUIDs, which error codes may be tried again
---

## How is the address verified?
- Saving the Callback URL and Verify token in the app (WhatsApp › Configuration) sends `GET ?hub.mode=subscribe&hub.challenge=…&hub.verify_token=…`; answer 200 with the challenge alone when the token matches, else a 4xx, and the address is not saved. PHP reads the names as `hub_mode`, `hub_challenge`, `hub_verify_token`.
- A valid TLS certificate is required (no self-signed); mTLS is optional.

## How is a POST checked and answered?
- `X-Hub-Signature-256: sha256=<hex>`: HMAC-SHA256 of the raw body with the app secret. Answer 200; anything else is retried with decreasing frequency for up to 7 days, and retries can arrive twice: deduplicate by the message or status id. Bodies up to 3 MB, batched (`entry[].changes[]`).
- There is no API to fetch past webhooks. Some are not sent while the app is in Dev mode.

## Which fields matter here?
- `messages`: a user's message (`messages[]`: `from`, `id`, `timestamp`, `type`, `text.body`; `contacts[].profile.name`, `wa_id`) and the states of sent ones (`statuses[]`: `id` = the wamid, `status` sent, delivered, read, failed, played; `errors[]` with `code`, `title`, `error_data.details`).
- `message_template_status_update`: `event` APPROVED, REJECTED, PENDING, PAUSED, DISABLED, FLAGGED, IN_APPEAL, PENDING_DELETION, DELETED, REINSTATED, ARCHIVED, LIMIT_EXCEEDED; `reason` for a rejection; `other_info` with `reason` and `recommendation` for INVALID_FORMAT.
- `template_category_update` (a category Meta changed), `user_preferences` (a user stopped or resumed marketing messages), `account_alerts`, `phone_number_quality_update`.
- The subscribing app needs `whatsapp_business_messaging` for `messages`, `whatsapp_business_management` for the rest.

## Usernames and BSUIDs?
- Every user also has a business-scoped user id (`user_id`, `from_user_id`, like `US.1349…`). A user who adopted a WhatsApp username may come without a phone number (`wa_id`, `from` missing): a message is then tied to a contact only through an earlier one.
- A send may name `recipient` (the BSUID) instead of `to`; authentication templates only to a phone number (131062).

## Which error codes may be tried again?
- The answer is `error.code` (subcodes are gone since v16.0); build on the code, not the HTTP status. A coded error took nothing, so trying again cannot deliver twice.
- Later, unchanged: 2 (temporary), 4 (app rate), 80007 (account rate), 130429 (throughput), 131016 (service unavailable), 131056 (same pair too often), 131057 (maintenance), 131000 (unknown, "try again").
- Never: 190 (token expired or invalid), 0, 3, 10, 200–299 (permission), 100, 131008, 131009 (parameters), 131026 (not a WhatsApp number), 131047 (window closed, free text), 131049 (per-user marketing cap: wait 24 h), 131050 (user stopped marketing), 130403 (blocked by the business), 131042 (payment), 131045 (number not registered), 132000 (variable count), 132001 (template or language missing, or not approved), 132007 (policy), 368, 131031 (account restricted).
- No answer, a timeout after the request was written, a 5xx without Meta's JSON, or a 200 without a wamid: Meta may have it (uncertain).

Related: docs/memory/platform/whatsapp-cloud-api-facts.md, docs/memory/platform/sms-outbox-queue.md
