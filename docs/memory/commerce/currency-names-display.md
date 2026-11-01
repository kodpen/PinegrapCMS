---
name: currency-names-display
description: Stored currency names shown through pg_currency_label() in the panel language; the currency list's rate with the panel's separators (#4430)
module: ecommerce
---

## How is a stored currency named on screen?
- `pg_currency_label($name)` (`includes/fn/ecommerce.php`): the six English names the starter data stores ("US Dollar", "British pound", "Canadian dollar", "Euro", "Japanese yen", "Australian dollar"; the Turkish starter data stores the four middle ones in English too) go through `lang()` as literal keys, so `check_lang.php` sees them; any other name, one the site owner wrote ("US Dolar", "TR Lirası", "Bitcoin"), is printed as written. A name is not passed to `lang()` as a variable, so an owner's own text is never swapped for a key that happens to match it.
- Used by the currency list (`view_currencies.php`), the dashboard card "Current Site Exchange Rates" (`includes/dashboard/widgets/widget_16.php`), the panel currency select of `includes/fn/forms.php` and the iyzico and ERP exchange-rate selects of the settings screen (`includes/settings/prep.php`). Site pages' currency pickers (`get_catalog.php` and the other order screens) still print the stored name.

## How is the rate written in the currency list?
- `pg_format_number((float) $rate, 5)`, as the dashboard card writes it: "0,01495" in Turkish. Before (#4430) the list printed the stored decimal "0.01495".

## What is not changed?
- The stored names and rates. Running sites keep their rows; a name is edited in Settings > E-Commerce > Currencies.

Related: docs/memory/commerce/country-names-display.md
