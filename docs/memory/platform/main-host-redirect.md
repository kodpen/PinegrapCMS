---
name: main-host-redirect
description: Redirect to the main host (config.main_host_redirect, 11.0.52, #2179): which requests get a 301 to config.hostname, which never do, why it starts off everywhere
module: seo
---

## What does it do?
- When on, a page request whose Host is not `config.hostname` (`HOSTNAME_SETTING`) gets a 301 to the same path and query on that host: `example.com/blog` and `www.example.com/blog` stop being two copies of one page (#2179; the canonical link alone left both answering 200).
- `pg_main_host_redirect_url()` (`includes/fn/seo.php`) decides; `get_page.php` asks right after `init.php`, before the maintenance gate, and sends the 301.

## Which requests are never redirected?
- Anything but GET and HEAD; a request that already names the main host (case, a trailing dot and the same port aside); no Host (command line renders); an IP address (v4, `[v6]`) or `localhost` / `*.localhost`; a main host that is not a plain name.
- Everything that is not a page render, since only `get_page.php` asks: the panel, `api.php`, `health.php`, cron, webhooks, `get_file.php` downloads. The page file cache is no gap: it answers only a request on `config.hostname` (`page-file-cache.md`), so another host always reaches `get_page.php`.
- The path passes `pg_safe_redirect_path()` (`redirect-after-hostname.md`): `//host` or a full address becomes the site root.
- There are no language domains (languages use address prefixes, `language-address-prefix.md`), so nothing else is exempt.

## Why off by default, new sites included?
- Decision #2474: off until the operator switches it on (Site Settings › SEO › Site Map and Crawling). A site reached by an unexpected name (a temporary address, before DNS is set) would send its visitors to a name that may not resolve yet, and a 301 is cached by browsers.
- Constant `MAIN_HOST_REDIRECT` (`init.php`, false before the upgrade); `seo.save.php` writes it only once the column exists.

Related: docs/memory/visitors/robots-large-image-preview.md
