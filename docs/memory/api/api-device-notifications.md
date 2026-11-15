---
name: api-device-notifications
description: Which notifications an API caller (device session, OAuth connection, key) sees: new_order and order_return only with orders:read, in lists, unread counts and read targets
---

## Which notifications does an API caller see? (#2281)
- `pg_notification_visible()` with `api_notifications_rights()`, whose `orders_read` says the call's scopes hold `orders:read`: without it `new_order` and `order_return` leave the list, every unread count (`/auth/me`, `/workspace/sync` too) and the read targets. A device without Orders listed notices whose order answered 403.
- The panel and push sender pass no such key: unchanged. `out_stock` follows the e-commerce right alone.
