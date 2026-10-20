# Pinegrap CMS

**English** | [Türkçe](README.tr.md)

![PHP Version](https://img.shields.io/badge/PHP-7.1%20--%208.5-777BB4?style=flat-square&logo=php)
![Database](https://img.shields.io/badge/MySQL%205.7%2B%20%7C%20MariaDB%2010.2%2B-4479A1?style=flat-square&logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)
![Status](https://img.shields.io/badge/Status-Active%20Development-success?style=flat-square)

Pinegrap is an open-source, all-in-one website platform written in PHP. A website with its own design, an online store, accounting (ERP), a team workspace and an external API live in one codebase, and a new site can be up in minutes from a ready-made design template.

Pinegrap grew out of LiveSite and has been developed by Kodpen since 2017. It runs wherever PHP runs, from a shared cPanel account to a dedicated IIS server, with no Composer step, no build pipeline and no outside service to set up.

---

## Links

| | |
|---|---|
| **Website** | [pinegrap.com](https://pinegrap.com) |
| **Features** | [pinegrap.com/features](https://pinegrap.com/features) |
| **Documentation** | [pinegrap.com/introduction](https://pinegrap.com/introduction) |
| **Changelog** | [pinegrap.com/changelog](https://pinegrap.com/changelog) |
| **Live demo** | [demo.pinegrap.site](https://demo.pinegrap.site) |

---

## Try the demo

[demo.pinegrap.site](https://demo.pinegrap.site) is a **demo installation** of Pinegrap for anyone who wants to try it before installing: look around the site, open the control panel and build a page with the visual editor. Please do not enter real personal data or payment details there.

---

## What's inside

A short overview; the full list is on [pinegrap.com/features](https://pinegrap.com/features).

* **Visual page editor and design templates:** drag-and-drop pages with Bootstrap 5 or custom CSS, looks and colour palettes, shared components, drafts, HTML / ZIP import, and ready-made templates (Say hello to Pinegrap, Online Store, Bookshop, Boutique, Café, Clinic, Playground) to choose from during setup.
* **Content:** pages, blog, photo galleries, menus, comments, forms, calendars with reservations, site search, a file manager with image tools, and a Recycle Bin for deleted pages, page styles and files.
* **Multilingual sites:** pages, the store, forms and e-mails served in other languages under a prefix such as `/en/`, a Translations screen with several translation engines, and automatic language redirection.
* **E-commerce:** a catalog with variants, campaigns, coupons and gift cards, checkout with saved addresses, payment gateways such as Iyzipay, Stripe and PayPal, carrier rates and tracking, a point-of-sale screen and n11 marketplace sync.
* **ERP:** customer and supplier accounts, invoices, quotes, waybills, purchase orders, stock, cash and bank, reports, and e-Invoice / e-Archive through Logo İşbaşı or Paraşüt.
* **Workspace:** team channels, tasks, a plan board, a calendar, notes, guest rooms, approvals, time tracking and scheduled actions, with Pinegrap AI or Claude helping in channels and tasks.
* **API and integrations:** an external API with scoped keys, an OpenAPI description and webhooks; an OAuth 2.1 authorization server and an MCP server, so AI apps such as Claude and ChatGPT can connect with a person's own permissions; connected Google and Microsoft accounts for e-mail and backups; MailChimp and Cloudflare.
* **Sign-in and security:** Sign in with Google, Microsoft or Apple, two-step sign-in, roles, and a built-in web application firewall with bot control, rate limits, country restriction and security headers.
* **Operations:** visitor statistics, an SEO score and a Google Search Console report, a performance monitor, System Status with one-click repairs, scheduled backups with remote copies, a mail queue and updates from the control panel.
* **Control panel:** English and Turkish, installable as an app, with a "?" button on every screen that opens its documentation on pinegrap.com.

### Proudly monolithic

* **No Composer, no build pipeline, no `node_modules`.** Upload the files, run the installer, done.
* **One codebase, one deploy.** Everything ships together and is upgraded together through versioned upgrade steps.
* **Runs where PHP runs.** A codebase with a production lineage going back to 2001 that still deploys over plain FTP if it has to.

---

## System requirements

| | |
|---|---|
| **PHP** | 7.1 to 8.5 |
| **Database** | MySQL 5.7 or later, or MariaDB 10.2 or later (the installer refuses MySQL 5.6 and MariaDB 10.1) |
| **Web server** | Apache or LiteSpeed with `mod_rewrite`, Microsoft IIS with URL Rewrite, or nginx |
| **PHP extensions** | `mysqli`, `mbstring`, `openssl`, `curl`; GD or Imagick for images; `zip` for updates, backups and imports |
| **Write access** | `pinegrap/data/` for the configuration, backups and temporary files; the whole `pinegrap/` folder for updates from the control panel |

---

## Installation

### 1. Get the files

**Download Assistant (quickest):** upload [`download_assistant.php`](download_assistant.php) to your web root, open `https://your-domain.com/download_assistant.php` and click **Install**. It downloads the latest stable package, unpacks it into `pinegrap/` and opens the installation wizard.

**From GitHub:** download the latest release from [GitHub Releases](https://github.com/kodpen/PinegrapCMS/releases), or clone the repository, and upload the files to your document root (or a sub-folder). The `main` branch holds released versions; work in progress lands on `development`.

```bash
git clone https://github.com/kodpen/PinegrapCMS.git
```

### 2. Run the installation wizard

1. Create an empty MySQL / MariaDB database and a user with full privileges on it.
2. Open `https://your-domain.com/pinegrap/install/` and follow the six steps: source (the starter site, or a restore from a backup on the server or a zip file), control panel language, site design (the starter site or a design template), database, the first administrator, and installation.
3. The installer creates the tables, writes `pinegrap/data/config.php` with a generated encryption key, and writes the web server rules: `.htaccess` on Apache, `web.config` on IIS, or `nginx.conf.sample` for you to copy into your nginx server block.

### 3. Schedule the general job (recommended)

```cron
* * * * * php /path/to/pinegrap/job.php
```

One scheduled task is enough. In **Settings › General › Cron Jobs**, turn on **Run Cron Jobs Automatically** and select the jobs the general job should run for you, one per turn: e-mail campaigns, webhook delivery, the search index, SEO checks, backups and others. The same card lists the exact command for every job if you prefer separate schedules (on Windows, use Task Scheduler).

---

## Try it with Docker

The [`docker/`](docker/) folder holds a Docker Compose stack: nginx, PHP-FPM, the general job and MariaDB 10.11, each in its own container. It is the quickest way to try Pinegrap on your own machine and can run a site on a server you manage; the installation above stays the main way.

```bash
git clone https://github.com/kodpen/PinegrapCMS.git
cd PinegrapCMS
docker compose -f docker/docker-compose.yml up -d
```

Open `http://localhost:8080/pinegrap/install/` and run the installation wizard. On its database step enter `db` as the **Database Hostname**; the database name, username and password are all `pinegrap` (or what you set in `PG_DB_NAME`, `PG_DB_USER` and `PG_DB_PASSWORD`).

**Without the wizard:** name the first administrator before the first start and the starter site is installed for you:

```bash
PG_INSTALL_ADMIN_EMAIL=you@example.com PG_INSTALL_ADMIN_PASSWORD='choose a long one' \
  docker compose -f docker/docker-compose.yml up -d
docker compose -f docker/docker-compose.yml logs install
```

The site is then at `http://localhost:8080/` and its control panel at `http://localhost:8080/pinegrap/`.

* **Images:** without `--build`, Compose uses the images published for every release, `ghcr.io/kodpen/pinegrap-app` and `ghcr.io/kodpen/pinegrap-web`. It pulls them when they are not on the machine yet, and builds them from the files of your copy only when they cannot be pulled. `docker compose -f docker/docker-compose.yml pull` fetches them ahead of time and replaces older ones on the machine. `PG_IMAGE_TAG` picks the tag: `latest` (the default) follows the newest release, a release tag such as `v2026.11.0` stays on that release. `up -d --build` builds both images from the files of your copy instead, under the same names: use it for a copy you changed yourself. Images built that way are used until the next `pull` replaces them.
* **Settings** come from the shell or from a `docker/.env` file: `PG_HTTP_PORT` (8080), `PG_DB_PASSWORD` (`pinegrap`; for anything but a trial set your own before the first start), `PG_INSTALL_STARTER` (`english_default` or `turkish_default`) and the others listed at the top of [`docker/docker-compose.yml`](docker/docker-compose.yml).
* **Your data** stays in two volumes, `pinegrap_data` (configuration, uploads, backups) and `pinegrap_db`. `docker compose -f docker/docker-compose.yml down` stops the site and keeps them; `down -v` deletes the site.
* **Updates:** the software comes from the image. Update your copy (`git pull`, or the new release files) so the compose file is the new release's, then run `docker compose -f docker/docker-compose.yml pull` and `up -d`: the containers are recreated with the new images. With `PG_IMAGE_TAG` set to a release tag, set the new tag first; with images you build yourself, run `up -d --build` instead. The database steps run from the upgrade screen the control panel opens at the next sign-in, or at once with `docker compose -f docker/docker-compose.yml exec -u www-data app php pinegrap/install/index.php automated_upgrade`. An update installed from the control panel lasts only until the containers are recreated.

---

## Updating

Updates are installed from the control panel (**Software Update**), on the Stable or Beta channel. Database changes ship as versioned upgrade steps that run on their own, so no manual SQL is needed. If an update leaves files missing, **Repair Software** restores the release files and can re-run the database steps.

What changed in each version: [pinegrap.com/changelog](https://pinegrap.com/changelog).

---

## History

Pinegrap began life as **LiveSite**, developed by Camelback Web Architects since 2001. Since 2017 it has been maintained and evolved by **Erdal Güral (Kodpen)** under the name Pinegrap; the final LiveSite update (2019) has been fully integrated. LiveSite remains available separately as a legacy version.

---

## Contributing and security

Contributions are welcome: see [CONTRIBUTING.md](CONTRIBUTING.md) for the setup, the definition of done and the issue conventions. Pull requests target the `development` branch. Security issues are reported privately as described in [SECURITY.md](SECURITY.md); please do not open public issues for them.

---

## License

Released under the [MIT License](license.txt).

Copyright © 2001–2019 Camelback Consulting, Inc. · © 2017–2026 Kodpen
