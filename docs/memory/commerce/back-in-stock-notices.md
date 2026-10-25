---
name: back-in-stock-notices
description: Back-in-stock notices (includes/fn/stock_notify.php, stock_notify.php): who may ask, the request rules, the inventory.changed listener, claim then mail, queue vs inline, trim, export, delete hooks
---

## Where is it kept?
- `stock_notify` (11.0.14): id, product_id, email_address VARCHAR(100), user_id, phone (empty until SMS, step 4c), created_at, sent_at (0 = waiting). KEY (product_id, sent_at); no unique key: duplicates are refused in code.
- `includes/fn/stock_notify.php` (PR #1659); before the table, `pg_stock_notify_ready()` is false and all answers are empty.

## Which product can be asked for?
- `pg_stock_notify_product_out()`: enabled, `inventory = 1`, `inventory_quantity <= 0`, no `backorder` (the catalog widgets' `__out_of_stock`); back-orderable is `in_stock`.
- Back (`pg_stock_notify_product_back()`): enabled and stock > 0, or untracked; a back order is no stock.

## What does a request answer?
- `pg_stock_notify_request($product_id, $email, $user_id)` → `pg_stock_notify_request_status()`, checked in this order: `unavailable` (no table), `email` (`pg_stock_notify_email()`: trimmed, one line, ≤ 100, `validate_email_address()`), `product` (none or switched off), `in_stock`, `already` (the address, or the account, waits for it), `limit` (`PG_STOCK_NOTIFY_PENDING_LIMIT` = 50 waiting per address), `added`.
- Signed in with no address typed: `USER_EMAIL_ADDRESS`. `pg_stock_notify_status_ok()`: `added`, `already`; `pg_stock_notify_status_message()`: the sentence.

## What does the endpoint do?
- `stock_notify.php`, POST `product_id`, `email_address`, `token`, `send_to`. Checks: body size, method, token (`hash_equals`), id, rate (`pg_stock_notify_throttled()`: 10 / 10 min per `waf_ip_subject()`, logged `rate-stock-notify`), then the write.
- XHR gets JSON `{status, ok, message}`; a form goes back to `send_to` (`pg_safe_redirect_path()`), the sentence in the `catalog_detail` liveform, which the product page's Messages node shows.
- It makes no stored page old (`pg_page_cache_request_dirties()`).

## When does the notice go out?
- `pg_stock_notify_heard($event, $payload)` hears every `inventory.changed`: from `pg_announce()` (checkout, local sale, product screens, barcode, bulk edit, file manager, Workspace, ERP documents) and from the stock API right after its own `api_webhook_enqueue()`. Imports (products, marketplace orders) and ERP stock counts announce nothing, so mail nobody (plan decision (c)).
- `pg_stock_notify_heard_product()` (pure) takes an `inventory.changed` whose `out_of_stock` is false, not from `checkout`/`local_sale` (a sale brings nothing back; no checkout waits on mail). Nobody waiting: one indexed read. Under `pg_error_throws(true)` in try/catch: a failed query is logged, never breaks the writer.
- `pg_stock_notify_send()` reads the product again, then per waiting row: claim (`UPDATE … sent_at … WHERE … AND (sent_at = 0)`, one affected row) and only then `email()`. Two announcements at once mail once; one address on two rows (guest and account) gets one mail; an invalid address is claimed and dropped.
- Queued (`email(…, 'queue' => true)`) while `pg_mail_job_alive()`: 500 per announcement; without the job, sent in the request: 10. The rest wait for the product's next `inventory.changed`.
- `pg_stock_notify_mail()`: the name (`short_description`, else the SKU) escaped, a link from `pg_stock_notify_url()` (HOSTNAME_SETTING, catalog detail page, address name) when both exist; the site's language.

## What removes rows?
- Daily (`pg_stock_notify_trim_daily()`, `job.php`, `data/temp/stock_notify_trimmed.txt`): sent rows after 30 days, waiting rows after a year, batches of 1000.
- `pg_stock_notify_forget_user()` (`delete_users.php`, `edit_user.php`) and `pg_stock_notify_forget_product()` (`pg_pb_delete_product()`): an id MySQL 5.7 hands out again never inherits requests.

## Where else is it?
- Personal data export: section `stock_notify`, by account or address ([[personal-data-export]]). The item view's field: [[catalog-stock-notify-form]].
