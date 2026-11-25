---
name: connections-google-reviews-page
description: Google reviews' page on Connections (CX11i): the Places API key and Place ID typed there under the Integrations gate; the Google Reviews card shows the connection
module: google_reviews, connections_screen
---

## Where are Google reviews' key and Place ID typed?
- On `connections.php?service=google_reviews` (CX11i #11117, the Integrations group of `connections-one-place.md`), no longer on Site Settings › Integrations › Google Reviews.
- "Connection details": "Places API Key" (password box, never drawn back; "Stored. Leave empty to keep it."), "Place ID" with the Place ID Finder link, "Save" filled, "Save and try" quiet. Posts `grev_save`.
- `pg_grev_page_decide()` (pure): a key is needed while none is stored, the Place ID is always needed (the card allowed saving one without the other); a changed key or place empties the cache. `pg_grev_page_save()` seals it; try reads the place and names it with its rating, or shows Google's error.
- Status has "Disconnect" (`grev_remove`, `pg_grev_page_remove()`) while a key or Place ID is kept: the sealed entry is deleted.
- "Where it is used": the Google Reviews widget (the card's link) and the review invitation after a survey answer (Messages › Surveys), which reads the Place ID.

## Who may change it?
- Who may open Site Settings › Integrations (`connections_integrations_may_manage()`, `pg_settings_may_open('api')`): card `details` of `pg_connections_screen_page_cards()` with `$can['integrations']`; the writes check the same.

## What does the Google Reviews card keep?
- `#pgset-google-reviews` keeps its place and anchors; it shows `pg_grev_settings_connection_html()`: "Google reviews connection", Connected teal / Not connected gray, "Manage in Connections" / "Connect in Connections". `api.save.php` no longer writes it; `pg_grev_settings_check()` and `pg_grev_settings_save()` are gone.
- The survey editor's "Set your Google place first" line links the page (`pg_grev_connections_url()`).

## What of Integrations stayed?
- Application API and Uploads & Logs: the site's own API, nothing typed for an outside service. Related screens stays.

## Registry?
- `place` `connections.php`, `found_at` `#settings/api/pgset-google-reviews`, state `pg_grev_configured()`; no service added. No data step, table or column: the sealed `google_reviews` service entry is unchanged.

Tests: `tests/connections_google_reviews_test.php`, `tests/settings_integrations_test.php`.
