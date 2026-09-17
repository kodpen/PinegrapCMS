<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'manager');
validate_token_field();

// The work itself is pg_purge_caches() in functions.php. This screen is one of
// two doors onto it -- the other is api.php, which the System Status widget
// calls so that clearing the cache no longer throws the operator off the
// dashboard onto the settings screen. Keeping the body here as well would have
// meant two purges that could quietly come to clear different things.
$purge = pg_purge_caches();

log_activity(lang(array('string' => 'Cache purged ({var:1}).', 'vars' => array($purge['message']))), $_SESSION['sessionusername']);

include_once('liveform.class.php');
$liveform = new liveform('settings');
$liveform->add_notice(lang(array('string' => 'Cache cleared: {var:1}', 'vars' => array($purge['message']))));
header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/' . pg_settings_return_url());
exit();
