---
name: marketplace-buybox-read
description: The hourly buybox read (2026.11.0): the Trendyol connector ability buybox, its body, header and limit, the job pass, and what marketplace_price_state keeps
---

## How is the buybox read from Trendyol?
- Connector ability `buybox($account, $barcodes)` (`mp_trendyol_buybox()`, `connectors/trendyol.php`): POST `/integration/product/sellers/{sellerId}/products/buybox-information`, body `{"barcodes":[…]}`, header `storeFrontCode` (`mp_trendyol_storefront()`: the credentials' `storefront`, two letters, else `TR`).
- At most 10 barcodes a call (`mp_trendyol_buybox_size()`); their service allows 1000 calls a minute. Barcodes their rules refuse are not sent and come back in `data['refused']`.
- Answer read (`mp_trendyol_buybox_rows()`): per `buyboxInfo` entry `buyboxOrder` (our rank, 1 = ours), `buyboxPrice` (minor units), `hasMultipleSeller`. A rank over 1 always counts as other sellers. An entry with no rank or a negative price is dropped. `secondBuyboxPrice` / `thirdBuyboxPrice` exist but are not read.
- n11 has no buybox ability; Hepsiburada joins by naming `buybox` in its connector, without touching the job.

## When does the job read it?
- `mp_sync_buybox()` in `sync.php`, last in `mp_sync_run()` (after the listing states), for every active account whose connector names `buybox`. No account switch: a read changes no price.
- Due listings (`mp_sync_buybox_due_sql()`): mapping `active`, approved (`remote_id` set), a barcode, product enabled, not flagged out of stock, tracking stock with quantity over 0, and `marketplace_price_state.buybox_at` an hour old or more (or no row), the oldest first. Products that do not track stock are left out: they are sent as quantity 0.
- At most 50 calls (500 listings) per account per pass, inside the pass's deadline; the job runs every 5 minutes. A failed call writes the account's error (`mp_account_outcome()`) and stops that account until the next pass.

## What is stored per listing?
- `marketplace_price_state` upsert (`mp_sync_buybox_state_sql()`): only `buybox_price`, `buybox_ours`, `buybox_at`, `updated_at`; a new row gets the other columns' defaults.
- `buybox_ours` packs two things (`mp_buybox_ours_value()` / `mp_buybox_ours_read()`): the low 7 bits our rank (capped at 127, 0 = no read), bit 128 (`MP_BUYBOX_OTHERS`) other sellers. 1 = ours alone, 129 = ours with others, 131 = third.
- A listing asked about but not answered (or refused) gets `buybox_at` = now with price and rank 0, so it waits an hour instead of being asked on every pass.

## What is tested?
- `tests/marketplace_buybox_test.php` with `tests/fixtures/trendyol/buybox-information.json` (the documented shape): body, header, stage address, the 10-barcode limit, refused barcodes, a 429, the packing, the stored state, the due query.

Related: docs/memory/commerce/marketplace-channel-price.md, docs/memory/commerce/marketplace-outbound-layer.md, docs/memory/plans/marketplace-repricer-features.md
