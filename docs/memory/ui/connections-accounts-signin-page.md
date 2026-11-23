---
name: connections-accounts-signin-page
description: Accounts and sign-in on Connections (CX11f): Apple's sign-in application and the Turnstile and hCaptcha keys on their pages; the cards keep the switches and the choice
module: connections_screen
---

## Where is Sign in with Apple's application typed?
- On `connections.php?service=signin_apple` (CX11f #10785, plan `plans/connections-merges-build.md`), no longer on the "Sign in with Google, Microsoft and Apple" card.
- "Connection details": Apple's setup steps, the Return URL, Services ID, Team ID, Key ID, "Private key (.p8)" (never drawn back, "Stored. Leave empty to keep it."), "Save", "Open the Apple console". Status has "Disconnect" (`apple_remove`, `pg_connect_app_remove('apple')`) while a row is stored.
- `pg_signin_apple_app_decide()` (`includes/fn/signin.php`, tested) holds the checks the card ran; a refused field writes nothing. Saved as before: `connect_apps` row `apple`, the key through `pg_signin_pem_private_key()`.
- "Where it is used": the sign-in card, with whether its switch is on.

## Where are the CAPTCHA keys typed?
- On `?service=turnstile` and `?service=hcaptcha`: "Site Key", "Secret Key" (never drawn back), "Save" and "Save and try" (siteverify with a made-up token, as the card's check). Status has "Disconnect" (`captcha_remove`) while keys are stored.
- The write builds the card's post for one provider and runs `pg_captcha_settings_check()` / `pg_captcha_settings_save()`: keys of the provider forms use are asked about before they are saved (`waf/form-captcha-providers.md`).
- StopForumSpam has no key: its switch stays on Forms and spam; its page keeps Status and Where it is used.

## What do the cards keep?
- "Sign in with Google, Microsoft and Apple": the three switches, each with its state and a row to its page (Google's, Microsoft's, Sign in with Apple's). Microsoft's return address is no longer on the card: Microsoft's page lists it (`connections-provider-page.md`).
- Forms and spam: "CAPTCHA", "CAPTCHA type", the warnings, form limits, blocked addresses, and a line per provider ("Keys stored" / "No keys yet", "Manage in Connections" / "Connect in Connections").
- Choosing a provider whose keys are missing marks "CAPTCHA Type": "Set up the … keys on its page of Connections first."

## Who may change them?
- Who may open Accounts and sign-in (`connections_signin_may_manage()`, `pg_settings_may_open('security')`): cards `details` of `pg_connections_screen_page_cards()` with `$can['signin']` (Apple) and `$can['security']` (Turnstile, hCaptcha). Nobody gains or loses a right.

## What did not change?
- The registry rows keep their `place` on the cards, as `signin_google` did in CX10b: the switch and the choice stay there. no service was added (`pg_connections_count()`: `api/connections-registry.md`). No data step, table or column.
- Old anchors (`pg_settings_legacy_anchors()`): `signin_apple_row`, `apple_client_id` land on `pgset-signin`; `captcha_turnstile_site_key`, `captcha_hcaptcha_site_key` on `pgset-captcha`.

Tests: `tests/connections_accounts_signin_test.php`, `tests/captcha_test.php`.
