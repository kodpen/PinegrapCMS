---
name: kvkk-notice-switch
description: The KVKK notice's own switch (Show the KVKK notice, config_text ecommerce_kvkk_enabled, step 11.0.271): checkout and sign-up follow it, apart from Legal documents
---

## Which switch shows the notice?
- Owner (2026-11-21, poll 214, `workflow/decisions-made-product-96.md`): its own. `config_text` `ecommerce_kvkk_enabled` '1' or '0' (`pg_legal_kvkk_enabled()`, pure `pg_legal_kvkk_switch_on()`); no row follows the Legal documents switch, as before. Step 11.0.271 (`upgrade_2026_11_0_kvkk_switch()`) writes the row from `ecommerce_legal_documents` with INSERT IGNORE, so no running site changes and a rerun keeps a later choice.
- Before #11096 the lines worked only while Legal documents was on. Now sign-up forms follow the KVKK switch alone; the checkout draws the KVKK lines while either switch is on (`pg_legal_documents_inject()`, the documents' boxes and script only with theirs), and `pg_legal_documents_store()` keeps the `kvkk` copy alone when the documents are off; `submit_order.php` keeps `legal_acceptance` when either is on.

## Tests?
- `tests/legal_documents_test.php` (`test_legal_documents_kvkk_switch_on`, `…_kvkk_switch_update_step`), `tests/settings_privacy_fold_test.php` (the row's switch and pill).

Related: docs/memory/commerce/checkout-kvkk-notice.md, docs/memory/ui/settings-privacy.md
