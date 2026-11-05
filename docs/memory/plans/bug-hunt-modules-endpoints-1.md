---
name: bug-hunt-modules-endpoints-1
description: Module list of plans/bug-hunt.md, part 3: root endpoint groups account to offer by area word, one line each with its state
---

## Which root endpoint groups (account to offer) are hunted?
- Done: account #4675, ad #4854, affiliate #4998, api #4615, arrival, auto, barcode, billing #5016, calendar #5049, cart #4561, catalog #5098, change #5098, check #5108, comment #5108, commission #5113, common, connect, contact #5124 #6126 #6139, container #5133, country #5144, currency #5147, custom #5164, design #4663 #4684, designer #5177, device, dynamic, editor #5167, email, erp #5549 #5607 #5622 #5643, expres #5265 #6126, field, file #5407, find #6027, folder #6027 #6066, forgot #6104, form #4571 #4582, generate, gift #4576 #4586, google #6092, key #6092, login #4594, logout #6092, membership #6094, menu #4673, my #4653, offer
- Endpoints `image` (3): edit clean; api held by #5148
- External API routes of external-api-coverage X4–X12, second round (`includes/api/resources/`): done #7009 (POST /orders dropped a split part whose amount was not a whole number); users read, store lookups and currencies, countries and states, campaigns and profiles, calendars and locations, form submission writes and /design/ ads, dialogs and common regions clean (scopes, ceilings, role-3 rows, dry-run order, max_length against columns)
- Outgoing webhooks, second round (`includes/api/outbound/webhooks.php`, `includes/api/resources/webhooks.php`, `api_webhook_job.php`): done #7028 (a payload with broken UTF-8 was queued with an empty body)
