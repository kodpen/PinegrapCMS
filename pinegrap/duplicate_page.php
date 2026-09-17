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

require_once(dirname(__FILE__) . '/duplicate_page_f.php');

$page['id'] = $_GET['id'];

$response = duplicate_page(array('page' => $page));

if ($response['status'] == 'error') {
    output_error(h($response['message']));
}

$new_page = $response['page'];

// Where the copy is edited, which for a visual design is the editor and not
// the properties form: that is where its body lives, and it is what the
// operator was looking at when they asked for a copy.
$edit_row = pg_page_edit_row($new_page['id']);
$edit_url = is_array($edit_row) ? pg_page_edit_url($edit_row) : ('edit_page.php?id=' . (int) $new_page['id']);

// The notice has to be filed under the form of the screen that will draw it,
// or it sits unread in the session and comes back on some later visit.
if (is_array($edit_row) && pg_page_is_visual_design($edit_row)) {
    $liveform_duplicate = new liveform('edit_system_style', (int) $edit_row['page_style']);
} else {
    $liveform_duplicate = new liveform('edit_page');
}

$liveform_duplicate->add_notice(lang('The page has been duplicated. You are now editing the duplicate.'));

header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/' . $edit_url
    . (strpos($edit_url, '?') === false ? '?' : '&') . 'from=' . urlencode($_GET['from'] ?? ''));