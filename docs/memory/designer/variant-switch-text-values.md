---
name: variant-switch-text-values
description: The variant chooser (pg_civ_variants.js) writes a bound value as text unless its key holds markup (HTML_FIELDS), as the server escapes the token; why the script decides, tests
module: widgets_catalog
---

## What went wrong?
- `setProp()` in `assets/js/pg_civ_variants.js` wrote every `text:` binding with `innerHTML`. The variant payload (`$vproducts` in `_render_system_widget_catalog_item_view()`) and the `get_product` answer (`pg_panel_get_product()`) carry plain text raw (name, short_description, mpn, gtin, brand, weight, custom_field_1–4, reference_label), while the server escapes the same tokens once (`pg_sw_escape_token_values()`).
- A short description holding markup showed as typed on the page and ran as HTML a moment later: the refresh after the load (the variant shown, when the default selection is complete) and every pick wrote it. The text comes from a role-3 user with manage_ecommerce, a products:write API key or a marketplace import.

## What is the rule?
- Text unless named: a `text` or `html` binding writes `textContent`; only a key of `HTML_FIELDS` writes `innerHTML`.
- `HTML_FIELDS`: `description`, `details`, `out_of_stock_message` (rich text, raw tokens on the server too; the refresh maps `out_of_stock_notice` into the last), `price_formatted`, `original_price_formatted`, `discount_amount_formatted` (`pg_visitor_money()`: the symbol is stored as an entity, `&#8378;`) and `reference_price` (the token's text escaped for it, [[widget-currency-symbol-once]]).
- The key decides, not the prop: the server escapes per key, so `html:name` shows the name as text there too.
- `src`, `alt`, `href` and `value` set DOM properties, which take text: unchanged.

## Why not escape the payload instead?
- `get_product` is a public answer other readers use (old custom layouts) and stays raw, so escaping only the payload left the refresh writing HTML.
- An escaped value set as `alt`, `href` or `value` would show `&amp;`.
- A page cached with an older payload keeps working with the new script: the payload's shape did not change.

## What does a change to the payload or the script need?
- A new key holding markup goes into `HTML_FIELDS` and the test; any other key is plain text, sent raw.
- Bump `$_civ_v_v` (`widgets_catalog.php`) with every change to the script, so browsers fetch it again.

## What holds it?
- `tests/civ_variants_text_test.php` runs the script under Node on a fake page: the payload's text keys written as text and its markup keys as HTML on a pick, the `get_product` refreshes (after the load and after the pick) writing the raw name as text, an `html:` binding of a text key as text, and the version stamp.

## What is left as it was?
- The value takes the element's whole content: a rich-text slot the server fills inside markup (`pg_binding_text_slot()`, a link around the name) loses that markup on a switch, as before.
- The payload's `image_alt` is the short description, the server's `__image_alt` the name; its `price_formatted` is the plain amount where the server's token shows the strike-through block with an offer.
