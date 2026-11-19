---
name: integrations-census-today-2
description: Census (2026-11-17) of the outside services the product connects to today, the rest: mail, SMS, chat, accounts, files, analytics, maps, AI, and what the market's lists hold that is absent here
---

## Mail, SMS and messaging?
- Sending: SMTP with DKIM and the API transports Postmark, Mailgun, Brevo, Resend, Amazon SES (System tools › SMTP Settings); Gmail and Microsoft 365 through a connected account. Reading: IMAP mailboxes with presets, Microsoft 365 by a connected account (Site Settings › Mailboxes). Mailchimp sync (Communication › MailChimp).
- SMS: Netgsm, İleti Merkezi, Twilio; İYS for consent. WhatsApp Cloud API with templates, and replies into the Workspace.
- Slack, Microsoft Teams and Telegram receive operations alerts and Workspace events (Updates and maintenance › Chat notifications).

## Accounts, files and calendars?
- Sign in with Google, Microsoft and Apple. Backups and file pickers: Google Drive, OneDrive, Dropbox; S3-compatible storage and FTP.
- Google Calendar and Outlook take a person's tasks and give moves back; iCal in and out. A meeting link is a made-up Jitsi address or a pasted link, no API.

## Analytics, search, maps, protection?
- Google Analytics (the tag and a GA4 card), Search Console, IndexNow. Tag Manager, Google Ads and the Meta Pixel have no field: a script pasted into a design, held by the cookie notice.
- Address autocomplete by Google Places, Mapbox or Photon; Google reviews; the DB-IP country database. Exchange rates from Frankfurter, HexaRate behind it.
- Turnstile, hCaptcha, StopForumSpam; Cloudflare's API with a token in `data/config.php`.

## AI?
- Pinegrap AI on the licence key; Claude through a routine; model assistants on OpenAI, Anthropic, Gemini, Mistral, DeepSeek, OpenRouter, Azure OpenAI or a custom address; speech to text; Google Cloud Translation.
- MCP servers: DeepWiki, Context7, Microsoft Learn, Cloudflare Docs, Hugging Face, Linear, Notion, Sentry, Atlassian, Vercel, Canva, GitHub, and any server by its address (`workspace/workspace-mcp-servers-card.md`).

## The rest?
- GitHub repository lines into a channel; an ActivityPub actor; the OpenAI product feed and RSS; the Unsplash picker; importers for WordPress, WooCommerce and Shopify; the vendor's licence and update API.

## What do the market's lists hold that is absent here?
- Searched in code and memory, no connector: Zapier, Make, n8n and IFTTT (an outgoing webhook can call them, nothing more).
- Marketplaces: Hepsiburada (planned), Çiçeksepeti, Pazarama, PttAVM, Amazon, Etsy, eBay; the integrators Entegra, Sopyo, StockMount, Dopigo; the price sites Akakçe and Cimri.
- Payments and accounting: Craftgate, Papara; Mikro, Netsis, BizimHesap.
- Marketing: the Conversions API, TikTok, Klaviyo. Others: reCAPTCHA, Calendly.
- Planned in `reverse-oauth-leftovers.md`: the Meta Pixel and catalogue, an Instagram feed, Google Business Profile, social posting.
