---
name: express-order-legal-documents
description: Checkout document boxes and KVKK lines in the designer's express order tree: legal_* field bindings, has_legal_documents / has_legal_kvkk* flags, __legal_* tokens, sentence split of the label
---

## What does the designer's express order tree carry?
- The starter tree (PHP `_eo_default_designer_tree()`, JS `_buildExpressOrderStarterTree()`, lockstep) puts a `pg-eo-legal-documents` container in front of the terms row of the summary card: two required checkboxes, `_bindings.eo_field` = `legal_preinfo` / `legal_contract` (value 1), each label linking to its document.
- The container has `eo_visible_if = has_legal_documents` (`pg_legal_documents_enabled()`, set in `_render_system_widget_express_order()`): with the documents off the boxes leave the tree, otherwise their `required` would block a checkout the server does not ask them for.
- Once the rendered page carries `name="legal_preinfo"`, `pg_legal_documents_inject()` adds no second pair; it still records the snapshot for the document page and loads the script that makes the links post the typed values (commerce/checkout-legal-checkboxes.md). Trees saved before keep getting the injected pair in front of the first purchase button.

## Where do the links point?
- The `<a>` carries `_bindings.href` = `__legal_preinfo_url` / `__legal_contract_url`, static tokens of `_eo_compute_static_tokens()` (`legal_document.php?type=…`, escaped there). The tree holds no path; the designer picks them under "Checkout documents" in the token list, and the URL field is hidden as for every bound href.

## In which language is the linked document read?
- In the checkout's: `pg_legal_document_cart_url($type)` (`includes/fn/legal_documents.php`) adds `pg_lang=<code>` while the page is drawn in a target language (`pg_tr_active()`), for these tokens and for the boxes `pg_legal_documents_inject()` adds (`?pg_lang=en&type=preinfo`). `legal_document.php` is not routed, so `pg_lang` (init.php) is how it learns the language; the script posts to the link's address, so the post keeps it.
- Before PR #1087 (issue #765) a visitor on `/en/...` read the source-language document ("Ön Bilgilendirme Formu") while the copy kept at submit was English ("Pre-Contract Information"). Test: `test_legal_documents_cart_url`.

## Why is the label built from one sentence?
- Turkish puts the document first ("… okudum ve onaylıyorum"), English last. The label is `span` + `a` + `span` cut from one key, `I have read and approve {var:1}` / `I have read and accept {var:1}`, at the `{var:1}` by `_eo_sentence_around()` (JS twin `_eoSentenceAround()`); an empty side is left out. No period: the renderer's line breaks between nodes would put a space before it. Tests `tests/designer_legal_documents_test.php`.

## What did the field list gain?
- `SW_EXPRESS_ORDER_FIELD_GROUPS` "Checkout approvals": `agree_terms` (the starter tree's terms box, which showed as Custom before), `legal_preinfo`, `legal_contract`. `_eo_known_input_names()` prefills both legal fields by name too.

## Where are the KVKK lines?
- Since PR #2648 both starter trees put a `pg-legal-kvkk` block after the documents container, under `has_legal_kvkk` (a notice page or a consent sentence is set): a `p` with the notice link (`__legal_kvkk_notice_url`, sentence `How your personal data is processed is explained in {var:1}` without its period) under `has_legal_kvkk_notice`, and a box `eo_field` = `legal_kvkk`, never `required`, its label bound to `__legal_kvkk_consent_html` (the store's sentence, escaped, line breaks as `<br>`), under `has_legal_kvkk_consent`.
- The values come from `_eo_legal_kvkk_tokens()`, fed `pg_legal_kvkk_settings()` through the renderer's state; the flags from the same settings. The class keeps `pg_legal_documents_inject()` from adding its own lines (commerce/checkout-kvkk-notice.md). `legal_kvkk` is in "Checkout approvals". Test `test_designer_legal_documents_starter_tree_carries_the_kvkk_lines`.

Related: docs/memory/commerce/order-legal-documents.md
