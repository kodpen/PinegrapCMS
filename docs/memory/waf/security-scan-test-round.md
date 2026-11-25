---
name: security-scan-test-round
description: Security Scan test round (SC8, #11633): what tests/security_scan_round_test.php proves, the false alarms it turned into exceptions, and what stays unverified or open
module: security_scan_files
---

## What does the round test?
- `tests/security_scan_round_test.php`: each file rule fires on its planted file and on nothing else, with and without a reference; each content rule on its planted markup, never on a link, an entity, a visible map or video frame, or a known host.
- Clean install: the git-tracked software tree gives no finding (first run and with an empty baseline); every design template's strings and its JSON give no content finding under the default CSP; template pictures copied as `data/files/store-<name>`; every kept upload type and every refused name after `prepare_file_name()`.
- Quarantine then Restore leaves content, mode and time as before; Restore never writes over a file that came back.
- A walk cut every 1, 4, 7 or 50 files and resumed through the stored JSON sees each file once.
- 10,000 files are walked and scaled to 50,000 (0.7 s measured for 50,000), asserted under a quarter of `SEO_ANALYZE_TIME_BUDGET`; the walk holds no list.

## Which false alarms became exceptions?
- `data/layouts/<id>.bak.php`: the 2017.2.2 password pages step's copy of a layout.
- `data/config.tmp<uniqid>.php`: config.php while `pg_write_config_file()` writes it.
- An `.htaccess` that only denies (`pg_security_config_denies_only()`: deny or Require all denied, Order, IfModule, `Options -Indexes`, comments): data/, includes/, workspace mail, accountant, quarantine and backup folders get one from the software. Such a file never enters a later baseline, so one turned into a handler reads `new`.
- The server rules repair writes the web root's `.htaccess`, above the software folder, which the walk never reads.
- The installer's administrator keeps `user_created` 0, so a new install reports no new account.

## What is not verified, or still open?
- Not run: a live crawl during a scan (page times, sitemap, robots), object storage (local `data/files` may be empty), a backup restore and the update's replace on a real site.
- Open: the update never deletes a PHP file a later release dropped, so an upgraded site can report it as `file_php_unknown` (high). Fixing it needs the update to remove retired files or a list of them: for the close-out (SC9) to ask the architect.
