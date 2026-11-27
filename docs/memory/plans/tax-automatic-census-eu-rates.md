---
name: tax-automatic-census-eu-rates
description: Automatic tax census: where the EU's standard and reduced VAT rates can come from (TEDB web service, Your Europe table), on which terms, and a shipped list against a scheduled read
---

## Which official sources hold the rates?
- TEDB, the Commission's Taxes in Europe Database: a SOAP service, WSDL https://ec.europa.eu/taxation_customs/tedb/ws/VatRetrievalService.wsdl, operation `retrieveVatRates`. It takes member states, a date (`situationOn`) or a range, optional categories and CN or CPA codes; it answers each state's STANDARD and REDUCED rates with their kind (reduced, super-reduced, parking, exempted and others), category and comment.
- The member states supply TEDB's data (https://taxation-customs.ec.europa.eu/taxation/vat/vat-directive/vat-rates_en). No key or account is named; that access is anonymous was read only on a third party's page.
- Your Europe's table (https://europa.eu/youreurope/business/taxation/vat/vat-rules-rates/index_en.htm): each state's standard, reduced, super-reduced and parking rates under a "Last checked" date, regional rates left out; an HTML page, not a feed.
- Its floors: a standard rate of at least 15%, one or two reduced rates of at least 5%, parking rates of at least 12%.

## On which terms?
- Commission pages and data: CC BY 4.0 under the Commission Decision of 12 December 2011; reuse with credit and changes marked (https://commission.europa.eu/legal-notice_en).
- The same notice: the information is not necessarily complete, accurate or up to date, the Commission takes no liability, and only the Official Journal is authentic. A wrong rate stays the shop's.

## A list shipped with updates, or a schedule?
- A list shipped with updates: no outside call, works offline, every change reviewed in a PR; a change reaches a site only with its next update, late on a site that updates rarely.
- TEDB read on a schedule: current to the day, and it can hold a dated future rate; a SOAP client is new to the code (no library without asking, `plans/global-features.md`) and the service can be down. The precedent for a dated official feed is `currency_rates` (includes/fn/currency_rates.php:242), read daily.
- Both: the shipped list is the rate; a scheduled read only compares and tells the shop, never changes a rate by itself (`tax-automatic-census-proposal.md`).
- A reduced rate needs to know what a product is: TEDB names categories per state, a product has no category today (`tax-automatic-census-doors.md`).
