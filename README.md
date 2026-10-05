# Pinegrap CMS

**English** | [Türkçe](README.tr.md)

![PHP Version](https://img.shields.io/badge/PHP-7.1%20--%208.5-777BB4?style=flat-square&logo=php)
![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)
![Status](https://img.shields.io/badge/Status-Active%20Development-success?style=flat-square)

Pinegrap CMS is an open-source content management and enterprise web platform built upon the foundation of LiveSite, actively maintained and evolved since 2017 for performance, flexibility, and broad PHP compatibility.

---

## Overview

Pinegrap CMS provides a powerful system designed to manage enterprise websites, e-commerce, user permissions, and custom dynamic content with high reliability across legacy and modern web environments — from a shared-hosting cPanel account to a dedicated IIS server.

### Proudly Monolithic

Pinegrap is **deliberately monolithic** — and that is a feature, not an apology.

* **No Composer. No build pipeline. No `node_modules`.** Upload the files, run the installer, done.
* **One codebase, one deploy.** Everything ships together and is upgraded together through a versioned, in-app upgrade system.
* **Runs where PHP runs.** A codebase with a production lineage going back to 2001 that still deploys over plain FTP if it has to.
* **Every line is inspectable.** No vendor directory with ten thousand files you have never read.

### Key Features

* **Broad Compatibility:** Runs seamlessly across PHP 7.1 to PHP 8.5.
* **Server Support:** Compatible with Apache, Nginx, and Microsoft IIS (including automatic web routing and `.htaccess` / `web.config` rewrite handling).
* **All in One:** Website, visual page editor, e-commerce, ERP, team workspace and an external API in a single codebase.
* **Multilingual Front End:** Pages built in the visual editor are served in other languages from a virtual language directory, without copying them.

---

## Feature Highlights

**Visual Page Editor & Templates**

* Drag-and-drop page editor for Bootstrap 5 or fully custom designs; each design wears a look (corners, shadows, type, buttons) and a colour palette, and shared components (navbar, footer, bands) are edited once for every page
* Ready-made site templates — "Say hello to Pinegrap" and "Online Store" — installable straight from the setup wizard
* System widgets driven by data bindings instead of magic CSS classes: catalog, product, cart, checkout, order view, account pages, login area, cart link, language switcher, error page, form list and form item views
* HTML / ZIP import that turns an existing site into an editable design, offers repeated sections as shared components and keeps inline SVG
* E-mail pages (form notifications and replies, order receipts) designed in the same editor; an assistant proposes page changes that you preview before applying

**Content**

* Dynamic page engine with page, common, designer, and dynamic regions — content is edited in place, on the page itself
* Blog / article publishing, photo galleries, menus, comments with scheduled publishing, and site-wide search
* Calendars with recurring events, locations, and reservation support
* Desktop-style file manager (tree view, drag and drop, quick look, batch rename, image optimization, recycle bin) and a built-in image editor

**Multilingual Sites**

* Visual-editor pages served in other languages from a virtual directory (`/en/about-us`) with the same design; files, images and assets stay at one address
* Translations screen with coverage, side-by-side editing, review marks, CSV import / export and a glossary; product, form and widget texts are translated too
* Engines: Google Cloud Translation (your own key), the browser's built-in Translator API, Pinegrap AI, Claude, or manual translation; "translate on save" keeps pages current
* hreflang alternates in the sitemap, search within a language, per-language number and date formats, and a language switcher widget

**E-commerce**

* Product catalog with nested product groups, variant sets with price rules, per-product tax rates, inline editing and spreadsheet import
* Single-screen campaign builder, coupons, gift cards, cross-sell, and abandoned-cart auto campaigns
* Checkout with saved addresses, ID / tax number fields and "billing address same as shipping"; bank transfer orders wait for payment and cancel automatically when it does not arrive
* Full order lifecycle: cancellation flows, refunds (Iyzipay integration), cargo tracking links for Turkish carriers, printable invoices, order timelines, a fast POS screen and barcode-based inventory
* n11 marketplace integration: category and attribute mapping, price and stock sync, order import

**ERP (Accounting & Invoicing)**

* Customer and supplier accounts, statements, cash, bank and POS accounts, reconciliation letters
* Invoices and delivery notes from orders or by hand, drafts, returns and cancellations, PDF export
* e-Invoice / e-Archive through Logo İşbaşı or Paraşüt, aging reports, payment reminders and multi-currency balances

**Workspace (Team CRM)**

* Channels about customers and work, with tasks, a plan board, a work calendar and notes
* Guest rooms for customers or suppliers without an account, through one-time or time-limited links
* Scheduled actions with conditions and chained steps; task reminders by e-mail
* Assistants in channels and notes (Pinegrap AI with `@ai`, or a connected Claude) propose task and record changes that are applied with one click

**Marketing & Communication**

* Scheduled e-mail campaigns with mail-merge variables and opt-in management
* MailChimp synchronization, contact management, affiliate & commission tracking
* Short links (one-time and time-limited links included), a live chat module, and a PWA with web push notifications

**External API**

* `integration.php` with per-key scopes, secrets, IP allow-lists and rate limits; an OpenAPI description and a built-in console
* Products, orders, contacts, forms, files, ERP, Workspace, design and translations resources; dry-run writes, ETag and `fields=`
* Webhooks for real-time events and team device sessions for mobile apps

**Security**

* Built-in Web Application Firewall: signature scanning, rate limiting, IP reputation, bot classification, and IPv6-aware IP bans
* CSRF tokens, role-based access control (Administrator / Designer / Manager / User), developer PIN locks
* TLS-verified update channel — update packages are never accepted without certificate verification — and file integrity checked against the release tag on GitHub

**Performance & Operations**

* Request-level performance monitor with percentile reporting
* Visitor analytics with hourly rollup tables built for high-traffic sites
* System Status card with a health score and one-click repairs (write permissions, CA bundle)
* Image optimization, Cloudflare integration, automatic backups, and a versioned database upgrade system

**Internationalization**

* Full English and Turkish admin interface via the `lang()` translation system
* Multilingual front end (see above), with forms, orders and visitor e-mails kept in the visitor's language
* UTF-8-safe casing and ASCII-safe URL generation for Turkish characters (important on IIS)

---

## System Requirements

* **PHP:** Version 7.1 up to 8.5 (7.1 is the minimum; the installer and the System Status card flag older versions)
* **Database:** MySQL / MariaDB
* **Web Server:** Apache (with `mod_rewrite`), Nginx, or IIS
* **Extensions:** `mysqli`, `gd`, `curl`, `mbstring` (recommended: `zip` and `openssl` for software updates)

---

## Installation

1. **Clone the Repository**

   ```bash
   git clone https://github.com/kodpen/PinegrapCMS.git
   ```

2. **Upload to Your Web Server**

   Place the files in your document root (or a subdirectory). On Apache the bundled `.htaccess`, on IIS the bundled `web.config` handles URL rewriting automatically.

3. **Create a Database**

   Create an empty MySQL / MariaDB database and a user with full privileges on it.

4. **Run the Installer**

   Open `https://your-domain.com/pinegrap/install/` in your browser and follow the wizard. The installer creates the schema and writes `data/config.php` (including an auto-generated encryption key) for you.

5. **Set Up Cron Jobs** (recommended)

   ```cron
   * * * * * php /path/to/pinegrap/job.php
   */5 * * * * php /path/to/pinegrap/email_campaign_job.php
   ```

   `job.php` handles scheduled publishing, abandoned-cart campaigns, webhook delivery, workspace scheduled actions, and general housekeeping; running it every minute is what lets webhooks and scheduled actions go out on time. `email_campaign_job.php` sends scheduled e-mail campaigns and is activated with the `EMAIL_CAMPAIGN_JOB` constant in `data/config.php`.

---

## Updating

Updates are applied from the admin panel. Schema changes ship as versioned upgrade steps and run through the same `install/` interface — no manual SQL required. See `changelog.txt` for what changed in each release.

---

## History

Pinegrap began life as **LiveSite**, developed by Camelback Web Architects since 2001. Since 2017 it has been maintained and evolved by **Erdal Güral (Kodpen)** under the name Pinegrap; the final LiveSite update (2019) has been fully integrated. LiveSite remains available separately as a legacy version.

---

## Contributing and security

Contributions are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md) for the setup, the definition of done and the issue conventions. Security issues are reported privately as described in [SECURITY.md](SECURITY.md); please do not open public issues for them.

---

## License

Released under the [MIT License](license.txt).

Copyright © 2001–2019 Camelback Consulting, Inc. · © 2017–2026 Kodpen
