---
name: system-status-php-security-fixes
description: The "PHP Security Fixes" System Status check (PHP2Shell, CVE-2026-109618/109619): fixed releases, build date, four states, and the Docker image's upload progress setting
---

## What is PHP2Shell, and why a check for it?
- Two flaws in PHP itself, published 2026-10-21: CVE-2026-109618 (the out-of-memory bailout leaves a half-built hash entry) and CVE-2026-109619 (state one request leaves behind reaches the next one in the same worker: session upload progress, on 8.5 also fatal error backtraces). Any reachable PHP page is enough, under FPM, mod_php, CGI and LiteSpeed alike.
- Pinegrap's code cannot close it, and `waf.php` runs after PHP has parsed the request, so it cannot filter it either. The cure is the host's PHP update; the check tells the operator whether it happened.
- php.net fixed it in 8.5.12, 8.4.27, 8.3.36 and 8.2.35; the 8.1 and older lines got no release.

## How does the check decide? (`includes/fn/system_status.php`)
- `pg_php2shell_status(PHP_VERSION, pg_php_build_time())`. At or above its line's release (`pg_php2shell_fixed_releases()`), or a later line: fixed (green).
- Otherwise the build date decides, because distributions patch without changing the number (Ubuntu 24.04 stays 8.3.6): built before 2026-10-01, missing (red, full weight); on or after 2026-10-21, likely_fixed (grey, not scored, "confirm with your host"); in between (the embargo weeks) or no build date, unknown (yellow, half weight).
- The build date is read from `phpinfo(INFO_GENERAL)`: `pg_php_build_time()` asks `function_exists()` first (disable-functions-rule.md), `pg_php_build_time_from_info()` reads the command-line and the HTML form as UTC, and `pg_php_build_date_text()` prints it in UTC too (in the site's time zone a 21 October build could show as the 20th).
- Red and yellow add one sentence on turning off `session.upload_progress.enabled` while it is on.
- Title "PHP Security Fixes", short label "PHP fixes", icon `bi-bug-fill`, group server right after PHP Version, weight `php_security` = 18 (Serious class).

## What does the Docker image do?
- `docker/php-settings.conf` sets `session.upload_progress.enabled = Off`; nothing in Pinegrap reads upload progress.
- The base `php:8.3-fpm-alpine` carried 8.3.36 on 2026-10-22, so a rebuild has the fixes. Images published before that need the Docker publish workflow run again for their tag (docker-image-publishing.md).

## Tests
- `tests/php_security_fixes_test.php`: the states, the build date parser, the Docker setting.
