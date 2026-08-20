# Pinegrap CMS

**English** | [Türkçe](README.tr.md)

![PHP Version](https://img.shields.io/badge/PHP-7.0%20--%208.5-777BB4?style=flat-square&logo=php)
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

* **Broad Compatibility:** Runs seamlessly across PHP 7.0 to PHP 8.5.
* **Server Support:** Compatible with Apache, Nginx, and Microsoft IIS (including automatic web routing and `.htaccess` / `web.config` rewrite handling).
* **Enterprise Architecture:** Robust form management (`liveform`), dynamic page engine, and secure session handling.
* **Customizable UI:** Built-in support for modern themes and Bootstrap 5 integration.

---

## Feature Highlights

**Content & Design**

* Dynamic page engine with page, common, designer, and dynamic regions — content is edited in place, on the page itself
* Visual theme and style designer with drag-and-drop system widgets (catalog, cart, express order, order view) driven by data bindings instead of magic CSS classes
* Blog / article publishing, photo galleries, menus, comments with scheduled publishing, and site-wide search
* Calendars with recurring events, locations, and reservation support
* Built-in image editor for cropping, resizing, and designing visuals without leaving the panel

**E-commerce**

* Product catalog with nested product groups, variant sets, and product form templates
* Offers, coupons, gift cards, cross-sell, and abandoned-cart auto campaigns
* Full order lifecycle: cancellation flows, refunds (Iyzipay integration), cargo tracking links for Turkish carriers, printable invoices, and order timelines
* Barcode-based inventory operations

**Marketing & Communication**

* Scheduled e-mail campaigns with mail-merge variables and opt-in management
* MailChimp synchronization, contact management, affiliate & commission tracking
* Short links, live chat module, and a REST API (`apps.php`) for custom integrations

**Security**

* Built-in Web Application Firewall: signature scanning, rate limiting, IP reputation, bot classification, and IPv6-aware IP bans
* CSRF tokens, role-based access control (Administrator / Designer / Manager / User), developer PIN locks
* TLS-verified update channel — update packages are never accepted without certificate verification

**Performance & Operations**

* Request-level performance monitor with percentile reporting
* Visitor analytics with hourly rollup tables built for high-traffic sites
* Image optimization, Cloudflare integration, automatic backups, and a versioned database upgrade system

**Internationalization**

* Full English and Turkish interface via the `lang()` translation system
* UTF-8-safe casing and ASCII-safe URL generation for Turkish characters (important on IIS)

---

## System Requirements

* **PHP:** Version 7.0 up to 8.5
* **Database:** MySQL / MariaDB
* **Web Server:** Apache (with `mod_rewrite`), Nginx, or IIS
* **Extensions:** `mysqli`, `gd`, `curl`, `mbstring` (recommended: `zip` and `openssl` for software updates)

---

## Installation

1. **Clone the Repository**

   ```bash
   git clone https://github.com/kodpen/pinegrap.git
   ```

2. **Upload to Your Web Server**

   Place the files in your document root (or a subdirectory). On Apache the bundled `.htaccess`, on IIS the bundled `web.config` handles URL rewriting automatically.

3. **Create a Database**

   Create an empty MySQL / MariaDB database and a user with full privileges on it.

4. **Run the Installer**

   Open `https://your-domain.com/pinegrap/install/` in your browser and follow the wizard. The installer creates the schema and writes `data/config.php` (including an auto-generated encryption key) for you.

5. **Set Up Cron Jobs** (recommended)

   ```cron
   */5 * * * * php /path/to/pinegrap/job.php
   */5 * * * * php /path/to/pinegrap/email_campaign_job.php
   ```

   `job.php` handles scheduled publishing, abandoned-cart campaigns, and general housekeeping. `email_campaign_job.php` sends scheduled e-mail campaigns and is activated with the `EMAIL_CAMPAIGN_JOB` constant in `data/config.php`.

---

## Updating

Updates are applied from the admin panel. Schema changes ship as versioned upgrade steps and run through the same `install/` interface — no manual SQL required. See `changelog.txt` for what changed in each release.

---

## History

Pinegrap began life as **LiveSite**, developed by Camelback Web Architects since 2001. Since 2017 it has been maintained and evolved by **Erdal Güral (Kodpen)** under the name Pinegrap; the final LiveSite update (2019) has been fully integrated. LiveSite remains available separately as a legacy version.

---

## License

Released under the [MIT License](license.txt).

Copyright © 2001–2019 Camelback Consulting, Inc. · © 2016–2026 Kodpen
