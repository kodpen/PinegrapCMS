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

// phpinfo() prints environment variables, filesystem paths, loaded modules and
// ini settings; that is server-level information, so this screen is
// administrator-only. validate_user() redirects to the login screen when there
// is no session, and validate_area_access() shows the access-denied screen and
// exits for any lower role, so phpinfo() below never runs for either.
require('init.php');
$user = validate_user();
validate_area_access($user, 'administrator');

phpinfo();
