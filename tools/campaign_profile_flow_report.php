<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Prints the dry-run report of turning a site's e-mail campaign profiles
 * into flows (includes/fn/campaign_profile_flow.php): per chain the flow it
 * would become, per profile left the reason, with each profile's last 30
 * days. Reads only; nothing is written. Run it in a checkout whose
 * pinegrap/data/config.php points at the site (or a copy of it).
 *
 * Usage:  php tools/campaign_profile_flow_report.php [--json]
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

chdir(dirname(__DIR__) . '/pinegrap');
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
require './init.php';
require_once PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php';
require_once PG_FUNCTIONS_DIR . '/includes/fn/campaign_profile_flow.php';

$report = pg_profile_flow_dry_run(time());

if (in_array('--json', $argv, true)) {
    print json_encode($report['plan'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit;
}

print implode("\n", $report['lines']) . "\n";
