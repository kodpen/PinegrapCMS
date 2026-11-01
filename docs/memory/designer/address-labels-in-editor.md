---
name: address-labels-in-editor
description: City and state labels in the visual editor follow the store's default country (İlçe and İl for Turkey): the starters write its words, the canvas swaps default words; twin of the site render stamp
module: shipping
---

## What does the visual editor show? (#4759)
- `regionData.address_labels` = `pg_address_labels_editor_config(pg_address_default_country())`: the words and whether the store's default country is Turkey (`_eo_default_country()` reads the same).
- The JS starters (express order, recipient list block, account profile, address book, billing information, affiliate sign-up, shipping address and arrival) write `_sdAddrLabel('city' | 'state')`: the default country's words, a default word the render still swaps for a visitor's other country. The PHP default tree keeps City / State / Province; the render stamps it the same.
- The canvas draws a label holding a default word beside a city or state control (name or `eo_field`) in those words (`_sdAddrCanvasText()`, twins of the key and swap); the tree is not rewritten, so an older design shows İlçe/İl in a Turkish store too.

## Why the default country?
- The canvas has no visitor's country; the site starts the country controls with the default (`countries.default_selected`), so the canvas shows what a first visit shows. Site rules: `commerce/address-labels-by-country.md`.

## Where is it tested?
- `tests/address_labels_editor_test.php`: the config, every starter's labels, the canvas hook and its control names.
