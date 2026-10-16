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
validate_area_access($user, 'designer');

validate_token_field();

// A copy needs an existing designer region. An id that is missing, unknown or
// sent as an array (?id[]=x) stops on "not found": the copy was made from an
// empty row, or escape() failed on the array with an error page.
$id = is_scalar($_GET['id'] ?? null) ? (int) $_GET['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM cregion WHERE cregion_id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

$designer_region = db_item(
    "SELECT
        cregion_name AS name,
        cregion_content AS content
    FROM cregion
    WHERE cregion_id = '" . escape($_GET['id']) . "'");

$original_name = $designer_region['name'];

$designer_region['name'] = get_unique_name(array(
    'name' => $designer_region['name'],
    'type' => 'designer_region'));

db(
    "INSERT INTO cregion (
        cregion_name,
        cregion_content,
        cregion_designer_type,
        cregion_user,
        cregion_timestamp)
    VALUES (
        '" . escape($designer_region['name']) . "',
        '" . escape($designer_region['content']) . "',
        'yes',
        '" . USER_ID . "',
        UNIX_TIMESTAMP())");

$new_id = mysqli_insert_id(db::$con);

log_activity(lang(array('string'=>'{var:1} ({var:2}) was duplicated','vars'=>array(lang('designer region'), $original_name) )), $_SESSION['sessionusername']);

include_once('liveform.class.php');
$liveform = new liveform('edit_designer_region');

$liveform->add_notice(lang(array('string'=>'The {var:1} has been duplicated, and you are now editing the duplicate.','vars'=>lang('designer region') )) );

header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_designer_region.php?id=' . $new_id);
?>