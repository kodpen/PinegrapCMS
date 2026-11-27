---
name: accounting-sync-census
description: QuickBooks and Xero sync AS1 census: the answer in short, what a failed send shows, the owner's questions; parts on Paraşüt, QuickBooks, Xero, the source and the proposal
---

## What is this census?
- Step AS1 of `accounting-sync.md`: what the code has today and what the two services' developer pages say, written for the architect and the owner. Paths are relative to `pinegrap/`. Nothing is built and no schema is changed.
- Parts: this file (the answer in short, failed sends, the owner's questions), `accounting-sync-census-parasut.md`, `-quickbooks.md`, `-quickbooks-sale.md`, `-xero.md`, `-xero-sale.md`, `-source.md`, `-proposal.md`. Pages that could not be read are listed at the end of the two `-sale` files.

## What is the answer in short?
- Paraşüt: its sign-in, sealed token, renewal under a lock and the company read after connecting are the shared pattern; its one column per service and record is not.
- QuickBooks Online: a paid order is a SalesReceipt (else Invoice and Payment), a refund a RefundReceipt; production keys need Intuit's App Assessment; writes are free under the App Partner Program.
- Xero: an invoice and a payment, a refund a credit note; an app of one site fits the free Starter tier; Custom Connections close to new apps on 2026-12-01.
- Source: the order on a site without the ERP module, the ERP invoice on a site with it.
- Proposal: out only, sent from a queue by the job, paired in one new table (asked, not built).

## What is shown when a send fails?
- An "Events" card on the service's Connections page, as for ad conversions (`api/ad-conversions-log.md`): daily counts and the last 50 sends with the service's own words, and Try again on a failed one.
- One line on the View Order screen, or on the ERP invoice: "Sent to Xero", "Waiting to be sent" or "Not sent:" with the reason.
- An operations alert once a day when sends fail and none pass, as `ad_events_refused`. A connection marked `reauth` holds the queue, and Connections says it has to be connected again.
- Retries on `api_webhook_backoff()` (`includes/api/outbound/webhooks.php` 944); a refusal that will not change (a duplicate name, an unmatched tax code) fails at once.

## What are the owner's questions?
- May the pairing and the send queue be one new table, or should they be columns on orders, products, invoices and customers?
- With the ERP on, is the ERP invoice the source, and does an order without an ERP invoice stay unsent?
- Does each shop register its own Intuit and Xero app (model A, as Paraşüt), or does the product run one app for every site (model B, `plans/reverse-oauth-leftovers.md`) and pay Intuit's and Xero's tiers?
- A paid order in QuickBooks: a SalesReceipt, or an Invoice and a Payment?
- A paid order in Xero: an invoice and a payment, or a bank transaction?
- One record per order, or one summary of each day's sales?
- Each buyer as a customer in the books, or one "Web sales" customer?
- Each product as an item in the books, or one general item?
- Is stock kept in the books as inventory items, or only on the site?
- Are past sales sent once on connecting, and from which date?
- A sale in another currency: sent in its currency (multicurrency, which cannot be turned off in either service), or in the home currency?
- In the US, does the service work out sales tax, or is the site's tax sent as it is?
- One accounting service per site, Paraşüt included, or several at once?
- Both services, and which first?
