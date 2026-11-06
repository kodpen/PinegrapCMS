---
name: storefront-api-missing-fields
description: Public api.php storefront actions (get_cross_sell_items, get_product, get_installment_options) answer malformed fields in JSON, never a warning or a 500; get_product answers enabled products only
---

## Why does this matter for the public actions?
- They run for signed-out visitors and bots (`'public' => true` in `pg_panel_actions()`). A warning prints the server path into the answer and breaks its JSON; every bad bot request lands in `php_errors.log`. Same class of fault as `get_styles` (#3961).

## How does get_cross_sell_items read its request?
- Defaults fill `products`, `product_group`, `recipient`, `catalog_detail_page` (arrays) and `number_of_items`. A source that is not an object (a bare id, text) counts as not sent; a `products` entry without a scalar `id` is skipped.
- So the modes of `cross_sell.src.js` reach their own branch: products, then product group, then recipient (needs the session's order), then the whole session order. Before #4746 a request without `product_group` stopped on a TypeError (HTTP 500), so the recipient and cart-page modes never ran (#4724).
- `limit`, `days` and `number_of_items` count only when numeric and above zero.

## What do get_product and get_installment_options answer without their fields?
- `get_product`: a missing, non-numeric or unknown `product[id]` answers `{"status":"error","message":lang('Product not found.')}`; `frontend.src.js` (and its `.min.js` twin) ignores an answer without `product`.
- `get_installment_options`: under Iyzipay, a card under 6 digits or a non-numeric price answers `lang('Card number and price are required.')` before the gateway is called. `eo_get_installments` answers the same text; `get_cross_sell_for_product` without an id answers `lang('Product is required.')`.

## Which products does get_product answer?
- Only `products.enabled = 1`. A switched-off product, or one in the Recycle Bin (binning sets `enabled` to 0), answers the same "Product not found." as an unknown id; the `enabled` column is read for the check and left out of the answer. Every variant chooser (designer `catalog_item_view`, legacy catalog detail) lists enabled products only, so no chooser asks for one.
- Before: any id was answered with texts, price, stock and photos, so a variant switched off before release, or deleted, could be read signed out by counting ids.

Tests: `tests/api_public_missing_fields_test.php`, `tests/storefront_get_product_enabled_test.php`.

Related: docs/memory/core/json-endpoint-rules.md, docs/memory/core/panel-action-table.md
