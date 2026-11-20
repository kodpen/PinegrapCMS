---
name: connections-google-analytics-page
description: Google Analytics' page on Connections (CX11b): the tag and the GA4 figures set there under the Visitors and privacy gate; the Visitor Tracking card shows the connection
module: google_analytics
---

## Where are the Google tag and the GA4 property set?
- On `connections.php?service=google_analytics` (CX11b, the Visitors and privacy group of plan `plans/connections-merges-build.md`), no longer on Site Settings › Visitors and privacy › Visitor Tracking.
- "Connection details" ("Bağlantı bilgileri"), first form: the switch "Enable Google Analytics", "Web Property ID" (the Google tag ID) and "Website Analytics URL" (the Visitors menu's link), "Save" filled. `pg_ga_tag_input()` takes each in its own format, `pg_ga_tag_save()` writes the three `config` columns.
- Second form, "Google Analytics figures": the property list, or the chosen property with "Read the figures now" and "Stop using this property", through `pg_ga_save_from_screen()` as before.
- "Status": the Google accounts connected for `google_analytics` with "Connect a Google account", the tag's state, the chosen property. "Where it is used": the tag (to the Visitor Tracking card) and the figures (to the dashboard).

## Who may change it?
- Who may open Site Settings › Visitors and privacy (`connections_privacy_may_manage()`, `pg_settings_may_open('privacy')`): the card `details` of `pg_connections_screen_page_cards()` with `$can['privacy']`; the writes `ga_tag_save` and `ga_figures` check the same. The list row keeps that gate through the registry's `found_at`.

## What does the Visitor Tracking card show?
- `#pgsub-google-analytics`: "Google Analytics connection", Connected when the switch is on with a valid tag ID, a line when the dashboard shows figures, and "Manage in Connections" / "Connect in Connections".
- `privacy.save.php` no longer writes `google_analytics`, `google_analytics_web_property_id` or `stats_url`, so saving the card cannot empty them. `includes/settings/privacy_analytics.php` and its hidden Connect form are gone.
- Old anchors `google_analytics`, `google_analytics_web_property_id`, `stats_url`, `ga_property` land on `pgset-analytics` (`pg_settings_legacy_anchors()`).
- Registry: `place` `connections.php`, `found_at` `#settings/privacy/pgset-analytics`. `pg_connections_count()` stays 78. The dashboard's Google Analytics card and widget 32 link to the page.

## What of Visitors and privacy stayed, and why?
- The site's own settings, no outside account: visitor tracking with its code duration and pay-per-click flag, record retention, IP removal, the daily summary; Cookie Consent (it names Google's consent mode but holds no key); Legal Documents; the Accessibility Statement.
- "Website Analytics URL" moved with the tag: it sat under the Google Analytics switch and is the outside analytics site's address.

Tests: `tests/connections_google_analytics_test.php`, `tests/settings_privacy_test.php`, `tests/consent_test.php`.
