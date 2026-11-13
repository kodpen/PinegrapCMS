---
name: canvas-sample-gaps-catalog
description: CS0 census, catalog (CS4): catalog listing, product page and values, wishlist, order form, search, photo gallery, tag cloud, folder view: canvas faults
---

## What do the catalog listing and the product page draw wrong? (`SW_CATALOG_TOKEN_GROUPS`, `SW_CATALOG_ITEM_TOKEN_GROUPS`)
- Samples: SKU, product name, prices and price block, reference price, stock, out of stock and preorder labels, rating, bundle saving, page numbers.
- ‹label› (about 35 each): `__id`, `__details`, `__sort_order`, `__created_at`, `__image_alt`, `__backorder`, `__out_of_stock`, `__out_of_stock_message`, `__preorder`, `__sku`, `__mpn`, `__gtin`, `__brand`, `__manufacturer`, `__weight`, `__shippable`.
- More ‹label›: `__category_id`, `__featured`, `__is_new`, `__has_variants`, `__custom_field_1` to `_4`, `__reward_points`, `__seo_score`, `__is_bundle`, `__has_breadcrumb`; listing only `__row_type`, `__display_type`, `__active_cat_id`, `__has_children`; product page only `__ai_generated`, `__variant_picker`, `__is_select_group`, `__select_group_id`.
- ‹label› where the page draws a block or control: `__group_tree_html`, `__filters_button`, `__filters_offcanvas`, `__clear_filters_button`, `__attribute_filters_html`, `__variant_picker`.
- Wrong: `__taxable` and `__discount_percent` draw money; `__gallery_count`, `__inventory_tracked`, `__quantity_available`, `__variant_count` "1"; `__category_name`, `__active_cat_name`, `__select_group_name`, `__address_name` "Example"; `__description` the stock blurb.
- Grey box: `__image_url`, `__thumbnail_url`, `__gallery_image_1` to `_5`. Empty flags: `__has_discount`, `__has_related`, `__has_active_filters`, `__has_active_attr_filters`.
- Product entity values (`SW_PRODUCT_ENTITY_TOKENS`): no sample; ‹label› for keywords, length, width, height, preparation days, VAT rate, payment period, gift card, donation; "1" for minimum, maximum and payment counts; `__is_free_shipping` and `__extra_shipping_cost` money.
- Lists: `product.images` no sample; `product.related` and `product.bundle_items` samples but grey picture boxes; `product.attributes`, `catalog.crumbs`, `catalog.active_filters` samples.

## What do the wishlist and order form draw wrong?
- Wishlist: ‹label› `__id`, `__image_alt`; `__discount_percent` money; grey box `__image_url`.
- Order form: ‹label› `__id`, `__image_alt`, `__out_of_stock_message`; `__description` the stock blurb; grey box `__image_url`.

## What do search, gallery, tag cloud and folder view draw wrong?
- Search results: ‹label› `__empty_message`, `__result_is_featured`; grey box `__result_image`; the rest samples.
- Photo gallery: all samples but the grey `__gallery_item_image_url`.
- Tag cloud: no sample. `__tag_total` money, `__tag_count` "1", `__tag_name` and `__search_page_name` "Example", ‹label› `__tag_keyword`, `__tag_size`, `__tag_weight`.
- Folder view: all samples.
