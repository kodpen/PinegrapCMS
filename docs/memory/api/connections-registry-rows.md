---
name: connections-registry-rows
description: Every row of the registry of outside services by group: the place of its setting (Site Settings card or screen) and how its state is read, and the services that show no state, with why
module: connections_registry
---

## Where is each set, and how is its state read?
- The rule and the reader kinds: `connections-registry.md`. Cards are `#settings/<category>/<card>`; the code is the source, this file its map.
- Payments (`commerce/pgset-payments`): iyzico, Stripe, Authorize.Net, ClearCommerce, First Data, Sage by `ECOMMERCE_PAYMENT_GATEWAY` (Iyzipay, Stripe PaymentIntents, ...); PayPal and PayTR by the app `payment_<key>`; Givex (`pgset-giftcards`) by `ECOMMERCE_GIVEX`.
- Shipping (`commerce/pgset-shipping`): Geliver, Basit Kargo, Kargonomi, Aras Kargo, Shippo, Sendcloud by `pg_label_ready()`; UPS, FedEx, USPS by `pg_carrier_api_ready()`; ShipWorks none.
- Marketplaces and feeds: n11, Trendyol (`marketplace_settings.php`) by their account; Google Merchant Center (`pgset-feeds`) by the Google account for `merchant_center`; OpenAI product feed none.
- E-documents: Paraşüt, Logo İşbaşı (`accounting/pgset-invoice`) by the active e-document provider; VIES (`pgset-store`) none.
- E-mail: Postmark, Mailgun, Brevo, Resend, Amazon SES (`mail_sending/pgset-mail-transport`) by the app `mail_<key>`; Gmail and Microsoft 365 mail (`mail_sending/pgset-mail-system`, where the account is chosen) by the account for `mail` (Microsoft's also covers `mail_read`); Mailchimp (`mail_sending/pgset-mailchimp`) by `MAILCHIMP`.
- SMS and messaging (`contact/`): Netgsm, İleti Merkezi, Twilio by the app `sms_<key>` (`pgset-sms`); İYS by the app `iys`; WhatsApp by the app `whatsapp`; Slack, Microsoft Teams, Telegram (`maintenance/pgset-chat-notify`) by a stored target of that kind.
- Files and backups (`maintenance/pgset-backup`): Google Drive, OneDrive, Dropbox by the account for `backup`.
- Sign-in (`security/pgset-signin`): Google, Microsoft, Apple by `pg_signin_enabled()`.
- Calendars and notes: Google Calendar, Outlook Calendar (`workspace_team/pgset-ws-calendar`) by the account for `calendar_sync`; OneNote (`connections.php`) by the account for `notes`.
- Analytics and search: Google Analytics (`privacy/pgset-analytics`) and Google Search Console (`search_console.php`) by the Google account for their purpose; IndexNow (`seo/pgset-seo`) by `INDEXNOW_KEY`.
- Maps and places: Google Places, Mapbox, Photon (`commerce/pgset-address-autocomplete`) by the app `addr_<key>`; Google reviews (`api/pgset-google-reviews`) by `pg_grev_configured()`; DB-IP (`firewall/pgset-countries`) none.
- AI (`workspace_assistants/`): Pinegrap AI by `ws_ai_ready()`; Claude by `ws_claude_ready()`; OpenAI, Anthropic, Gemini, Mistral, DeepSeek, OpenRouter, Azure OpenAI (`connections.php`, each its own page since CX9a, `ai-connections.md`) by `pg_ai_connection_ready()`, the key stored as the provider's connection; Google Cloud Translation (`languages/pgset-translation-engines`) by `TRANSLATION_GOOGLE_KEY_ENC`.
- Protection: Cloudflare (`cloudflare.php`) by `CLOUDFLARE_API_TOKEN` and `CLOUDFLARE_ZONE_ID`; Turnstile, hCaptcha (`security/pgset-captcha`) by the app `captcha_<key>`; StopForumSpam by `pg_form_spam_enabled()`.
- Other: GitHub (`connections.php`, its own page since #10418) by the account for `channel_links`; ActivityPub (`fediverse/pgset-fediverse`) by `pg_ap_enabled()`; Frankfurter, HexaRate (`maintenance/pgset-cron`) none; Pinegrap licence and updates (`general/pgset-software`) by `SUBSCRIPTION_KEY`.
- MCP connectors (`workspace_assistants/pgset-ws-mcp`): the 12 of `ws_mcp_catalogue()`, each by a server added from its entry.

## Which services show no state, and why?
- ShipWorks: it signs in as a user of the site. OpenAI product feed: a file the service fetches. VIES: no key or switch, runs whenever tax is on. DB-IP: a free database, no account. Frankfurter and HexaRate: no account, the exchange-rate job asks them.
