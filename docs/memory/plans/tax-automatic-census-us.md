---
name: tax-automatic-census-us
description: Automatic tax census: US sales tax services a checkout driver could call (TaxJar, Avalara AvaTax, Stripe Tax, Quaderno), what each asks, and the free SST rate files
---

## Which services, and what does each ask?
- TaxJar: `POST /v2/taxes` (https://api.taxjar.com/v2/, sandbox https://api.sandbox.taxjar.com/v2/) with from and to address, amount, shipping, `line_items` (optional `product_tax_code`) and `nexus_addresses`, else the account's nexus; a token from a TaxJar account (https://developers.taxjar.com/api/reference/). The API only in Professional, $99 a month at 200 orders, 30-day trial (https://www.taxjar.com/pricing/).
- Avalara AvaTax: Basic auth with an account id and license key from the AvaTax website, sandbox (https://sandbox.admin.avalara.com) and production (https://admin.avalara.com) apart (https://developer.avalara.com/avatax/authentication-in-rest/, https://developer.avalara.com/avatax/logins/); `CreateTransaction` records a sale; a 90-day trial (https://developer.avalara.com/products/avatax/). Price and nexus setup not read.
- Stripe Tax: `POST /v1/tax/calculations`, then `/v1/tax/transactions/create_from_calculation` once paid, a reversal for a refund; works with payments outside Stripe; taxes only where the account holds a registration, else zero (https://docs.stripe.com/tax/off-stripe). A US address needs at least the postal code. Also EU VAT, reverse charge from a tax id (https://docs.stripe.com/tax/custom).
- Stripe Tax's fee: 50 cents a transaction with 10 calculations, 5 cents a calculation above, or Tax Complete from $90 a month (https://stripe.com/tax/pricing).
- Quaderno: `GET /tax_rates/calculate` (`to_country`, postal code, city, street, `tax_code`), an account's API key, a sandbox host; rates only for jurisdictions the account is registered in (https://developers.quaderno.io/api/). Plans 24 to 124 a month (currency not shown), API calls 10 times the plan's transactions, 7-day trial (https://www.quaderno.io/pricing).

## What do they share?
- Each needs the shop's own account, a paid plan after the trial, and the shop's nexus or registrations set at the service: it taxes only where the shop says it collects. The driver carries the shop's key; the product holds no account of its own.
- Calls are counted: the express order recalculates on each address edit (`commerce/express-order-tax-address.md`), so a driver calculates on the final address and keeps the answer per cart.
- Stripe and AvaTax record the paid sale as a transaction, which feeds their reports and filing; a refund is recorded against it.

## Is there a free source of US rates?
- The Streamlined Sales Tax member states publish rate and boundary files (by ZIP code or address), new files quarterly when something changed (https://www.streamlinedsalestax.org/Shared-Pages/rate-and-boundary-files). The page states no terms, covers member states only, and gives rates, not which products are taxable.
