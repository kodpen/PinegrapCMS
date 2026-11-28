---
name: etsy-categories
description: Etsy categories and properties on the Listings view: the seller taxonomy under the provider's key (etsy), nested nodes flattened, a leaf's properties as attribute rows, scales named on values
module: etsy
---

## Where do an Etsy account's categories come from?
- The seller taxonomy of the Open API v3, read with the seller's token and the x-api-key (`mp_etsy_api_call()`): `GET /seller-taxonomy/nodes` (timeout 120 s, kept up to 32 MB, gzip accepted) by `mp_etsy_category_tree()` in `connectors/etsy.php`.
- `pg_etsy_taxonomy_rows()` (`includes/fn/etsy.php`) flattens the nested `children` depth first in Etsy's order; a node without children is a leaf, the only kind a listing's `taxonomy_id` takes. A node whose id is not a number is left out with its branch. An empty tree is an error, never an empty cache.
- `mp_etsy_category_attributes()` asks `GET /seller-taxonomy/nodes/{id}/properties`; an id that is not a number is never sent.

## Under which key are they cached?
- The provider's, `etsy`: one taxonomy serves every shop, so the connector has no `category_key` (eBay's tree a site does, `ebay-categories.md`). No table or column was added.

## How does a property become an attribute row?
- `pg_etsy_property_rows()`: `property_id` is `attribute_id`, each `possible_values` entry's `value_id` its value id, as updateListingProperty takes them. The name is `display_name`, else `name`.
- `mandatory` = `is_required`; `is_variant` = `supports_variations` (answered by each product's own option, `marketplace-variant-attributes.md`); `custom_allowed` = no possible values (a free text box).
- A property that is neither an attribute nor a variation (`supports_attributes` and `supports_variations` both false) is left out.
- A value of a property with more than one scale carries the scale's name, "8 (US)" and "8 (UK)", so the dialog tells them apart.

## How is a product group paired?
- On Marketplace Products › Listings of the Etsy account, the same view and category search as the other marketplaces: it appears because the connector can `category_tree`. Sending a listing waits for `create_listings` (ET3 of `plans/sales-channels-global.md`).

## What is not verified?
- Built in ET2 from Etsy's documentation; fixtures `tests/fixtures/etsy/taxonomy-nodes.json`, `taxonomy-properties.json`; tests `tests/etsy_taxonomy_test.php`. Whether a property with possible values also takes a text of the shop's own is not settled; it is a closed choice here. The first try on a test shop is the owner's.
