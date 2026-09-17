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

// Duplicating no longer writes a record: the editor opens with the rows of the
// source offer and nothing exists until the operator saves it under a code.

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

go(PATH . SOFTWARE_DIRECTORY . '/edit_offer.php?duplicate=' . (isset($_GET['id']) ? (int) $_GET['id'] : 0));
