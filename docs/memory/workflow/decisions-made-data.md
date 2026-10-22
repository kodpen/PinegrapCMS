---
name: decisions-made-data
description: Decisions list, data part: starter dumps, orders.notes and tracking_company, user.secret_key*, order_items.tax, orders.tracking_code, fixing a released migration, MySQL 5.7 support.
---

## Which columns and dumps stay as they are?
- `data/backups/` (the starter site dumps): content and layout are not touched. Exception (2026-09-21): their credential fields stay empty: `usps_user_id`, `ups_user_id`, `ups_password`, `ecommerce_authorizenet_api_login_id`, `ecommerce_authorizenet_transaction_key`. The repository is public; no value is ever written there (#62 item 4).
- `orders.notes`, `orders.tracking_company`: reserved for the ERP module, which will add them with its own migration. Do not drop them, reuse them or fill them from another feature; a carrier goes on the shipment row. No migration or starter dump creates them yet, so they exist only where added by hand: `tracking_company` is read by nothing, and every reader or writer of `notes` probes for it first (the express order note in `submit_order.php`, the order view widget, `PATCH /orders`, Workspace record changes).
- `user.secret_key`, `secret_key_iv`, `secret_key_hash`: kept on purpose when the old app API went in 2026.4.4 (dropping columns off the account table was not worth the risk); no code reads them (`api/removed-rest-api-apps.md`).
- `order_items.tax`, the old per-unit tax: the shop has not written it since 2026.4.4 (step 4.44), new rows hold 0. The line tax is `order_items.tax_total`: `SUM(tax_total)`, never times the quantity (`commerce/product-tax-rate.md`). Seeing 0 tax usually means the code reads `tax`. Kept for older rows; no migration drops it (the "2026.5.0" once named for that never existed, 2026.11.0 does not), and dropping it is a schema step for the owner to approve. Still on the old column (2026-10-21): the order items CSV export in `view_orders.php`, and the marketplace import, which writes the connector's line `tax` there (always 0 from n11).
- `orders.tracking_code`: a campaign code (`?t=` visitor tracking, `get_tracking_code()`), nothing to do with shipping. Parcel numbers live in `shipping_tracking_numbers`.

## Which migration and server rules are decisions?
- A released version's migration may still be fixed for the sites that have not taken it yet (an installation past that version never runs it again): `upgrade_to_2022_2()` now calls `install_drop_column_default()` (issue #464, PR #470). What the version brings, its `versions.php` row and its changelog heading stay fixed (`release/released-vs-open-version.md`).
- MySQL 5.7 stays supported, with MariaDB 10.2 and later: syntax that runs only on newer servers is not used. MySQL 5.6 and MariaDB 10.1 are refused before a table is touched (`release/database-server-compatibility.md`).
