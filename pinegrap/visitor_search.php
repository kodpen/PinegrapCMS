<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Search is a section of the Visitors overview (includes/visitors/
 * site_search.php). An old link to this screen is sent there for good,
 * with its period kept (pg_site_search_overview_query()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_visitors_access($user);

header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/visitors.php?' . http_build_query(pg_site_search_overview_query($_GET['days'] ?? 30)) . '#site_search', true, 301);
exit();
