---
name: verified-shipping-address-screens
description: Verified shipping address screens: state labels via pg_geo_state_option_labels(), country sort by shown name, required state picker, narrow filter, one Turkish name (#5721-#5723)
---

## How are the state options labelled and ordered?
- `pg_geo_state_option_labels($states, $foreign_states)` (`includes/fn/geo.php`) gives `id` and plain-text `label`: "Country: State" with the country through `pg_geo_country_label()` while states of a non-default country exist, else the state name. With countries the rows follow the shown country name (`pg_geo_sort_by_label()`), states of one country keep the query's order.
- Used by `get_state_options()` (`includes/fn/forms.php`, the Create/Edit form's picker) and the state filter of `view_verified_shipping_addresses.php`. Before (#5721) the form printed the stored English name ("Turkey: Adana") and both lists ordered by it, so "Türkiye" came first.

## How does the list sort by country?
- `'country' => pg_list_label_order('countries.code', $country_labels)`, the labels read from the countries table per request: the order follows the name the Country column shows, not `countries.name`.

## Why is the state picker `required`?
- The server refuses an empty state (`validate_required_field('state_id', …)`); the select carries `required` so the panel's marker script adds the `*` and the browser stops an empty choice before posting (#5722). The İl/Eyalet label swap only rewrites the label's first text node, so the marker stays.

## Why `max-w-100` on the list's state filter?
- `w-auto` widens the select to its longest option ("Amerika Birleşik Devletleri: …"); at 375 px it ran 14 px past the card (#5723). `max-w-100` keeps it inside the wrapping flex form; the browser clips the option text.

## What is the screen group called in Turkish?
- "Doğrulanmış Teslimat Adres(ler)i" everywhere: "All Verified Shipping Addresses" was the only key saying "Gönderi".
