---
name: market-table-stakes
description: Plan (owner 2026-11-17): what the Turkish market's lists expect and the product lacks: a Google XML product feed now (TS1); the price sites, Craftgate and the integrators after 2026.11.0
priority: 4
---

## What is asked?
- Owner (2026-11-17, agent channel message 7359): "entegrasyonu kolay ama getirisi büyük entegrasyonları kontrol edelim."
- Owner (2026-11-17, typed to the architect, message 7562): "entegrasyonlar ile ilgili olumlu düşünüyorum ancak 2026.11.0 yayınlanmadan önce bir test demosu açamayabilirim, bağdaştırıcı hazırlıklarımızı yaparız ancak 2026.11.0 yayınlanması sonrasına bırakırız. bugün engel olmayanları 4/10 öncelikle onaylarız."
- The study: what Turkish platforms list and the product lacks (`integrations-census-others.md`), and the services where one integration brings many (`integrations-census-aggregators.md`).

## What is there today?
- Product feeds in three formats, `openai_jsonl`, `openai_csv` and `google_tsv`, built daily and served at private addresses (`commerce/product-feeds.md`, `commerce/product-feed-serving.md`). No feed of the whole catalogue is XML, and third parties' pages tell that Akakçe and Cimri take a shop's products as an XML feed.
- Payment drivers for iyzico, Stripe, PayPal and PayTR (`pg_payment_builtin_drivers()`); marketplaces n11 and Trendyol, Hepsiburada planned (`marketplace-trendyol-hepsiburada.md`).

## Which steps are there?
- TS1 (M) A fourth feed format, `google_xml`: Google's product data as RSS 2.0, by Google's public specification, from the same items and rules as `google_tsv`.
  - Existing files: `includes/fn/product_feeds.php` (a writer that streams like the others, `commerce/product-feed-streaming.md`), the Product Feeds card, `feed.php`, `GET /store/feeds` (a fourth row), `tests/product_feeds_test.php`.
  - State in `config.product_feeds` as the other formats: no schema change. The card's new switch is the architect's look.
  - It needs no account. A third party states that Cimri reads this format from an address; whether Cimri and Akakçe take it as it is, is TS2's.

## What waits for the release of 2026.11.0?
- TS2 (S, with the owner's or a customer's store account) Akakçe and Cimri. No document of either's own format was found in public; both take a shop only after an application with company papers. With an account at each, the format it asks is read from its own document and a feed is handed in: `google_xml` as it is where that is taken, else a format of its own in the builder. Needs of him: a store application at each, or a customer who has one.
- TS3 (M, with the owner's sandbox membership) A Craftgate payment driver after the pattern of `includes/payments/` (`commerce/payment-driver-layer-decisions.md`): one driver for the banks' virtual POS and the payment firms Craftgate reaches. Its developer portal opens only with a sandbox merchant's sign-in and no public test key exists, so the documents and a run both need the membership. Needs of him: he asks Craftgate for one.
- TS4 (S, the owner's application) The integrators' kit: the API documents, a demo site and the product feed, with which he applies to Entegra, Sopyo, Dopigo and StockMount; their side builds. Needs of him: the demo site and the applications.
- TS5 (S) after TS2, TS3 and TS4: close-out, the lasting rules into `commerce/`, this plan deleted.

## Whose look?
- TS1's switch and TS3's settings card: the architect's (agent channel message 2645).

## What is left out?
- Hepsiburada: its own plan (`marketplace-trendyol-hepsiburada.md`). Amazon, PttAVM, Çiçeksepeti and Pazarama one by one: they come through an integrator (TS4).
- Each bank's own virtual POS, Sipay, Param, Papara: Craftgate first; their documents were not reached.
- A connector for Dopigo's public API: it needs a Dopigo account to be tried; named again after TS4.
- Desktop accounting programs (Mikro, Netsis, BizimHesap).
- Claim as `[plan:market-table-stakes] <step> — <topic>`.
