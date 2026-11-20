---
name: erp-order-overview
description: Start file for the erp-order-* files: an order's ERP documents (invoice, cancel, refund), its charges and VAT, and its cost rows and net profit, which file holds each part
---

## What is it?
- An order's side in the ERP (accounting) module: the documents it gets (invoice, cancel, return invoice), what its invoice holds, and, since 2026.11.0, its cost rows and net profit.

## Which decisions bind it?
- A contact card's tax number not on the order's ERP account holds the automatic invoice (owner decision 2026-10-23, #1307): `erp-order-tax-number-difference.md`.

## What does a person see?
- The order screen's documents card: the four documents, their step states and the one Next button: `erp-order-documents-flow.md`.
- Its invoice cancel and re-invoicing after a cancel: `erp-order-card-invoice-cancel-rules.md`.
- A cancelled or refunded order's two reverse steps and the card refund by line: `erp-order-cancel-and-refund.md`; the refund by lines on an order with no ERP invoice: `erp-order-refund-lines-noted.md`.

## Where is each part?
- The invoice header (amounts, charge lines, gift card, the checks before a number is spent): `erp-order-invoice-header.md`.
- Shipping, surcharge and instalment charges and their VAT: `erp-order-charges-vat.md`.
- Order profit (the order-profit plan, NP steps):
  - The cost tables, the calculation and the resumable pass: `erp-order-profit-record.md`.
  - The fee a payment service kept: `erp-order-profit-payment-fee.md`.
  - Marketplace (Trendyol) deductions: `erp-order-profit-deductions.md`.
  - Label prices as the shipping cost: `erp-order-profit-label-price.md`.
  - The order's Profit card and the orders list: `erp-order-profit-screens.md`.

## How is a part added?
- One file per part with the `erp-order-` prefix, named here under the screen or part it belongs to; the order-profit plan's close-out step keeps this file current.
