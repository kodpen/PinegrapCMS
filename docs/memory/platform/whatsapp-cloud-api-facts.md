---
name: whatsapp-cloud-api-facts
description: Meta's WhatsApp Cloud API on 2026-11-12: Graph version, ids and permanent token, sending a template, the 24-hour window, template limits, categories, prices, media
---

## Which API and version?
- `https://graph.facebook.com/<version>/`. On 2026-11-12 Graph's changelog named v26.0 (2026-07-29) the latest; the WhatsApp examples use v27.0, which the API answers but the changelog does not list. Docs: `developers.facebook.com/documentation/business-messaging/whatsapp/` (`.md` for text).
- Meta now calls the WABA a "Messaging account"; endpoints still say `WABA-ID`.

## What does a site need from Meta?
- An app (App ID, App secret in App settings › Basic) with the WhatsApp product; a business phone number registered on it (its Phone number ID, not the number, is the sender); the Messaging account id.
- A system user token (Business settings › System users › Generate token, "Never" expires) with `whatsapp_business_messaging`, `whatsapp_business_management`, `business_management`; the system user needs access to both the WhatsApp account and the Messaging account (else error 200). Tokens are opaque, of any length.
- `GET /debug_token?input_token=<token>` with the app token `<app id>|<app secret>` gives `app_id`, `is_valid`, `expires_at`, `scopes`, `granular_scopes` (`target_ids`): checks the pair and the token in one call. A wrong app answers HTTP 400 code 190.
- `appsecret_proof` = HMAC-SHA256 of the token with the app secret, sent beside the token on each call.
- `GET /<phone number id>` gives `display_phone_number`, `verified_name`, `quality_rating` (GREEN, YELLOW, RED, NA); `GET /<waba id>/phone_numbers` lists the account's numbers.

## How is a message sent?
- `POST /<phone number id>/messages`, JSON `messaging_product: whatsapp`, `recipient_type: individual`, `to` with its `+` (without it the business number's country code is prefixed), `type: template`, `template: {name, language: {code}, components}`.
- 200 means accepted only: `messages[0].id` (`wamid.…`); delivery comes by webhook.
- A user's message opens a 24-hour customer service window; outside it only approved templates go. Opt-in is required, may be general (not WhatsApp-only) if local law allows; per-user caps on marketing templates exist (131049).

## What can a template hold?
- Name: lowercase letters, digits, `_`, at most 512; the same name may exist per language. Category `MARKETING`, `UTILITY` or `AUTHENTICATION` (OTP only); Meta may approve a "utility" one as marketing or move it later (`template_category_update`).
- Header (optional): text 60 with one variable, or image, video, document (GIF only through the Marketing Messages API), or location. Body 1024 (required). Footer 60, no variables.
- Buttons: up to 10, quick replies grouped apart from the others; URL at most 2 (2000 characters, one variable at the end, percent-encode it), phone 1, copy code 1 (code at most 20). More than 3 show as "See all options".
- Variables named (`{{first_name}}`) or positional (`{{1}}`); a sample per variable at creation; not at the start or end, not too dense (2388299, 2388293).
- Review up to 24 hours. 250 templates per unverified Messaging account, 100 created an hour; unused 12 months: archived, then deleted.

## What does a message cost?
- Per delivered message by category and the recipient's country (Turkey is a named market). Utility templates inside an open service window and service messages are free. Rates: business.whatsapp.com/products/platform-pricing.

## Media and limits?
- A header sample goes through the Resumable Upload API (`/<app id>/uploads`: needs the App ID); message media through `POST /<phone id>/media`: JPEG/PNG 5 MB, MP4/3GPP 16 MB, document 100 MB; ids last 30 days.
- Unique users reached outside windows in 24 h, per business portfolio: 250 new, 2,000 once verified, then automatic. 80 messages a second per number (130429).

Related: docs/memory/platform/whatsapp-cloud-api-webhooks.md, docs/memory/plans/whatsapp-channel.md
