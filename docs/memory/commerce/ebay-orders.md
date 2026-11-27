---
name: ebay-orders
description: eBay orders read on the schedule (getOrders by last modification) into mp_import_order() in the order's own currency, and a shipment sent back with carrier and tracking number
module: ebay
---

## How do eBay orders come in?
- By the job's pull only (`pull_orders`, `mp_ebay_pull_orders()`); eBay's order notices are not subscribed.
