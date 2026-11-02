---
name: small-features-2026-11
description: Plan (owner 2026-11-02): eight small independent features: address autocomplete, accessibility statement, search tuning, product questions, pre-order, EU VAT, offline page, chat channels; SF1-SF8
---

## What is this plan?
- The small items of the November gap review, each one or two PRs, none depending on another: a coordinator hands them to its five workers at once (`feature-plans-2026-11.md` says how a plan is held). Each step owns its own files; the shared lists (`pg_js_bundles()`, settings cards, the API schema) get one appended row.
- Global, as every plan of the review: nothing here is built around one country; the EU VAT step applies only to sites that sell into the EU and changes nothing elsewhere.

## Which steps, in one line each?
1. SF1 Address autocomplete: a provider driver (Google Places, Mapbox, Photon) behind a site endpoint, filling the address boxes of checkout, the account's address book and the panel forms; the key never reaches the browser. `commerce/address-labels-by-country.md` is the base; `plans/address-fields-by-country.md` left it out on purpose.
2. SF2 Accessibility statement: a system widget that writes the statement the EU Accessibility Act and EN 301 549 ask for from the nightly report (`platform/accessibility-report.md`) and a settings card.
3. SF3 Site search tuning: a daily rollup of search terms and zero-result searches (today only `visitors.site_search_terms` on the visit, `visitors/site-search-terms-on-visit.md`), synonyms and promoted results, a Visitors section.
4. SF4 Product questions: a question-and-answer thread on product pages on the comments system (`designer/comments-widget-product-page.md`), the staff answer marked, the asker mailed.
5. SF5 Pre-order: a product sold before its stock arrives, with an expected date on the catalog, cart, order and mails; built on `products.backorder`.
6. SF6 EU VAT: the VAT id checked against VIES at checkout and on the account card; a valid id from another member state sets the order's existing tax-exempt flag (reverse charge) with the note the invoice needs.
7. SF7 Panel offline page and install hint: the panel service worker (`sw.js`, which caches no page by decision) answers a static offline notice when the network is gone; an install hint in the account menu; an optional manifest for the site.
8. SF8 Chat notification channels: Slack, Microsoft Teams and Telegram as targets of operations alerts and chosen Workspace notifications, through the outbound webhook queue.

## Which decisions bind it?
- Legacy and totals: SF5 and SF6 change no totals code; pre-order rides the backorder path, reverse charge the `tax_exempt` flag the billing widget already posts. Every outside call goes through `pg_curl_run()` with a sealed key; a provider that fails leaves the box as typed.
- Texts through `tr_add.php`; each step writes its memory topic file and tests; a schema change is one sub-step of the open version's migration, asked first.

## Which questions are decided? (#5047, #5065)
1. SF3: `site_search_daily` (`sha1(date|term)`) and `config.search_synonyms` / `config.search_promoted` TEXT JSON; shape in the issue.
2. SF4: `comments.kind` ENUM(comment, question, answer) and `comments.answer_of`.
3. SF5: `products.preorder` TINYINT and `products.preorder_date` DATE.
4. SF6, owner: a VIES-validated id sets the tax-exempt flag on its own; the id and the check time are written on the order.
5. SF7 site manifest (#5065): no switch and no column; `site.webmanifest` is linked and served while the site icon is set (`platform/site-icon.md`); a follow-up adds at most `theme_color`.

## Where does it start?
- SF1, SF2, SF7 and SF8 need no answer and start at once; SF3-SF6 after their question. Steps: `small-features-2026-11-steps.md` (SF1-SF3), `-steps-2.md` (SF4-SF8).
