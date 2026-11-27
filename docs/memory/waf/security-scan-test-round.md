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
- 10,000 files are walked and scaled to 50,000 (about 0.4 s of processor time for 50,000), asserted under a quarter of `SEO_ANALYZE_TIME_BUDGET`; the walk holds no list.

## Why is the walk timed in processor time and against a bare walk?
- Wall-clock time failed `tools/pre_push.sh` on a machine running other test runs (75 to 90 s scaled), and pushes went out with `--no-verify` (#12080). Inside the full suite the walk also took 1.6 s for 10,000 files instead of 0.08 s: the 1 MB read buffer per picture (`security-scan-file-scan.md`), now 64 KB.
- The test now takes `getrusage()` user plus system time (wall clock only where it is missing), best of three passes: a pause while other processes hold the processor costs no processor time.
- The walk must also stay under 20 times a bare walk (`scandir()` per folder, `is_link()` and `is_dir()` per name) timed the same way in the same run: 5 to 8 times measured, idle, under load and inside the full suite. A walk that lists its folder again per name (about 28 times) fails; a few extra `stat()` calls per file do not.

## Which false alarms became exceptions?
- `data/layouts/<id>.bak.php`: the 2017.2.2 password pages step's copy of a layout.
- `data/config.tmp<uniqid>.php`: config.php while `pg_write_config_file()` writes it.
- An `.htaccess` that only denies (`pg_security_config_denies_only()`: deny or Require all denied, Order, IfModule, `Options -Indexes`, comments): data/, includes/, workspace mail, accountant, quarantine and backup folders get one from the software. Such a file never enters a later baseline, so one turned into a handler reads `new`.
- The server rules repair writes the web root's `.htaccess`, above the software folder, which the walk never reads.
- The installer's administrator keeps `user_created` 0, so a new install reports no new account.

## What is not verified, or still open?
- Not run: a live crawl during a scan (page times, sitemap, robots), object storage (local `data/files` may be empty), a backup restore and the update's replace on a real site.
- A PHP file a later release dropped stays on an upgraded site (the update deletes nothing); it was reported as `file_php_unknown` (high) until SC8b made it `file_php_retired` (`security-scan-retired-files.md`).
