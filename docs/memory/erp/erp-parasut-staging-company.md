---
name: erp-parasut-staging-company
description: Paraşüt's staging company 35477 in a live round (2026-11-27): e-Arşiv jobs end in error, no e-Invoice recipient, shared rate limit, Cloudflare challenge, settings a round needs
module: parasut
---

## What does e-Arşiv do on staging?
- `POST e_archives` answers 202 with a job, and every job ends `error` with "e-Belge entegrasyonunuz ile ilgili bir hata oluştu, lütfen bizimle iletişime geçin" (2026-11-27). Ask After the Status puts the row back to `created` and says to hand it to GİB again (`erp-parasut-send-failures.md`).
- So the e-document PDF, the cancel of a sent e-document and GİB's verdict (a 0 % line without an exemption code too) cannot be tested there.
- Staging has no e-Invoice recipient: every send goes e-Arşiv, and e-Fatura stays untested.
- The order screen's legacy Create Invoice takes that 202 as accepted and follows the job, as the driver does (`erp_edoc_parasut_ok()`, #12013); before, it said "…but e-document failed: HTTP 202". E-Shipment has no conversion step.

## Which user right does a round need?
- The Paraşüt user's integration permission: `erp-parasut-devhub-connection.md`. Many 401 or 403 answers can get an OAuth client switched off at Paraşüt: a round stops at the first one and finds the cause before it goes on.

## Why did 429s come in a round?
- Paraşüt's limit, 10 requests per 10 s, is per company and client: every worker of a round on one company shares it, and a per-worker pace does not keep under it. 429s were frequent; the one retry after a 429 recovered each (`erp-parasut-driver-2.md`).

## Why does a browser on Paraşüt's screen get 403?
- `uygulama-staging.parasut.com` sits behind a Cloudflare managed challenge ("Just a moment..."). An automated browser's delete of a purchase bill got that 403 challenge page; the same `DELETE` through the API answered 204. Such a 403 is Cloudflare, not a missing right.

## What else does a round need to know?
- Card reuse on the legacy buttons (`erp-parasut-legacy-buyer-card.md`) needs the site's "TC/VKN field" setting to name a field: with "do not use" the order has no number and every press opens a new card, so #2441 could not be tried.
- The legacy buttons are hidden on the order screen while the ERP's provider is on; the round called their functions from the command line.
- A product card used on an invoice cannot be deleted on Paraşüt's screen, even after the invoices are gone ("Bu ürün bir veya birden fazla faturada kullanılmış."): it can only be archived; the archived card was then deleted through the API.

## Where are the round's results?
- Each topic file's "How was it checked live?" names what passed on 2026-11-27 (dev at 586b3e9250). Open bugs and bigger work: `plans/erp-parasut-open-work.md`.
