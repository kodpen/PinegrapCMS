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
 * before keeps a worker that still loads, and nothing is sent to it. In app
 * mode (pg_site_push_app_mode()) its settings carry the offline page.
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

// App mode: the worker answers a page load the network lost with an
// offline page. The answer changes with the switch, so a browser's update
// check (no-cache above) gives the installed site the handler or takes it
// away.
$site_push_sw_offline = pg_site_push_app_mode()
    ? pg_site_push_worker_offline(
        PATH,
        SOFTWARE_DIRECTORY,
        (string) lang(array('info' => true)),
        lang('You are offline'),
        lang('This page could not be loaded. Check your connection and try again.'),
        lang('Try again')
    )
    : array();

echo pg_site_push_worker_prelude(
    $site_push_sw_settings['door'],
    defined('TITLE') ? TITLE : '',
    lang('Open the site to see what is new.'),
    $site_push_sw_icon,
    $site_push_sw_offline
);
echo $site_push_sw_script;
