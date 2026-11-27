---
name: sales-channels-global-census-ebay
description: Census (2026-11-27): eBay's Sell APIs as developer.ebay.com says them: connecting a seller, listings, stock and price, orders and shipment, registering, account deletion notices
---

## Where are the pages?
- Addresses below are on `developer.ebay.com`.

## How is a seller's shop connected?
- OAuth authorization code; the return address is the application's RuName (one for sandbox, one for production, each with an accept address and a privacy policy address). Code 299 s, access token 2 hours, refresh token about 18 months (/develop/guides/sell/authorization).
- Scopes sell.inventory, sell.fulfillment, sell.account. Tokens a day: 10,000 by code, 50,000 by refresh.

## What does a listing ask?
- Three calls: createOrReplaceInventoryItem (`PUT /sell/inventory/v1/inventory_item/{sku}`: product, condition, quantity), createOffer (marketplaceId, categoryId, payment, return and fulfillment policy ids, merchantLocationKey, price with currency), publishOffer. Variations are an inventory item group (/api-docs/sell/static/inventory/inventory-item-to-offer.html, publishing-offers.html).
- First: an inventory location, and business policies after opting in to SELLING_POLICY_MANAGEMENT (/api-docs/sell/static/seller-accounts/business-policies.html). Required item specifics per category: getItemAspectsForCategory, with an application token.
- Images: HTTPS addresses or eBay's picture service, 24 a listing (/api-docs/sell/static/inventory/managing-image-media.html). The "Does not apply" text for a missing identifier is worded per site (product-identifier-text.html). 250 revisions a listing a day.

## Stock and price?
- bulkUpdatePriceQuantity: the guide says 25 items a call (/api-docs/sell/static/inventory/bulk-updates.html), the reference one SKU's offers.
- Calls a day: Inventory 2,000,000, Fulfillment 100,000 (/develop/api/sell/api_call_limits).

## Orders and shipment?
- getOrders `GET apiz.ebay.com/sell/fulfillment/v1/order`, 200 a page, 90 days back by default, up to 2 years (/develop/api/sell/fulfillment_api/order/getorders). Notice ORDER_CONFIRMATION, signed in `X-EBAY-SIGNATURE` (/develop/api/sell/notification_events).
- Shipment: createShippingFulfillment with the lines, carrier code and tracking number.

## Several countries?
- One offer per marketplaceId, each with its own price and currency, a Content-Language header per site, and the EU's product safety fields.

## What does registering ask of the owner?
- Membership is free, approved in about one working day (/develop/guides/sell/get-started-with-ebay-apis).
- Production keys stay off until the application subscribes to Marketplace Account Deletion notices, or is exempted: an HTTPS address that answers a challenge code (/develop/guides/sell/marketplace-user-account-deletion).
- Higher call limits: the free Application Growth Check (/grow/application-growth-check).
- Licence (/join/api-license-agreement): keys are not given to third parties or users without eBay's consent; buyers' data is deleted when asked; eBay content is destroyed within 10 days of the end.
- A working sandbox with limits (/develop/tools/sandbox).
