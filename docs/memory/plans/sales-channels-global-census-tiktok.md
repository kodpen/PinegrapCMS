---
name: sales-channels-global-census-tiktok
description: Census (2026-11-27): TikTok Shop's Partner API: connecting a shop, a shop cipher per country, products, stock and price, orders and packages, partner registration and reviews
---

## Where are the pages?
- `partner.tiktokshop.com/docv2/page/<name>`; they are drawn by JavaScript and were read in a browser.

## How is a shop connected?
- The seller opens `services.tiktokshop.com/open/authorize?service_id=…`; the code lives 30 minutes; token at `auth.tiktok-shops.com/api/v2/token/get`; access token 7 days, refresh token as long as the authorization (authorization-overview-202407).
- Each call: host `open-api.tiktokglobalshop.com`, signed HMAC-SHA256 with the app secret (sign-your-api-request), timestamp within 5 minutes back and 30 seconds ahead (common-parameters).
- Get Authorized Shops gives a `cipher` a shop; a cross-border seller has several (get-authorized-shops-202309).
- Markets: BR, FR, DE, ID, IE, IT, JP, MY, MX, PH, SG, ES, TH, GB, US, VN. Not Turkey (regions-and-languages).

## What does a product ask?
- `POST /product/202309/products`: leaf category, brand, up to 9 main images by uri, HTML description, package weight, certifications, attributes; SKUs with sales attributes, stock per warehouse_id and price with currency per market. Every product is audited (create-product-202309).
- Images are uploaded first as files (upload-product-image-202309).

## Stock and price?
- Update Inventory and Update Price, one product a call (update-inventory-202309, update-price-202309). Rate limits change with load (rate-limits).

## Orders and shipment?
- `POST /order/202309/orders/search`, 100 a page (get-order-list-202309); the address is masked while UNPAID or ON_HOLD. Detail `/order/202507/orders`, 50 ids a call. Webhook ORDER_STATUS_CHANGE, HMAC-SHA256 (tts-webhooks-overview).
- Ship Package `/fulfillment/202309/packages/{id}/ship` with self_shipment: tracking number and shipping provider id (ship-package-202309). Mark Package As Shipped (202610) only in US, UK, ES, IE, IT, DE, FR and JP (mark-package-as-shipped-202610).

## What does registering ask of the owner?
- Developer types (tts-developer-types). A partner registers in the region of its company papers; compliance review 3 weeks or more; "local partners should choose only their local market" (developer-onboarding).
- Public or custom application. A custom one is reviewed if it is a connector or has 25 sellers or more; up to 10 markets an application (create-your-app).
- Reviews: registration about 5 working days, development 7-10 days, App Store listing 7-10 days a market, data security about 2 weeks (app-review-process). No fee is named.
- Development shops without KYC for tests (seller-center-development-shops). API version 1 ended 31 December 2025 (deprecating-tts-api-v1).
