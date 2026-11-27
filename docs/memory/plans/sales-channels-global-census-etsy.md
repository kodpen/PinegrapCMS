---
name: sales-channels-global-census-etsy
description: Census (2026-11-27): Etsy's Open API v3: connecting a shop with PKCE, listings with uploaded images, stock and price, receipts and shipment, limits for sellers in Turkey, registering
---

## Where are the pages?
- Addresses below are on `developers.etsy.com` unless named.

## How is a shop connected?
- OAuth 2.0 with PKCE (S256): `www.etsy.com/oauth/connect`, token at `api.etsy.com/v3/public/oauth/token`; 12 scopes; access token 1 hour, refresh token 90 days; the return address matches exactly and is HTTPS (/documentation/essentials/authentication).
- Every call sends `x-api-key: keystring:shared_secret`, required since 9 February 2026 (/documentation/essentials/requests).

## What does a listing ask?
- createDraftListing: quantity, title, description, price, who_made, when_made, taxonomy_id; a physical good also shipping_profile_id and readiness_state_id (/documentation/tutorials/listings).
- Images are uploaded as files (multipart), at least one before updateListing with state=active publishes it.
- Etsy takes $0.20 a listing and 6.5% a sale (help.etsy.com/hc/en-us/articles/360035902374). Mass-produced goods bought to resell are not allowed (help.etsy.com/hc/en-us/articles/360024112614).

## Stock and price?
- updateListingInventory: one listing a call, with its whole products array; price as a decimal number. Reading in bulk only, 100 a call.
- Limits per key, a second and a day (/documentation/essentials/rate-limits).

## Orders and shipment?
- getShopReceipts by min_last_modified and was_paid, 100 a call; money carries currency_code. Webhooks (order.paid...) signed HMAC-SHA256 (/documentation/essentials/webhooks).
- Shipment: createReceiptShipment with tracking_code and carrier_name, "not available to all apps" (/documentation/tutorials/fulfillment).
- Turkey: since 3 June 2024 a personal key made in Turkey has no createReceiptShipment and no address fields, and new Turkish sellers on a commercial application lose them unless it is a Preferred Partner (github.com/etsy/open-api/releases/tag/3.0.0-general-release-2024-06-03).

## Countries and currency?
- One currency a shop; the shop ships to many countries. One account a shop fits today's tables.

## What does registering ask of the owner?
- Tiers (/documentation/, help.etsy.com/hc/en-us/articles/360025870013): a seller's application for its own shop; personal, up to 5 shops; commercial, reviewed by hand. No API fee is named.
- The trademark notice is shown in the application; the shared secret is never published (/documentation/tutorials/quickstart).
- No sandbox: tests run on test shops.
