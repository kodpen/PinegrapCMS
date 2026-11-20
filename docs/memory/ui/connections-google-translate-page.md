---
name: connections-google-translate-page
description: Google Cloud Translation's page on Connections (CX11c): the API key typed there under the Languages and Translation gate; the Translation Engines card shows the connection
module: connections_screen
---

## Where is the Google Cloud Translation key typed?
- On `connections.php?service=google_translate` (CX11c, the Languages and Translation group of plan `plans/connections-merges-build.md`), no longer on Site Settings › Languages and Translation › Translation Engines.
- "Connection details": "Google Cloud Translation API key", a password field never filled back ("Stored. Leave empty to keep it."), "Save" filled and "Save and try" quiet. Try sends "Hello" en→tr through `pg_tr_google_request()` with the typed key, or the stored one when the field is empty; Google's error is shown escaped.
- `pg_tr_google_key_valid()` (`includes/translate/store.php`) takes 1 to 200 letters, digits, dashes and underscores; `pg_tr_google_key_store()` seals it into `config.translation_google_key` as before.
- "Status" has "Disconnect" (`translate_remove`) while a key is stored. "Where it is used": the Translation Engines card, and the languages whose engine or fallback is `google` (`pg_tr_languages(false)`).

## Who may change it?
- Who may open Site Settings › Languages and Translation (`connections_languages_may_manage()`, `pg_settings_may_open('languages')`): card `details` of `pg_connections_screen_page_cards()` with `$can['languages']`; the writes `translate_save` / `translate_remove` check the same. The list row keeps that gate through the registry's `found_at`.

## What does the Translation Engines card show?
- `#pgsub-google-translate`: "Google Cloud Translation connection", Connected when a key is stored, and "Manage in Connections" / "Connect in Connections".
- `languages.save.php` no longer writes the key, so saving the card cannot change or clear it; the "Remove the stored key" box is gone.
- The old anchor `translation_google_key` lands on `pgset-translation-engines` (`pg_settings_legacy_anchors()`). Registry: `place` `connections.php`, `found_at` `#settings/languages/pgset-translation-engines`; `pg_connections_count()` stays 78.
- The engine's "no key" reason (`pg_tr_engines()`, `pg_tr_google_translate()`) names the Connections page.

## What of Languages and Translation stayed, and why?
- The site's own settings, no outside account: Site Languages (source and target languages, each language's engine and fallback), Automatic Language Redirect (its location note reads the site's own geo database), and on Translation Engines the Google attribution switch, the AI style note and the browser engine's notes.
- The attribution switch stays: it decides what the site's pages show, though Google's terms ask for it.
- Pinegrap AI and Claude hold no key here; their connections are the Workspace's (CX9, `api/ai-connections.md`).

## Was the move cleared?
- Poll 147 (`workflow/decisions-made-product-76.md`) kept this key on Languages and Translation when the AI keys merged; whether CX11 moves it is asked of the architect in the PR's Decision question.

Tests: `tests/connections_google_translate_test.php`.
