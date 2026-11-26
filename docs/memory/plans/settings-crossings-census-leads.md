---
name: settings-crossings-census-leads
description: Crossings census, the leads: every Site Settings card (and other screen) that leads to a Connections page, the page and anchor it opens, its label, same or new tab, when the lead shows
---

## Which cards lead where? (same tab, "Manage in / Connect in Connections" unless said)
- SEO › Search Engine Optimization: `?service=indexnow` (`includes/settings/seo.php:87`).
- Visitors and privacy › Visitor tracking: `?service=google_analytics` (`privacy.php:87`). Cookie consent: no lead to Meta CAPI, TikTok Events or GA4 MP, though their `found_at` is that card.
- Languages and Translation › Translation Engines: `?service=google_translate` (`languages.php:387`).
- Communication › SMS: the chosen provider's page (`fn/sms.php:1696`); WhatsApp `?service=whatsapp` (`fn/whatsapp.php:338`); İYS `?service=iys` (`iys/settings.php:148`).
- E-mail › Campaign mail: `?service=mailchimp` (`mail_sending.php:280`). E-mail sending: no lead (decided); with Google's or Microsoft's application missing, "Set up the application on Connections first." opens the list `connections.php`, not the provider's page (`fn/mail_settings.php:108`).
- Mailboxes: `?service=google|microsoft&mail_return=…#connections-mail` (`workspace/mail_settings.php:401`); label "Sign in for mailboxes in Connections" when the account serves another purpose; new tab from a box's drawer.
- Accounts and sign-in › Sign in with…: "Set up in Connections", Google `?service=google#connections-signin`, Microsoft `?service=microsoft` (no anchor), Apple `?service=signin_apple` (`security.php:200-222`). Forms and spam: Turnstile, hCaptcha `?service=<key>` (`fn/captcha.php:593`).
- Ecommerce › Payment Methods: the gateway or driver named by the select, shown by script as the select changes (`fn/payments.php:2533-2579`). Gift Cards & Rewards: Givex. Shipping: the label service or carrier (`carrier_labels.php`, `carrier_api.php`). Address Autocomplete: the chosen provider (`address_autocomplete.php:778`). Product Feeds: `?service=merchant_center` (`merchant_center.php:406`).
- Accounting › E-Invoice: `?service=parasut|isbasi` (`fn/ecommerce.php:13488-13498`).
- Integrations › Google Reviews: `?service=google_reviews` (`google_reviews.php:434`).
- Updates and maintenance › Backups: `?service=google_drive|onedrive|dropbox&backup_return=1` (`fn/backup_cloud.php:84`), and a second link "Connected accounts" to the list `connections.php` (`maintenance.php:301`). Chat notifications: `?service=slack|teams|telegram` (`notify_channels_page.php:418`).
- Assistants and agents › Workspace agents and Voice message transcripts: the provider's page under the "Provider" select (`fn/ai_connections.php:360`). Claude in the channels: `?service=claude` (`workspace/claude.php:3010`), also set-up step 4 (`claude_setup.php:222`). MCP servers: `?service=mcp_<key>`, or the list's MCP group, in a new tab, ghost button with an arrow icon (`mcp_catalogue.php:979`).
- Channels › GitHub repositories: `?service=github#connections-repositories` (`github_links.php:669`).

## Which other screens lead to Connections?
- Page Design Studio › New design, Figma: `?service=figma`, manager only (`designer_screen.php:2041-2117`).
- Marketplace settings, Merchant Center's account: "Manage in Connections" in a notice (`marketplace_settings.php:3169`).
- Dashboard GA card and widget 32: `?service=google_analytics`.
- Site Settings list: the "Connections ›" row opens the list (`pg_settings_connections_link()`).

## Which old anchors open a page instead of a card?
- Only Mailchimp's (`pgset-mailchimp`, `mailchimp_row`, `mailchimp_key`; `pg_settings_anchors_to_connections()`). The other moved cards kept their card and anchor (`decisions-made-product-14.md`).

Related: docs/memory/plans/settings-crossings-census.md
