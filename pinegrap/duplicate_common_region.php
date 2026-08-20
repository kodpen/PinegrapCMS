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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */


include('init.php');
$user = validate_user();
validate_area_access($user, 'designer');

validate_token_field();

$common_region = db_item(
    "SELECT
        cregion_name AS name,
        cregion_content AS content
    FROM cregion
    WHERE cregion_id = '" . escape($_GET['id']) . "'");

$original_name = $common_region['name'];

$common_region['name'] = get_unique_name(array(
    'name' => $common_region['name'],
    'type' => 'common_region'));

db(
    "INSERT INTO cregion (
        cregion_name,
        cregion_content,
        cregion_designer_type,
        cregion_user,
        cregion_timestamp)
    VALUES (
        '" . escape($common_region['name']) . "',
        '" . escape($common_region['content']) . "',
        'no',
        '" . USER_ID . "',
        UNIX_TIMESTAMP())");

$new_id = mysqli_insert_id(db::$con);

log_activity(lang(array('string'=>'{var:1} ({var:2}) was duplicated','vars'=>array(lang('common region'), $original_name) )), $_SESSION['sessionusername']);

include_once('liveform.class.php');
$liveform = new liveform('edit_common_region');

$liveform->add_notice(lang(array('string'=>'The {var:1} has been duplicated, and you are now editing the duplicate.','vars'=>lang('common region') )) );

header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_common_region.php?id=' . $new_id);
?>