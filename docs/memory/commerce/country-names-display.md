---
name: country-names-display
description: Country names on screen: pg_geo_country_label() (lang() of the stored name, else intl by code) and pg_geo_sort_by_label() name and order every country list in the current language (#2522)
---

## How is a stored country named on screen?
- `pg_geo_country_label($name, $code)` (`includes/fn/geo.php`): the stored English name through `lang()` when the Turkish shards know it ("Turkey" → "Türkiye", "Brazil" → "Brezilya"), else the name intl gives for the ISO code in the current language (`pg_geo_country_name()`: "Aland Islands" → "Åland Adaları"), else the stored name. `pg_geo_sort_by_label($rows)` orders a list by that label with the language's `Collator` ("Çin" between "Brezilya" and "Danimarka"), case-insensitive byte order without intl.
- Used by the account widgets' billing and shipping address forms (`_pg_acct_country_options()`), the express order's billing country, the classic billing information page, the shipping zone and tax zone forms, and the Name column of the countries list (`view_countries.php`, whose server sort still follows the stored name). Before (#2522) every one of them printed and sorted the raw name, so a Turkish site showed "Turkey", "United States" among Turkish names and in a mixed order.

## What is not changed?
- The stored names and codes: zones, orders and the accounting accounts match on the stored name (`erp/erp-parasut-accounts.md`), the dashboard sales map sends the raw name to its filter (`ui/dashboard-sales-map-data.md`). The starter dump's wrong or duplicate codes ("Western Samoa" as EH, CD and VA twice, outdated names) are data of running sites and a decision of their own; the billing page keeps both options of a duplicated label by adding the code.
