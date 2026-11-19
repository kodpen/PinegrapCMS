<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The site's service worker for visitor notifications: assets/js/
 * pg_site_push_sw.js with its settings before it
 * (pg_site_push_worker_prelude()), answered with Service-Worker-Allowed for
 * the site's root, so a worker kept under the software directory may serve
 * the whole site. The "Notify me" block registers it only when a visitor
 * presses its button (assets/js/pg_site_push.js).
 *
 * It is answered while the switch is off too: a browser that subscribed
 * before keeps a worker that still loads, and nothing is sent to it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// No session: a browser fetches the worker without the visitor's
// credentials, and the answer is the same for every visitor.
define('PG_NO_SESSION', true);

$_SESSION = array();

include(dirname(__FILE__) . '/init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_push_block.php');

$site_push_sw_script = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_site_push_sw.js');

if ($site_push_sw_script === '') {
    header('HTTP/1.1 404 Not Found');
    header('X-Robots-Tag: noindex');
    exit();
}

$site_push_sw_settings = pg_site_push_block_settings(PATH, SOFTWARE_DIRECTORY, '');
$site_push_sw_icon = (function_exists('pg_site_icon_kind') && (pg_site_icon_kind(pg_site_icon_name()) !== ''))
    ? PATH . 'apple-touch-icon.png'
    : '';

header('Content-Type: application/javascript; charset=utf-8');
header('Service-Worker-Allowed: ' . PATH);
// A browser checks a worker for updates past its own cache; no-cache keeps
// an upgraded file from waiting for a day, private keeps shared caches out.
header('Cache-Control: private, no-cache');
header('X-Robots-Tag: noindex');

echo pg_site_push_worker_prelude(
    $site_push_sw_settings['door'],
    defined('TITLE') ? TITLE : '',
    lang('Open the site to see what is new.'),
    $site_push_sw_icon
);
echo $site_push_sw_script;
