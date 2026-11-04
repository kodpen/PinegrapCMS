<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Page Speed is a section of the Visitors overview (includes/visitors/
 * speed.php). An old link to this screen is sent there for good, with its
 * period, device and order kept.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_visitors_access($user);

header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/visitors.php?' . http_build_query(pg_visitor_insight_overview_query($_GET, array('device' => 'speed_device', 'order' => 'speed_order'))) . '#speed', true, 301);
exit();
