---
name: n11-api-facts
description: n11 REST API facts for the connector: headers, connection test, async price/stock task flow, REJECT vs IN_QUEUE, order endpoints and page cap, GMT+3 dates, discount fields, stockCode.
---

## How does the n11 API behave? (developer.n11.com)
- Host `api.n11.com`, headers `appKey` / `appSecret` (the docs write them two different ways in two services; the connector sends both). SOAP services are NOT used.

## How is the connection tested? (`mp_n11_test_credentials()`)
- GET `/cdn/categories`, the whole category tree, read with `mp_n11_category_tree_bytes()` (8 MB) like every call to that endpoint. 401/403 is reported as a refused key.
- Symptom before the fix (#1029): it read 4,000 bytes, so the right key failed with "n11 answered 200 with something that is not JSON" and the account's last error was written.
- `mp_n11_call()` reports a 2xx body that is not JSON and as long as its limit as cut off, and does not retry it (asking again gets the same cut). A 5xx/408 body of that length stays "not JSON" with its status and is retried, so a long gateway error page during an outage does not fail queued price/stock rows for good (#1029).

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
- At most 20 requests a pass, asked in ascending `lastModifiedDate` order. At the cap `read_to` is the last read package's `lastModifiedDate`, not now; the next pass starts there (minus the 5-minute overlap) and reads on.
- Symptom before the fix (#1011): with more than 2,000 packages in the window the cursor jumped to now and packages 2,001+ were never imported.
- If 20 full pages end inside the overlap (no progress), it reads on up to 10,000 entries new to the pass (re-read entries are not counted; at most 300 requests); if even that does not get past the start, `complete` is false and the cursor stays.
- After a full page the window starts again 1 ms before the last moment read, from page 0 (`mp_feed_next_page()`, `connectors/base.php`): inclusive or exclusive `startDate`, that moment comes back, and a package already handed over at the same moment is skipped (`mp_feed_first_time()`). Page numbers only walk a run of pages that all share one moment. Trendyol pages the same way.
- Symptom before the fix (PR #1459): a package read on one page that changed before the next page was asked for left the window, every later one moved up a place, and one package was never imported.
- Tests: `tests/marketplace_n11_test.php` (transport parameter, like Trendyol's).

Related: docs/memory/commerce/marketplace-order-status-sync.md
