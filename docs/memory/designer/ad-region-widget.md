---
name: ad-region-widget
description: Ad Region widget (ad_region, #1836): the classic <ad> region in the designer; which ads show and in which order, the carousel and its rotation, raw ad markup, edit chips, what is left out.
---

## What is it?
- Kind `ad_region` (`includes/fn/widgets_ad_region.php`), label "Ad Region". `cfg.ad_region_name` names the region as `<ad>name</ad>` does; a new widget takes the site's first region. Draws nothing while ads are off (`ADS`), as the classic region.
- The ads stay in the Ads screen (`ads`, `ad_regions`) with the users granted the region; read on every request.

## Which ads, in which order?
- `pg_aw_pick()`, as the classic region: a static region one ad at random; a dynamic one every ad by `sort_order`, shuffled when no ad has a sort order.

## What does a row hold?
- `__ad_name`, `__ad_label`, `__ad_content_html`, `__ad_caption_html` (the ad's own markup: in `pg_sw_raw_token_keys()` and its twin `SW_ATTR_MARKUP`, so never escaped, never in a condition or an attribute). Row flag `ad_has_caption`. The row's layout ids are made its own before the ad's markup goes in, so an id in an ad stays as written.
- Flags `has_ads`, `no_ads`, `has_several_ads`.

## How does it turn?
- The starter is a Bootstrap carousel; the server adds `active` to the first row's element when it is a `carousel-item` (`pg_aw_mark_first_active()`). `pg_aw_rotation()` gives `__ad_ride`, `__ad_interval` (ms, at least 1000) and `__ad_wrap` (false for a slideshow that is not continuous), written into `data-bs-*` as `{{token}}` attribute values ([[widget-attr-bindings]]). Controls show with `has_several_ads`.

## Edit chips?
- On the front end, for a manager or a user granted the region, each ad carries a chip to `edit_ad.php`.

## What is left out?
- The classic transition type (fade or slide), its labels menu and `software_initialize_dynamic_ad_region()`: the design gives the look (`carousel-fade` by hand). Images inside an ad get no edit buttons.
