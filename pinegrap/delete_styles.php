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

include_once('liveform.class.php');
$liveform = new liveform('view_styles');

// If a style was not selected, then output error.
if (!$_POST['styles']) {
    $liveform->mark_error('', lang('You must select at least one page style to delete.'));
    go(PATH . SOFTWARE_DIRECTORY . '/view_styles.php');
}

$number_of_deleted_styles = 0;
$number_of_preserved_styles = 0;

// Once the bin holds styles, a style deleted here goes there and can be
// restored from the file manager's Recycle Bin. Each one is logged by name
// on the way in.
require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
$recycle_styles = pg_recycle_style_ready();

// Loop through all styles that where selected.
foreach ($_POST['styles'] as $style_id) {
    // If this style is being used by a folder or page,
    // then don't delete it.
    if (
        (
            db_value(
                "SELECT COUNT(*)
                FROM folder
                WHERE
                    (folder_style = '" . escape($style_id) . "')
                    OR (mobile_style_id = '" . escape($style_id) . "')")
        )
        ||
        (
            db_value(
                "SELECT COUNT(*)
                FROM page
                WHERE
                    (page_style = '" . escape($style_id) . "')
                    OR (mobile_style_id = '" . escape($style_id) . "')")
        )
    ) {
        $number_of_preserved_styles++;

    // Otherwise this style is not being used, so delete it.
    } elseif ($recycle_styles) {
        $recycled = pg_recycle_style($style_id, $user);

        if ($recycled['status'] == 'binned') {
            $number_of_deleted_styles++;
        }

    } else {
        db("DELETE FROM style WHERE style_id = '" . escape($style_id) . "'");
        db("DELETE FROM system_style_cells WHERE style_id = '" . escape($style_id) . "'");
        db("DELETE FROM preview_styles WHERE style_id = '" . escape($style_id) . "'");

        // Its prototype share links end with it: a later design may get its id.
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_links.php');
        pg_proto_links_revoke_design($style_id, (int) $user['id']);

        $number_of_deleted_styles++;
    }
}

// If at least one style was deleted, then prepare message for that and log activity.
if (($number_of_deleted_styles > 0) && $recycle_styles) {
    $liveform->add_notice(lang(array('string' => '{var:1} page style(s) were moved to the Recycle Bin.', 'vars' => pg_format_number($number_of_deleted_styles, 0))));

} elseif ($number_of_deleted_styles > 0) {
    if ($number_of_deleted_styles == 1) {
        $message = lang('1 page style was deleted');
    } else {
        $message = lang(array('string'=>'{var:1} page styles were deleted','vars'=>pg_format_number($number_of_deleted_styles, 0) ));
    }

    log_activity($message, $_SESSION['sessionusername']);

    $message .= '.';

    $liveform->add_notice($message);
}

// If at least one style was preserved, then add extra message for that 
if ($number_of_preserved_styles > 0) {
    if ($number_of_preserved_styles == 1) {
        $message = lang('1 page style was not deleted because it is being used by a folder or page.');

    } else {
        $message = lang(array('string'=>'{var:1} page styles were not deleted because they are being used by a folder or page.','vars'=>pg_format_number($number_of_preserved_styles, 0) ));
    }

    $liveform->mark_error('', $message);
}

go(PATH . SOFTWARE_DIRECTORY . '/view_styles.php');
?>