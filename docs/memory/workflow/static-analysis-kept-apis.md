---
name: static-analysis-kept-apis
description: Removed PHP APIs kept on purpose (mcrypt, pfpro, libxml_disable_entity_loader): phpcs:ignore marker or baseline entry; the brace-less foreach false positive
---

## Which old APIs stay in the product, and how are they marked?
- `mcrypt` in `_pg_decrypt_legacy_credit_card_number()` (`includes/fn/ecommerce.php`) decrypts card numbers stored before OpenSSL; it is guarded by `extension_loaded('mcrypt')`/`function_exists('mcrypt_decrypt')` and kept while PHP 7.1 is supported (decision #3150). Its three lines carry `phpcs:ignore` markers.
- `pfpro_process()` (`submit_order.php`, extension gone since PHP 5.1) stays: Payflow Pro is frozen there (`commerce/payment-driver-layer-decisions.md`), so its findings stay in both baselines, never marked.
- `libxml_disable_entity_loader(false)` in `pg_office_zip_stream()` (`includes/fn/office.php`, deprecated in PHP 8.0) is guarded by `function_exists()` and marked: it undoes PHPExcel's switch-off, which stops XMLReader opening an `.xlsx` later in the request (`erp/erp-bank-statements.md`).
- PHPCompatibility reads `break` in a brace-less `foreach (...) if (...) { ...; break; }` as "break outside loop" (a fatal error). Put braces round the loop body; `custom_form.php` was fixed so.

## Why a marker and not a baseline entry?
- A marker covers only its own line and names the guard beside it, so an unguarded call added elsewhere in the file fails CI; a baseline count per file and sniff would let a new call through in place of a fixed one. Only `pfpro`, which is frozen rather than guarded, stays a baseline entry.

Related: docs/memory/workflow/static-analysis.md, docs/memory/core/deprecated-resource-free-calls.md, docs/memory/workflow/decisions-made-product-3.md
