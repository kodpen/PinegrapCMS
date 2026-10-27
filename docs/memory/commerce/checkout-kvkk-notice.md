---
name: checkout-kvkk-notice
description: KVKK notice link and optional explicit consent box at checkout (11.0.48): settings, why a page and not a template, injection, the kvkk copy in order_documents, consent log row
---

## What does the checkout show?
- With the legal documents switch on, `pg_legal_documents_inject()` adds `pg_legal_kvkk_html()` in front of the purchase button: a sentence linking the notice page (`config.ecommerce_kvkk_page_id`, 0 = none) and, when `config.ecommerce_kvkk_consent_text` is set, a checkbox `legal_kvkk` (value 1) labelled with that sentence. Neither set: nothing. The box is never `required` and `submit_order.php` never checks it: explicit consent cannot be a condition of the order.
- Added once per page (marker class `pg-legal-kvkk`), also to designer trees that bind the two document boxes themselves; the designer has no KVKK node or token yet.
- Settings › Ecommerce › Legal Documents: page select and consent textarea inside the switch's popover (`includes/settings/commerce.php`, saved only when the column exists). Constants `ECOMMERCE_KVKK_PAGE_ID`, `ECOMMERCE_KVKK_CONSENT_TEXT` (`init.php`); `pg_legal_kvkk_settings()` reads them, '' when the switch is off or the page is gone.

## Why a page and not a template like the two documents?
- The `config` row's room on MySQL 8.0: two more TEXT columns plus a switch broke the ten-long-columns margin of `tests/innodb_test.php` (`data/config-table-no-varchar.md`). An INT page id plus one TEXT fits. Sites already publish their KVKK notice as a page; no switch is needed either (page 0 and an empty sentence = off), so a store that already uses the documents sees nothing new until it chooses.

## What is kept with the order?
- `pg_legal_documents_store()` adds a `kvkk` row beside the two documents: `pg_legal_kvkk_copy_html()` = the linked page's full address and the consent sentence as shown (not the page's text). `accepted_at` only when the box was shown and ticked; with no sentence a posted `legal_kvkk` is dropped. A ticked box writes a granted consent log row (channel `contract`, purpose `kvkk`) through `pg_legal_documents_consent_events()`.
- `pg_legal_document_types()` stays the two required documents; `pg_legal_document_labels()` adds `kvkk` ("Personal Data Notice") for the kept-copy page, e-mail attachments, the panel card ("No consent", not red) and the API `label`. `pg_legal_document_acceptance_note()` speaks of consent for `kvkk`. `legal_document.php?type=kvkk` exists only with an `order_id`.

## What is left?
- plans/checkout-legal-documents.md: the registration form's KVKK lines, a designer node/token, the order view link to the kvkk copy.
