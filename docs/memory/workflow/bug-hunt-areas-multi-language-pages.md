---
name: bug-hunt-areas-multi-language-pages
description: Bug hunt 32, multi-language pages (#PR in the body): the language lost on a payment's return and in the form sender's subject, what was read clean, what needs an order language column
---

## Which area was it?
- A site with more than one language: the language switcher, hreflang and canonical, the sitemap, the language kept through a form, search and the cart, mails in the visitor's language. Hunt 32, PR "[iş] Bug hunt — multi-language pages".
- Files read: `includes/fn/widgets_language.php`, `includes/translate/seo.php`, `render.php` (links, alternates, finalize), `includes/fn/translate.php`, `includes/fn/language_redirect.php`, the router's language directory, `get_sitemap_info.php`'s hand-over, `includes/fn/form_mails.php`, `order_completion.php`'s receipt, the payment return addresses.

## What was fixed?
- A checkout paid through a service that sends the visitor away (3-D Secure, iyzico, Stripe, PayPal, PayTR) from a page in /en/ came back to `order_preview.php` / `express_order.php` without pg_lang: the order was completed, its receipt mailed and the receipt page shown in the source language. The return address now goes through `pg_tr_script_url()` (`commerce/payment-return-language.md`).
- The same for a form's payment step (`custom_form.php` return) and a booking deposit (`booking_slots.php` return); PayPal Express Checkout's cancel address and PayTR's ok/fail pages use `LANGUAGE_PATH`.
- The subject of the e-mail a form sends its sender stayed in the source language while the body was translated: `pg_tr_extract_forms()` now offers `custom_form_pages.submitter_email_subject` for translation, and `form_mails.php` reads it through `pg_tr_text()` before the field values go in. Tests: `tests/multi_language_pages_test.php`.

## What was read and found clean?
- The switcher links the same page in each language (catalog items and record views below a page too); a file or unknown address goes to the language's home; pg_lang is dropped from the query.
- hreflang: the source page and each language copy name the same set (`pg_tr_index_decide()` for the drawn language, the kept answers for the others), x-default on the source, none on a noindex page.
- sitemap.xml: each language address once, an already prefixed address in the additional content gets no second copy; robots.txt rules repeated per directory.
- A POST form carries pg_lang and its redirect gets the language's directory; a GET search form keeps the page's own address.

## What is left as a finding?
- An order or a form record completed without the visitor's request (a payment service's server notification, a fee paid later) mails in the source language: no table keeps the language an order or record was placed in. Needs a schema decision.
