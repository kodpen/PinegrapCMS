---
name: settings-recategorization
description: Plan (owner 2026-10-30): Site Settings regrouped, one place per setting; System tools screen, Accounting category, clearer names; steps in settings-recategorization-steps.md
---

## What is the goal?
- Owner, 2026-10-30: people get lost in Site Settings. The list mixes 10 categories (54 cards, `includes/settings/registry.php` 84-310) with 18 links that leave the dialog (`registry.php` 515-625). Some topics sit in two or three places: the organization's identity, AI bots, the panel's look, privacy. Some names are near twins: Security/Firewall, API/Application API/Application Access, Cron Jobs/Jobs, Clean Up/Purge Cache.

## What is decided? (owner, 2026-10-30)
- Tools leave the list. The 18 links (Monitoring, Connections, Store Lists, Jobs, Infrastructure) move to one new System tools screen, with the same rights per link. The dialog's list keeps only categories, plus one "System tools ›" link at its end. The main menu's System heading gets a row for the screen.
- Accounting becomes a category, named as in the main menu. It takes the ERP and E-Invoice cards out of Ecommerce and the cards of `erp_settings.php`; that screen then opens the category.
- Security becomes "Accounts and sign-in"; Firewall becomes "Firewall and HTTPS". The encryption key moves from Payment Methods (`commerce.php` 1000-1006) to Accounts and sign-in.
- Kept: one dialog with one Save per pane; `#settings/<cat>/<card>` and `settings_<cat>.php` links, with old keys mapped as `pg_settings_legacy_anchors()` does; every field's column and save rule.

## What is the target list?
- **General:** Organization (name, address, logo, support e-mail and tax number, gathered from Campaigns, SEO, ERP and Affiliate), Server and domain, Date and time, Software.
- **Appearance:** Theme, Dashboard, Rich-text editor, Private label (from `private_label.php`).
- **Features:** Feature options, Page revisions, Page cache, Image optimization, Signature time stamp.
- **SEO and sharing:** SEO, Site icon, Social sharing, Structured data, AI crawlers (one place; the firewall's AI switches read from it).
- **Visitors and privacy:** Visitor tracking, Cookie consent, Legal documents.
- **Languages and translation:** as now.
- **Communication:** E-mail (SMTP link), Campaigns, Chat, SMS, SMS messages, İYS, MailChimp.
- **Accounts and sign-in:** Session and password, Two-step, Devices, Sign in with, Registration and membership, Spam protection (with the blocked e-mail addresses), Audit trail, Encryption key.
- **Firewall and HTTPS:** Firewall, Trusted proxies (no longer under the firewall switch), Bot filtering, IP lists, Country restriction, HTTPS and headers.
- **Ecommerce:** Store, Tax (out of Store), Shipping, Payment methods, Gift cards and rewards, Reference price, Affiliate program, Product feeds (from SEO).
- **Accounting:** ERP, E-Invoice, the `erp_settings.php` cards.
- **Integrations** (was API): Application API, Uploads and logs, Google reviews, and links to Application access, Connected accounts, Marketplaces and Workspace settings.
- **Updates and maintenance:** Update channel, Cron jobs, Backups, Alerts, Maintenance mode.

## What is still open?
- Headings over the list, for example Site, Selling, Security, Running the site? Ask before R8.
- Two "Support E-mail Address" fields (`general.php` 40, `commerce.php` 1281): one field, or two names? Ask before R4.

## Where to start?
- Code: `registry.php`, `modal.php`, `screen.php`, `settings_pane.php`, the category files and their `.save.php`.
- Notes: `ui/settings-modal-architecture.md` (out of date: says eight categories) and `ui/settings-modal-navigation.md`.
- What moving one card touches: its registry section, its block, its columns in the `.save.php`, the registry `lists`, a legacy-anchor row and the help mapping (`get_help_url.php`). A new category also needs an `output_menu()` case.
