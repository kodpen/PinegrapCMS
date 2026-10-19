<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Asked by the cron service before each run of the general job: exits 0 when
 * the site is installed and its database is at the version of the code, 1
 * otherwise.
 *
 * The installer writes data/config.php before it runs the upgrade steps of
 * the starter site, all in one long request; the job started in that window
 * would work on half-built tables. A database behind the code (an image newer
 * than the site, upgrade not run yet) or ahead of it (an update installed
 * from the panel, then a container recreated from the older image) waits for
 * the upgrade screen the same way.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$software = '/var/www/html/pinegrap';
$config = $software . '/data/config.php';

if (!is_file($config) || (strpos((string) @file_get_contents($config), 'DB_HOST') === false)) {
    exit(1);
}

define('INSTALL_OR_UPDATE', true);

$versions = include $software . '/includes/migrations/versions.php';
$code_version = is_array($versions) ? (string) end($versions) : '';

include $config;

if (($code_version === '') || !defined('DB_HOST') || !defined('DB_USERNAME') || !defined('DB_PASSWORD') || !defined('DB_DATABASE')) {
    exit(1);
}

mysqli_report(MYSQLI_REPORT_OFF);

$connection = @mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
$result = $connection ? @mysqli_query($connection, 'SELECT version FROM config') : false;
$row = $result ? mysqli_fetch_row($result) : null;

exit((is_array($row) && ((string) $row[0] === $code_version)) ? 0 : 1);
