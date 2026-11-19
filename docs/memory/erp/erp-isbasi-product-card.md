---
name: erp-isbasi-product-card
description: How İşbaşı keeps a product card per item code and reads the withholding from it, and why a withholding line's item code names its withholding (2026-11-17).
---

## What does İşbaşı do with a line's item code?
- `productDetail.itemCode` names a product card. The first line with a new code opens the card with that line's `productDetail` (name, unit, VAT, withholding); later lines with the same code use the card.
- An empty item code opens a new card with a random code on every line (seen in the test environment); not changed yet.

## Where does İşbaşı read the withholding from?
- From the product card, not the line: a code first seen without withholding keeps none, and a later withholding line with that code goes out without it; a card opened by a withholding line puts it on every later line with the code. The card is never updated through the integration (test environment, 2026-11-17).
- The VAT rate and the exemption code are read from the line, so they need no such care.

## How does the driver keep the withholding right? (`erp_edoc_isbasi_item_code()`)
- A withholding line's item code ends in `-T<code>-<rate>`: SKU `ABC-1` with 624 at 20 → `ABC-1-T624-20`; a line without SKU or product → `PG-T624-20`; the SKU is cut so the whole stays within 50 characters (`ERP_EDOC_ISBASI_ITEM_CODE_LENGTH`). Each withholding has its own card, so the card's withholding is always the line's.
- Lines without withholding keep the SKU or `PG-<product id>`, and an empty code stays empty.
- Before this, a product sold once without withholding and then with it went out without the withholding while the ERP counted it: the e-document and the ERP disagreed on the total.

Related: docs/memory/erp/erp-isbasi-invoice-payload.md
