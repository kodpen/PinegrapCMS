---
name: settings-tidy-census
description: Survey of the Site Settings categories (owner 2026-11-21: tidy every settings screen, e-mail first): the contradictions and scattered settings found, ranked, with file and line
---

## What is this?
- Owner (2026-11-21): find and fix the settings screens' contradictions, gather settings spread over several places, make old screens modern, starting with e-mail (done, `ui/settings-mail.md`), then the other categories.
- Read on development 1c77838b32; paths under `pinegrap/`. The order of the plans it leads to: `settings-tidy-census-order.md`. Architect (2026-11-21, message 11313): one category a plan, each asking the architect only for the parts it truly lacks.

## Which contradictions confuse most?
- (K1) The KVKK notice and consent box at sign-up (`ecommerce_kvkk_page_id`, `ecommerce_kvkk_consent_text`, `privacy.php:164-177`) work only while the distance-sales switch `ecommerce_legal_documents` is on (`includes/fn/legal_documents.php:874-876`). A site without a shop must turn it on. Behaviour change: architect first.
- (K2) The sales watch ("Take a product off sale on a price error", `maintenance.php:796-835`) is hidden and switched off with Operations alerts (`includes/fn/ops_alerts.php:175-177,1520-1524`). Done #11876: the card Sales watch of its own, behaviour kept (`ui/settings-maintenance.md`).
- (K3) "Support E-mail Address" (`general.php:63-66`) is the From of all system mail (`mail.php:502,581,784`) and the DKIM domain fallback (`mail.php:2338`); its label does not say so. The addresses stay with their features (mail-settings-one, C8).
- (K4) SEO's "Allow AI training" (`seo.php:211-214`) is overridden by the firewall's bad-bot list unless the bot is in Allowed Bots (`firewall.php:272-301`); the switch should write the allow (one place in SEO is decided, #3908).
- (K5) Dashboard "Refresh Time" is kept for the sign-in only (`appearance.save.php:109-111`) and the acrylic effect's help speaks of "your device" though it is site-wide (`appearance.save.php:87`).
- (K6) Appearance's "Custom CSS" styles the panel only (`includes/fn/output.php:68-72,319`); the label does not say so.
- (K7) General's "Proxy Address" is the outgoing proxy (`init.php:469`), easy to mix with Firewall's trusted proxies for incoming traffic; it has no help line.
- (K8) The sign-in throttle text sends the reader to the allowed list on Firewall with no link; the firewall's form rate limits sit beside Accounts and sign-in's own form limits.
- (K9) About nine messages name "Workspace Settings", a screen that now only redirects (`includes/workspace/bulk.php:388,880`, `voice.php:63`, `ai.php:974`, `mcp_ask.php:167`, `claude.php:277`, `api_claude.php:976`, `workspace_mcp.php:374`, `includes/settings/languages.php:412`).

## Which settings are spread?
- Module switches: Enable Commerce in Ecommerce (`commerce.php:34`), the rest in Features (`features.php:24-195`), courses and event tickets as loose cards (`features.php:560,582`).
- Retention periods in six places: visitors and IPs (`privacy.php:64-77`), chat (`contact.php:51`), audit log (`security.php:230-242`), firewall log (`firewall.php:112-122`), API log (Integrations).
- Abuse limits: form limits and throttle (`security.php:25-101`), captcha (`security.php:113-135`), chat captcha (`contact.php:113`), firewall rate limits and bans (`firewall.php:149-222`).
- Site address: hostname (`general.php:86-109`), main host redirect (`seo.php:72-76`), Secure Mode (`firewall.php:667-671`).
- Two Amazon S3 field sets (backup type `s3`, object storage): stay apart, drawn alike since #11910, neither on Connections (`ui/settings-maintenance.md`).
- Seller identity in General › Organization and again in Ecommerce's feeds (`commerce.php:1095-1117`).
- Smaller: the GeoIP note and refresh twice (`firewall.php:463`, `languages.php:339`); two deletion gates (`mass_deletion`, `ws_ai_bulk_delete`).

Related: docs/memory/plans/settings-tidy-census-order.md, docs/memory/ui/settings-mail.md
