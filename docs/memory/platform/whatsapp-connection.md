---
name: whatsapp-connection
description: WhatsApp account (WA1, #9067): the Communication › WhatsApp card and its five steps, connect_apps storage, the save check with Meta, the test message, whatsapp_webhook.php verify and signature
module: whatsapp
---

## Where does the account live?
- `connect_apps` row `whatsapp` (`pg_whatsapp_app_key()`), no schema: `client_id` the App ID; `secret` sealed (`pg_connect_seal()`, re-sealed by `reseal.php`) holding `client_secret` (App secret), `access_token`, `verify_token`; `options` JSON with phone_number_id, account_id, display_phone_number, verified_name, quality_rating, checked (time of Meta's last yes).
- `pg_whatsapp_app()` reads it; `pg_whatsapp_ready()` is enabled, complete and not a demo site. The secrets are never put back in a field, logged or exported: an empty field keeps the stored value.

## What does the card show?
- Communication › WhatsApp (`pgset-whatsapp`, before İYS): a switch, then five numbered steps as `<h6>` group headings with help (App ID and App Secret, Access Token, Phone Number ID, WhatsApp Business Account ID, Webhook), a Test Number filled from the signed-in person's mobile and "Save and send a test message". Drawn: `plans/whatsapp-channel/settings-card.html`.
- After a check: "Connected: name, number, quality … Checked with Meta on …". Step 5 shows the read-only Callback URL and Verify Token once an account is stored, with a warning when the site is not on https (Meta calls only https).

## What does Save do?
- `pg_whatsapp_settings_decide()` (pure, tested) decides: nothing when unchanged, the switch alone, forget (off with no App ID), or save. Ids are digits (5–25); secrets printable without spaces.
- A changed account with WhatsApp on is asked of Meta (`pg_whatsapp_cloud_check()`, Graph `v26.0`): `debug_token` with the app token `APPID|SECRET` (wrong pair marks App Secret; a token of another app, one that expires, or one without `whatsapp_business_messaging` and `whatsapp_business_management` marks Access Token), then `GET /{phone_id}` with `appsecret_proof`, then the account's `phone_numbers` must list it.
- A refusal is marked on its field and nothing of the pane is written. Meta not reachable: saved unchecked, a notice says so and the test message shows whether it works.

## What is the test message?
- Meta's sample template `hello_world` (`en_US`) to the Test Number, sent at once and charged by Meta; at most ten an hour per site (`waf_rate_exceeded('site', 'whatsapp-test', 10, 3600)`). Refused on a demo site. A send with an unknown outcome is reported as such, never repeated.

## What does whatsapp_webhook.php answer?
- A root machine endpoint without a session, in waf.php's API and country-exempt lists. GET is Meta's verify call: the challenge with 200 only for the stored verify token, else 403 (an offence for the firewall). POST: 403 without a stored App secret, 413 over 3 MB, 401 without a valid `X-Hub-Signature-256` (an offence), else 200 `{"ok":true}`; other methods 405; 600 calls a minute per address, then 429.
- Its events are not read yet: WA4 adds delivery states, template changes and customers' messages.

Related: docs/memory/platform/whatsapp-cloud-api-facts.md, docs/memory/platform/whatsapp-cloud-api-webhooks.md, docs/memory/platform/sms-provider-layer.md
