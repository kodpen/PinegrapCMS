---
name: verified-shipping-address-screens
description: Verified shipping address screens: state labels via pg_geo_state_option_labels(), country sort by shown name, required state picker, state filter fit to the bar (#9080), Turkish name
---

## How are the state options labelled and ordered?
- `pg_geo_state_option_labels($states, $foreign_states)` (`includes/fn/geo.php`) gives `id` and plain-text `label`: "Country: State" with the country through `pg_geo_country_label()` while states of a non-default country exist, else the state name. With countries the rows follow the shown country name (`pg_geo_sort_by_label()`), states of one country keep the query's order.
- Used by `get_state_options()` (`includes/fn/forms.php`, the Create/Edit form's picker) and the state filter of `view_verified_shipping_addresses.php`. Before (#5721) the form printed the stored English name ("Turkey: Adana") and both lists ordered by it, so "Türkiye" came first.

## How does the list sort by country?
- `'country' => pg_list_label_order('countries.code', $country_labels)`, the labels read from the countries table per request: the order follows the name the Country column shows, not `countries.name`.

## Why is the state picker `required`?
- The server refuses an empty state (`validate_required_field('state_id', …)`); the select carries `required` so the panel's marker script adds the `*` and the browser stops an empty choice before posting (#5722). The İl/Eyalet label swap only rewrites the label's first text node, so the marker stays.

## Why is the list's state filter a `pg-toolbar-filter` form?
- `w-auto` widens the select to its longest option ("Amerika Birleşik Devletleri: Virgin Islands (US) (0)"). In the toolbar's `d-inline-flex` form a flex item keeps that width as its automatic minimum, so `max-w-100` did nothing and the select ran 38 px off a 390 px screen (#9080; first #5723, fixed in #5813 inside a wrapping form the list's later look dropped).
- The form, written by hand for its visible "State" label, now carries `pg-toolbar-filter` (`ui/toolbar-phone-fit.md`): the select shrinks to the bar and cuts its text with an ellipsis; the label is `text-nowrap`, so "Eyalet/İl" stays on one line. A desktop bar is unchanged.

## What is the screen group called in Turkish?
- "Doğrulanmış Teslimat Adres(ler)i" everywhere: "All Verified Shipping Addresses" was the only key saying "Gönderi".
