---
name: connections-indexnow-page
description: IndexNow's page on Connections (CX11a): its key typed or generated there under the SEO gate, the key file, Disconnect; the SEO card shows the connection; what of SEO stayed and why
module: seo
---

## Where is the IndexNow key typed?
- On `connections.php?service=indexnow` (CX11a, the SEO group of plan `plans/connections-merges-build.md`), no longer on Site Settings › SEO › Search Engine Optimization.
- "Connection details" ("Bağlantı bilgileri"): the key (plain text, it is public in its file), "Save" filled, "Save and try" and "Generate" quiet. Generate makes 32 hex characters on the server (`pg_indexnow_key_new()`) and saves; the old `generateIndexNowKey()` of `backend.src.js` is gone.
- "Status" ("Durum"): the state, "Key file" (a link to `/<key>.txt`, or a warning when it is missing), Disconnect (empties the key and removes its file). "Where it is used": one row to the SEO card.

## Who may change it?
- Who may open Site Settings › SEO (`connections_seo_may_manage()`, `pg_settings_may_open('seo')`): the card `details` of `pg_connections_screen_page_cards()` with `$can['seo']`, the write `indexnow_save` / `indexnow_remove` checks the same.
- The list row keeps that gate: a registry row whose `found_at` is a settings card is listed only to who may open that card's category (`connections.php`, the row loop).

## How is it saved?
- `pg_indexnow_key_save()` in `includes/fn/seo.php`: format 8 to 128 letters, digits, dashes (`pg_indexnow_key_valid()`), the old key's file and Files row removed, the new `<key>.txt` written and listed, `config.indexnow_key` stored. `pg_indexnow_key_file_ok()` is "Save and try" and the Status line.
- `seo.save.php` no longer reads or writes the key, so saving the SEO card cannot empty it.

## What does the SEO card show?
- In place of the field, `#pgsub-indexnow`: "IndexNow connection" with Connected / Not connected and "Manage in Connections" / "Connect in Connections". The old anchor `indexnow_key` lands on `pgset-seo` (`pg_settings_legacy_anchors()`).
- Registry: `place` `connections.php`, `found_at` `#settings/seo/pgset-seo` (the settings search still finds it there). `pg_connections_count()` stays 78.

## What of SEO stayed, and why?
- Everything else is the site's own, not an outside account's: title and description, sitemap and robots additions, the image preview and host redirect switches, site icon, social sharing, structured data (the merchant return and shipping figures are the site's policy) and AI crawlers. SEO had no site verification code and no Connect button.

Tests: `tests/connections_indexnow_test.php`, `tests/settings_seo_save_test.php`.
