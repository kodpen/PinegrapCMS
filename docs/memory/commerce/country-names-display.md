---
name: country-names-display
description: Country names on screen through pg_geo_country_label() and pg_geo_sort_by_label() in the current language; the starter dumps' corrected country rows (#2522)
---

## How is a stored country named on screen?
- `pg_geo_country_label($name, $code)` (`includes/fn/geo.php`): the stored English name through `lang()` when the Turkish shards know it ("Turkey" → "Türkiye", "Brazil" → "Brezilya"), else the name intl gives for the ISO code in the current language (`pg_geo_country_name()`: "Aland Islands" → "Åland Adaları"), else the stored name. `pg_geo_sort_by_label($rows)` orders a list by that label with the language's `Collator` ("Çin" between "Brezilya" and "Danimarka"), case-insensitive byte order without intl.
- Used by the account widgets' billing and shipping address forms (`_pg_acct_country_options()`), the express order's billing country, the classic billing information page, the shipping zone and tax zone forms, and the Name column of the countries list (`view_countries.php`; sorted by name, it re-sorts the rows by the label, descending reversed, since the query can only order by the stored name). Before (#2522) every one of them printed and sorted the raw name, so a Turkish site showed "Turkey", "United States" among Turkish names and in a mixed order.

## What is not changed?
- The stored names and codes: zones, orders and the accounting accounts match on the stored name (`erp/erp-parasut-accounts.md`), the dashboard sales map sends the raw name to its filter (`ui/dashboard-sales-map-data.md`). Running sites keep their rows (decision #2657): a wrong country there is edited in Settings > Countries; the billing page keeps both options of a duplicated label by adding the code.

## What did the starter dumps' countries get?
- Both dumps (`turkish_default`, `english_default`), 242 rows to 240, `code` unique: id 250 "Western Samoa" (code `EH`) is renamed "Western Sahara" / "Batı Sahra"; the duplicate `CD` and `VA` rows the International zone does not reference are deleted (266 "Congo (CD)", 273 "Holy See"), so 254 (was "Zaire") is kept and renamed "Congo (CD)", and 246 "Vatican City" stays; `AN` (161) and `CS` (194) are deleted with their `zones_countries_xref` rows, and "Serbia" (`RS`, 293) and "Montenegro" (`ME`, 294) are added to the International zone; "Swaziland" → "Eswatini", "Macedonia" → "North Macedonia", Turkish "Azerbeycan" → "Azerbaycan". The new English names have Turkish keys.
