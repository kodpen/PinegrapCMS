---
name: sales-channels-global-census-amazon
description: Census (2026-11-27): Amazon's Selling Partner API (SP-API) as its developer pages say it: connecting a seller, listings, stock and price, orders and shipment, registering an application, fees
---

## Where are the pages?
- D/ = `developer-docs.amazon/sp-api/docs/`, R/ = `.../sp-api/reference/`, C/ = `.../sp-api/changelog/` (the old `developer-docs.amazon.com` moves there).

## How is a seller's shop connected?
- Public application: the seller opens `{Seller Central}/apps/authorize/consent?application_id=…&state=…`; the return brings `selling_partner_id` and a 5-minute `spapi_oauth_code`, swapped at `api.amazon.com/auth/o2/token` for a refresh token and a one-hour access token (D/website-authorization-workflow). The seller authorizes again every 365 days (D/authorize-public-applications).
- Private application: authorized by the account's primary user, no consent page (D/self-authorization). Limits: 10 sellers private, 25 public unlisted (D/application-authorization-limits).
- The LWA client secret is rotated every 180 days (D/rotating-your-apps-lwa-credentials). No IAM or SigV4 since 2 October 2023 (C/october-2023-sp-api-release-announcement); header `x-amz-access-token` (D/connecting-to-the-selling-partner-api).
- Regions NA, EU (Turkey, Germany, UK...) and FE (D/sp-api-endpoints); one authorization covers the region's marketplaces; ids in D/marketplace-ids.

## What does a listing ask?
- `PUT /listings/2021-08-01/items/{sellerId}/{sku}?marketplaceIds=` with `productType` and `attributes`, a VALIDATION_PREVIEW mode, 5 calls a second (R/putlistingsitem); a PUT replaces the whole listing (D/create-a-listing).
- The attributes' JSON schema per product type: R/getdefinitionsproducttype. A catalogue product is matched by EAN, GTIN or UPC (R/searchcatalogitems); a GTIN exemption is asked in Seller Central (D/listings-apis-faq). Images are URLs: one main, nine more.

## Stock and price?
- R/patchlistingsitem: `purchasable_offer` with a currency per marketplace, several marketplaces in one call (D/update-price-for-multiple-marketplace); `fulfillment_availability` for goods the seller ships (FBA stock is Amazon's).
- In bulk: JSON_LISTINGS_FEED, 25,000 messages a feed (D/building-listings-management-workflows-guide). A change shows after up to 15 minutes.

## Orders and shipment?
- Orders API v2026-01-01 `searchOrders` (R/searchorders); v0 getOrders goes on 27 March 2027 (C/new-introducing-the-orders-api-v2026-01-01). Slow: 0.0056 calls a second, burst 20 (D/orders-api-rate-limits). Buyer and address by role (D/access-orders-pii).
- Order notifications go only to Amazon SQS or EventBridge, so an AWS account (R/createdestination); without it the job polls.
- Shipment: v0 confirmShipment with carrier, tracking number, date and lines (R/confirmshipment); v2026 has none yet.

## What does registering ask of the owner?
- Private developer: its own company only (D/register-as-a-private-developer). Public: listed in the Appstore; the address roles (D/roles-in-the-selling-partner-api) add business checks, a security assessment and an architecture review (D/register-as-a-public-developer). Review 1-2 weeks (D/onboarding-step-3-create-a-developer-profile).
- Data Protection Policy (Seller Central, DPP): buyer data kept at most 30 days after delivery, encrypted, incidents told within 24 hours. From 25 August 2026 a WAF and sealed credentials (developer.amazonservices.com/policy-update-for-sp-api).
- Fees: a yearly fee was announced in 2025 and dropped (developer.amazonservices.com/cancellation-of-sp-api-fees, undated). Sandbox: fixed answers only (D/sp-api-sandbox).
