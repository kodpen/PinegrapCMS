---
name: ad-conversions
description: Plan (owner 2026-11-20, poll 166): purchases and other events sent from the server to Meta CAPI, GA4 Measurement Protocol, TikTok Events API; personal data only with consent; SC1 to SC7
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 166, message 10193): "Evet, plana girsin": three services, six events, personal data only with marketing consent, a queue and a panel log. The shop pastes its own keys; no Meta app review and no middle server.

## What is there today?
- No ad platform receives any event, from the browser or the server. The Google tag (`connections.php?service=google_analytics`, `ui/connections-google-analytics-page.md`) counts page views only. Tag Manager, Google Ads and the Meta Pixel have no field: a pasted script, held by the cookie notice (`plans/integrations-census-today-2.md`, which lists the Conversions API as missing).
- The Meta Pixel and catalogue of `plans/reverse-oauth-leftovers.md` (model B) wait for Meta business verification; this plan does not.
- Cookie notice with Consent Mode v2 (`platform/cookie-consent-notice.md`); `pg_consent_allows('marketing')` answers true while the notice is off. Browser ids are read by SC1 (`api/ad-conversions-queue.md`), click ids by SC1b (`api/ad-conversions-click-ids.md`).
- An order completed by a payment service's own message carries no visitor (`commerce/order-completion-after-lines.md`), so cookies read at that moment are not the buyer's.
- The webhook queue (`api/api-webhook-queue.md`) already carries `webhook_id = 0` rows for Slack, Teams and Telegram: a sealed target, a body sent as built, retries, a claim, a sweep.

## What binds it?
- No network call in a request: an event is a queue row; checkout never waits.
- Personal data (hashed e-mail and phone, browser and click ids) only for a buyer who accepted marketing cookies; without it, nothing, or with the shop's setting a purchase or refund without the person.
- Marketplace orders are never sent; counter sales only with a setting, never with personal data.
- Keys sealed in `config_text` (`data/config-table-no-varchar.md`); each Connections page under the Visitors and privacy gate, as Google Analytics'.
- Each service's request format, test mode and limits are read from its own document in its step, written into memory, never from memory.
- Money in minor units, turned into the service's decimal only in the body.
- The privacy export and erase (`pg_privacy_erase_tables()`) cover whatever the plan keeps about a person.

## What did the architect decide? (2026-11-20, agent channel message 10514)
- Owner, poll 174 (message 10512): one table, `ad_conversion_events`: queue, log and the buyer's ids (`workflow/decisions-made-schema-35.md`, message 10515). Not `api_webhook_queue`, not a column on `orders`; the reasons are in that part.
- Owner, poll 175 (message 10513): with the cookie notice off no personal data goes by default; each Connections page has "Send personal data while the cookie notice is off" (C8), ticked at the shop's own risk (message 10516).
- Consent is read by a pure function from three inputs (notice on, the stored answer, the service's box), never `pg_consent_allows()`.
- Add to cart and begin checkout from the server, no new public endpoint (C6); both only with consent. The architect's reading; the owner may turn it.
- A click id (`fbclid`, `gclid`, `ttclid`) seen before consent waits in the session, never in the table, used only if consent comes; a new cookie follows `platform/cookie-consent-new-cookies.md`.
- `submit_order.php` is also entered by `plans/stock-holds.md` SH3: never two open PRs on it.

## What is built?
- Features: `ad-conversions-features.md` (C1 to C12). Steps: `ad-conversions-steps.md`. Claim as `[plan:ad-conversions] <step> — <topic>`.

## What is left out?
- Google Ads' own conversion upload, Pinterest, Snapchat, a browser pixel field, the Meta catalogue; each a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): the three Connections pages are seen on dev after SC2.
