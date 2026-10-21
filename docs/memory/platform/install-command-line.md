---
name: install-command-line
description: php install/index.php install (PR #621): a starter site from PG_INSTALL_* variables without the screen; what it refuses, output and exit status, how the screen's code runs
---

## What does it do?
- `php pinegrap/install/index.php install` installs a starter site (default `english_default`) into an empty database with the installation screen's own code; `includes/install_cli.php` turns the `PG_INSTALL_*` variables (`install_cli_variables()`) into the form's fields. The Docker `install` service runs it (`docker/install.sh`, platform/docker.md).
- Required: `PG_INSTALL_DB_HOST`, `_DB_NAME`, `_DB_USER`, `_ADMIN_EMAIL`, `_ADMIN_PASSWORD`. Database values not set come from a data/config.php that names a database. An empty variable counts as not set.

## What does it refuse?
- A site in data/config.php's database: `skipped:`, exit 0, whatever the variables say (checked first), with the automated upgrade command when the database is behind the code. It never reinstalls.
- A site in the variables' database: the screen's notice, exit 1.
- Backups (restore stays on the screen, which shows what a backup holds) and design templates (they need `StyleDesigner.starterTree()` in a browser).
- Not quotes or backslashes in a database value (refused until PR #741): the fields go through `pg_config_install_content()`, the screen's writer, which escapes them (core/init-constants-and-config-file.md).
- An administrator e-mail that `filter_var(…, FILTER_VALIDATE_EMAIL)` refuses: exit 2, "PG_INSTALL_ADMIN_EMAIL must be an e-mail address…" (the screen leaves the format to its browser field). Before PR #1093 (issue #771) `not-an-email` installed with exit 0 and became the site's address and the alerts' fallback recipient.

## Which path does config.path get?
- PATH, next to the host name (`UPDATE config SET hostname = …, path = …` in install/index.php), on the screen and here (`SCRIPT_NAME` from `PG_INSTALL_PATH`). A job run from the command line reads PATH from that column; before PR #1093 it kept the starter dump's `/dev/` until the first web request, so alert e-mails linked to `/dev/pinegrap/…`.

## What does it print, and the exit status?
- Each `add_install_step()` as `[  1.2 s] label: detail` (`warning:`/`error:` marked), in the site's language; then one English line `ok:`, `skipped:` or `error:`, like the automated upgrade's cron answer. Exit 0 installed or skipped, 1 failed, 2 a variable missing or wrong (usage text).
- An installer check that ends with `exit('…')` would exit 0: `install_cli_shutdown()` adds an `error:` line and status 1 from a shutdown function it registers during shutdown, so the runner's own guard reports a fatal first.

## How does the screen's code run without a request?
- `install_cli_begin()`, right after config.php: the fields in `$_POST`/`$_REQUEST`, `$_GET['local']` = the language, `$_SESSION = array()` (no `session_start()`), `HTTP_HOST` = `PG_INSTALL_HOSTNAME` (config.hostname), `SCRIPT_NAME` from `PG_INSTALL_PATH` (PATH), `SERVER_SOFTWARE` from `PG_INSTALL_WEB_SERVER` (which rules file is written).
- In install/index.php: no CSRF check for it; `start_install_stream()` opens no page; `return_to_form()` prints the form's errors and notices; the dump loads step after step in the same process (`pg_restore_step()` loop) instead of ending the request; an upgrade failure gets the automated upgrade's text.

## Where was it checked?
- Cloud sandbox, MariaDB 10.11: both starters, nginx rules, config-named database, rerun, wrong password, unreachable server, a site in the variables' database, a failing upgrade step, sign-in as the new administrator, job.php after it; the screen's install (`tools/check_install.sh`) unchanged. Docker: `tools/check_docker.sh` in CI.
- PR #741, same sandbox: install and rerun with the database password `p'a\ss?>w"d\`; the written config.php parses and holds it.
