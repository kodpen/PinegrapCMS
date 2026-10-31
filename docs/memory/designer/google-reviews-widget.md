---
name: google-reviews-widget
description: Google Reviews widget (kind google_reviews): Place Details with a Places API key, the settings card, the sealed state and short cache, tokens, attribution, nothing drawn when not set up
module: google_reviews, widgets_google_reviews
---

## What is it?
- Decided in #3180: the business's Google rating and up to five reviews, read-only, with a Places API key; no OAuth, no schema. Kind `google_reviews`, label "Google Reviews", renderer `_render_system_widget_google_reviews()` (`includes/fn/widgets_google_reviews.php`); reading, cache and settings in `includes/fn/google_reviews.php`.

## Where are the key and the Place ID?
- Site Settings › Communication › Google Reviews (`pgset-google-reviews`, `includes/settings/contact.php`; saved by `pg_grev_settings_check()` / `pg_grev_settings_save()` from `contact.save.php`). The key is never drawn back: an empty box keeps it, the form text names its last four characters, "Remove the saved key" clears it. Both empty: the entry is deleted. "Save and test" (named submit, `ui/settings-card-own-button.md`) reads the place and names it with its rating.
- One sealed service entry `pg_connect_service_put('google_reviews', …)`: `key`, `place_id`, `cache`. Without `ENCRYPTION_KEY` the card warns and keeps nothing. A changed key or Place ID empties the cache.

## How is Google asked?
- `pg_grev_request()`: `GET https://places.googleapis.com/v1/places/<id>?languageCode=<page language>`, headers `X-Goog-Api-Key` and `X-Goog-FieldMask: id,displayName,rating,userRatingCount,reviews,googleMapsUri` (the mask is what Google bills). Through `pg_curl_run()` with `check_address` (TLS, private-address guard), 10 s, 256 KB cap.
- `pg_grev_parse()` keeps five reviews at most and only https links and photos.

## How long is it kept, and why?
- Google's Places policies let a site keep the Place ID; other Places content may not be stored beyond what the terms allow. So the answer is only a short cache per language: fresh for `PG_GREV_FRESH` (1 h), a failed read retried after `PG_GREV_RETRY` (10 min), the last good copy shown after failures until `PG_GREV_KEEP` (24 h), then dropped. A failure is logged once (`log_activity`) until a read works.
- `job.php` runs `pg_grev_job_run()` on every tick (only due languages). A page reads itself only when nothing usable is kept (`pg_grev_place()`); the attempt time is written first so parallel requests do not ask too.

## Tokens and flags?
- Static: `__place_name`, `__place_rating` (one decimal), `__place_review_count`, `__place_maps_url`, `__review_total`, `__google_attribution` ("Google Maps", never translated). Row: `__review_author_name`, `__review_author_url`, `__review_author_photo`, `__review_rating`, `__review_text`, `__review_relative_time` (Google's words in the language asked), `__review_url`. All escaped.
- Flags `has_reviews`, `no_reviews`; row `review_has_photo`, `review_has_text`. No Messages block, no settings in the widget panel (a note points to Site Settings).
- Not configured, nothing read or past the ceiling: the renderer returns '' (nothing on the site).

## Default layout?
- JS starter only (`case 'google_reviews'`): name, star and rating, review cards (photo, author linked to Google Maps, relative time, rating, text, review link), a no-reviews line, the Maps link and the attribution. Google requires the author attribution and its link with each review and "Google Maps" with content shown without a map: keep them when redesigning.

## What is not verified?
- `tests/google_reviews_test.php` covers the request, parsing, cache window, rows, escaping and the empty render. The live read needs a Google Cloud project with billing, Places API (New) on, a key restricted to the server and the Place ID; checked on dev by the owner.
