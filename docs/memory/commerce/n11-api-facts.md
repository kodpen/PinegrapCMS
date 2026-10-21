---
name: n11-api-facts
description: n11 REST API facts for the connector: headers, connection test, async price/stock task flow, REJECT vs IN_QUEUE, order endpoints and page cap, GMT+3 dates, discount fields, stockCode.
---

## How does the n11 API behave? (developer.n11.com)
- Host `api.n11.com`, headers `appKey` / `appSecret` (the docs write them two different ways in two services; the connector sends both). SOAP services are NOT used.

## How is the connection tested? (`mp_n11_test_credentials()`)
- GET `/cdn/categories`, the whole category tree, read with `mp_n11_category_tree_bytes()` (8 MB) like every call to that endpoint. 401/403 is reported as a refused key.
- Symptom before the fix (#1029): it read 4,000 bytes, so the right key failed with "n11 answered 200 with something that is not JSON" and the account's last error was written.
- `mp_n11_call()` reports a body as long as its limit as cut off (not retried), not as "not JSON".

## How does the async product side work?
- `price-stock-update` (1000 SKU/request) -> `taskId` -> `task-details/page-query` -> SUCCESS/FAIL PER SKU.
- Saying "success" on submission is wrong.
- `REJECT` is permanent; an `IN_QUEUE` without an id is retried. Never put the two in the same branch (it made an accepted batch permanently failed).

## How do orders work?
- `GET /rest/delivery/v1/shipmentPackages`: dates in milliseconds and GMT+3 (sending UTC shifts the window by three hours); pages start at 0, max 100.
- `PUT /rest/order/v1/update` only does `Created -> Picking`.
- In a line, `stockCode` is the seller's code (matching uses this); `productId` is n11's and useless here; `orderLineId` is the id needed for confirmation.
- `mallDiscount` is NOT counted: it is n11's money, paid to the seller as if it did not exist. Only `sellerDiscount` and `sellerCouponDiscount` are this order's discount.
- Prices are decimal and a comma may appear.

## How far does one order pull read? (`mp_n11_pull_orders()`)
- At most 20 pages (2,000 packages) a pass, asked in ascending `lastModifiedDate` order. At the cap `read_to` is the last read package's `lastModifiedDate`, not now; the next pass starts there (minus the 5-minute overlap) and reads on.
- Symptom before the fix (#1011): with more than 2,000 packages in the window the cursor jumped to now and packages 2,001+ were never imported.
- If 20 full pages end inside the overlap (no progress), it reads on up to 100 pages; if even that does not get past the start, `complete` is false and the cursor stays.
- Tests: `tests/marketplace_n11_test.php` (transport parameter, like Trendyol's).

Related: docs/memory/commerce/marketplace-order-status-sync.md
