---
name: marketplace-variant-attributes
description: Where a listed product's variant attributes come from (Trendyol varianter and slicer, n11 isVariant): the product's own option first, the article's value as fallback, and the mapping screen field.
---

## Which attributes count as variant attributes?
- `is_variant` in `marketplace_category_attributes`. n11: `isVariant`. Trendyol: `varianter` **or** `slicer` (`mp_trendyol_attribute_rows()`, #980, PR #1239). The slicer (usually Renk) splits one `productMainId` into pages per value, so like the varianter it differs between the products of one article: the chair whose products are its colours.

## What symptom did the slicer fix?
- The slicer was stored as an ordinary attribute, asked once per article on the mapping screen, and every product of a colour-split article went out in that one colour: rows Trendyol cannot tell apart, or the wrong colour shown to buyers (#980, PR #1239).

## Where does a product's value come from?
- `mp_listing_attributes()` reads the product's own options and hands them to the pure `mp_listing_attributes_of($definitions, $chosen, $own)`.
- A variant attribute: the product's own option first (`mp_listing_variant_value()`: by attribute name, then by value), else the value chosen for the article. With neither, a mandatory one refuses the article ("does not carry the option ..."); an optional one is left out.
- Other attributes: only the article's answer.

## What does the mapping screen show for a variant attribute?
- `mp_settings_article_form()`: the "from the variants" pill and an optional field ("Leave empty"), never required; the help text says the value is used only for a product without an option of its own. A single-colour article lists as before by choosing its colour there.

## What about categories cached before the change?
- Their rows keep a Trendyol slicer as `is_variant = 0` until the cache rows are replaced (nothing refreshes them on its own). Trendyol listing is new in 2026.11.0, so only test sites hold such rows; delete that category's rows to refetch.

Related: docs/memory/commerce/marketplace-listings.md, docs/memory/commerce/trendyol-listings.md
