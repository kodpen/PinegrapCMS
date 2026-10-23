---
name: marketplace-variant-attributes
description: Listed products' variant attributes (Trendyol varianter and slicer, n11 isVariant): own option first, the article's value as fallback, duplicate refusal, mapping screen field.
---

## Which attributes count as variant attributes?
- `is_variant` in `marketplace_category_attributes`. n11: `isVariant`. Trendyol: `varianter` **or** `slicer` (`mp_trendyol_attribute_rows()`, #980, PR #1239). The slicer (usually Renk) splits one `productMainId` into pages per value, so like the varianter it differs between the products of one article: the chair whose products are its colours.

## What symptom did the slicer fix?
- The slicer was stored as an ordinary attribute, asked once per article on the mapping screen, and every product of a colour-split article went out in that one colour: rows Trendyol cannot tell apart, or the wrong colour shown to buyers (#980, PR #1239).

## Where does a product's value come from?
- `mp_listing_attributes()` reads the product's own options and hands them to the pure `mp_listing_attributes_of($definitions, $chosen, $own)`.
- A variant attribute: the product's own option first (`mp_listing_variant_value()`: by attribute name, then by value). A product whose options do not match it (no option of that name, no label on the category's list) takes the value chosen for the article. With neither, a mandatory one refuses the article ("does not carry the option ..."); an optional one is left out.
- Other attributes: only the article's answer.

## What stops two products going out as the same variant?
- `mp_listing_variant_clash($definitions, $items)` (pure, `listings.php`), called at the end of `mp_listing_items()`: it keys each item by its variant attribute values (value id, or free text without case) and refuses the article naming the first two products that share a key. Items with no variant value are not compared.
- Symptom it fixes: the fallback made `[Size: Medium]` and `[Size: Large]` both the article's Beden M, and a Renk stored by the old required field made `Colour: Mocha` and `Colour: Green` both Siyah: two SKUs with identical variant values under one `productMainId` (PR #1239).
- A single-size article split by colour still lists: the shared Tek Ebat comes from the article, the colours differ.

## What does the mapping screen show for a variant attribute?
- `mp_settings_article_form()`: the "from the variants" pill and an optional field ("Leave empty"), never required. The help text says the value is used only for a product whose options do not match the attribute, and that two products ending up the same refuse the article. A single-colour article lists by choosing its colour there.

## What about categories cached before the change?
- Their rows keep a Trendyol slicer as `is_variant = 0` until the cache rows are replaced (nothing refreshes them on its own). Trendyol listing is new in 2026.11.0, so only test sites hold such rows; delete that category's rows to refetch.

Related: docs/memory/commerce/marketplace-listings.md, docs/memory/commerce/trendyol-listings.md
