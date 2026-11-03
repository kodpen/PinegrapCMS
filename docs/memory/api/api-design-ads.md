---
name: api-design-ads
description: Ads and ad regions over the external API (/design/ads, /design/ad-regions): the write path the four screens share, the routes, refusals, the region that keeps its ads
module: ads
---

## Where is an ad or ad region written?
- `includes/fn/ads.php`: `pg_ad_save()`, `pg_ad_delete()`, `pg_ad_region_save()` (a null name keeps the stored one: a manager may not rename), `pg_ad_region_delete()` (takes `users_ad_regions_xref` with it), and the reads `pg_ad_name_in_use()`, `pg_ad_region_exists()`, `pg_ad_region_has_ads()` (null on a failed query). `add_ad.php`, `edit_ad.php`, `add_ad_region.php`, `edit_ad_region.php` and the API handlers call them; the screens keep no SQL write.
- The checks stay in `includes/forms/ad_form.php` and `ad_region_form.php`. `pg_ad_form_values()` returns the six values keyed for `pg_ad_save()`; `pg_ad_region_behavior_values()` is the pure core of `pg_ad_region_form_values()`, for a caller without a liveform.
- The edit screens save, check and delete the `$id` checked at the top, not the posted `id` field.

## Which routes are there?
- `/design/ads` and `/design/ad-regions`: list (oldest first, cursor paged by id; ads filter by `ad_region_id`), GET one (`design:read`), create (201), update (POST, PATCH also accepted), DELETE and its `POST …/delete` twin (`design:write`, owner ceiling designer). Ids `design.ads.*`, `design.ad_regions.*`; handlers in `includes/api/resources/design_ads.php`. No events.
- Ad: name required, at most 100, unique (409 `name_taken`); a region that does not exist is 422. `content`/`caption` go in through `prepare_rich_text_editor_content_for_input()` and out with `{path}` expanded to an absolute address. Not sanitised, as on the screen: only designers hold `design:write`.
- Ad region: name by `pg_ad_region_name_error()` (tag name rule, 422), unique (409 `name_taken`); behaviour by `pg_ad_region_behavior_errors()` (422, `interval` names `slideshow_interval`). A create without behaviour takes the screen defaults (static, slide, continuous). A stored 0 duration or interval is the screen's blank. Delete with ads in it: 409 `ad_region_has_ads`, checked before the dry run stop.

## Tested?
- `tests/api_design_ads_test.php` (routes, scopes, limits, partial updates, check/stop/write order, screens without SQL writes), `tests/ad_form_test.php`, `tests/ad_region_form_test.php`. Not run against a database.
