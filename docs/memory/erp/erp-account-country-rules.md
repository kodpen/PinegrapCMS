---
name: erp-account-country-rules
description: How the ERP applies a country's rules only to that country's accounts: tax number check and width, the tax id label, the Turkish address mapping (4.71) and the gates of Turkish-only features.
---

## Which country is an account judged by?
- `erp_account_country($code)` (`includes/erp/accounts.php`): the account's own two-letter code, else the store's (`erp_default_country_code()`, `countries.default_selected`; '' when the store chose none).
- The ERP is used in many countries: a rule of one tax authority (VKN/TCKN check digits, `11111111111`, a five-digit postcode, a person as first name + surname) applies only to that country's accounts.

## How is a tax number checked?
- At every door with `erp_tax_number_check($number, $country)` → `['value', 'error']`: account form, New account dialog and counter sale (`erp_account_quick_create()`), CSV import, API, Workspace record changes, delivery-note carrier, expense supplier.
- Turkey: digits, 10 (VKN) or 11 (TCKN), check digits by `erp_edoc_tax_number_valid()`. Elsewhere: letters, digits, spaces, `. - /`, kept as typed, at most `erp_tax_number_width()` characters (read from the `erp_accounts.tax_number` column: 32 since 4.70, 11 before).
- Never filter Turkish-style in SQL (`LENGTH(tax_number) IN (10, 11)`); match two numbers by their letters-and-digits key, `erp_edoc_account_tax_key()` (upper case, separators dropped).
- Its name: `erp_tax_id_label($country)`, "VKN / TCKN" in Turkey, "Tax number" elsewhere. On a PDF the seller's is `label.tax_id` (the store's country), the buyer's `label.account_tax_id` (the account's country).

## How does an address land on the card? (4.71)
- Card fields `city`, `district`, `state`. Read a checkout or contact address (`business_city` / `business_state`) with `erp_address_from_checkout($city, $state, $country)`: in Turkey the state field is the province (card `city`) and the city field the district; elsewhere city is city and state is state. Never copy `business_state` straight into `city`.
- A Turkish account keeps `state` empty: every door (form, CSV import, API `erp_api_account_state_rule()`) moves a state into an empty city and drops it.
- The printed place line: `erp_address_locality()` in the country's own order ("34710 Kadıköy İstanbul", "Austin, TX 78701", "10115 Berlin"; a foreign country is named) and `erp_seller_locality()`; templates print `{{seller.locality}}` / `{{account.locality}}`.

## How are Turkish-only features gated?
- Withholding, the internet-sale box, GİB fields, the Paraşüt export profiles, e-document columns and steps ask `erp_turkish_features()` (store in Turkey, or e-documents in use) or `erp_edoc_in_use()` (a provider is selected, or documents went through one before). A document that already carries such a field shows it regardless.

Related: docs/memory/erp/erp-account-contact-link.md
