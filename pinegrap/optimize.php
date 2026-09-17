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
validate_area_access($user, 'user');

validate_token_field();

$file = db_item(
    "SELECT
        id,
        name,
        design,
        folder AS folder_id,
        description,
        type,
        size,
        optimized
    FROM files
    WHERE id = '" . e($_GET['id']) . "'");

if (!$file) {
    output_error(lang('Sorry, we could not find that file.'));
}

// If the user does not have edit rights to this file's folder, or this file is a design file and
// the user is not a designer or administrator, then log activity and output error.
if (!check_edit_access($file['folder_id']) or ($file['design'] and (USER_ROLE > 1))) {
    log_activity(lang('access denied to optimize image because user does not have access to file'));
    output_error(lang('Access denied.'));
}

require(dirname(__FILE__) . '/optimize_image.php');

// 'resize' also scales the image down; anything else means compress at the
// same pixel size, which is what every link that predates this parameter
// sends. Read as an exact match rather than a truthiness test so a stray
// value cannot turn the plain button into the one that changes dimensions.
$mode = ((($_GET['mode'] ?? '') === 'resize') ? 'resize' : 'optimize');

$response = optimize_image($file['id'], $mode);

if ($response['status'] == 'error') {
    output_error(h($response['message']));
}

// The File Management widget sends the operator here from the dashboard, not
// from a file list, so put them back on the dashboard instead of leaving them
// in view_files.php with no memory of where they started.
//
// "welcome" is a fixed keyword, not a URL, and it is turned into a hard-coded
// address below — nothing arriving in the query string can steer the redirect.
if (($_GET['send_to'] ?? '') == 'welcome') {
    $form = new liveform('welcome');
    $form->add_notice(h($response['message']));
    go(PATH . SOFTWARE_DIRECTORY . '/welcome.php');

} elseif (($_GET['send_to'] ?? '') == PATH . SOFTWARE_DIRECTORY . '/view_design_files.php') {
    $form = new liveform('view_design_files');
    $form->add_notice(h($response['message']));
    go(($_GET['send_to'] ?? ''));

} else {
    $form = new liveform('view_files');
    $form->add_notice(h($response['message']));
    go(PATH . SOFTWARE_DIRECTORY . '/view_files.php');
}