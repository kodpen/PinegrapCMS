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
 *              2016–2025 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
include_once('includes/designer_access.php');
// The editor is no longer designer-only. A manager or a user with content
// rights gets in at `content` level: text anywhere they may edit, plus the
// blocks an administrator marked as editable areas. What they may actually
// change is decided per node (client) and per save (server) — see
// includes/designer_access.php.
if (pg_designer_access($user) === PG_DESIGNER_ACCESS_NONE) {
    validate_area_access($user, 'designer');
}

include_once('liveform.class.php');
include_once('includes/designer_screen.php');

$liveform = new liveform('edit_system_style', $_REQUEST['id']);
$liveform->assign_field_value('id', $_REQUEST['id']);
$liveform->assign_field_value('send_to', isset($_REQUEST['send_to']) ? $_REQUEST['send_to'] : '');

$style_id = (int)$liveform->get_field_value('id');

// Shared design fields. `style.*` so the asset columns added by the
// multi-page step of 2026.4.4 are picked up when present and simply absent
// when the database has not been upgraded yet.
$row = db_item(
    "SELECT style.*, user.user_username AS last_modified_username
     FROM style
     LEFT JOIN user ON style.style_user = user.user_id
     WHERE style.style_id = '$style_id'");
if (!$row) {
    output_error(lang('The style could not be found.'));
}

$style = array(
    'name'                              => trim((string)$row['style_name']),
    'theme_id'                          => $row['theme_id'],
    'collection'                        => $row['collection'],
    'social_networking_position'        => $row['social_networking_position'],
    'additional_body_classes'           => $row['additional_body_classes'],
    'style_head'                        => isset($row['style_head']) ? $row['style_head'] : '',
    'style_empty_cell_width_percentage' => $row['style_empty_cell_width_percentage'],
    'style_custom_css'                  => _filter_orphan_designer_files(isset($row['style_custom_css'])   ? (string)$row['style_custom_css']   : ''),
    'style_custom_js'                   => _filter_orphan_designer_files(isset($row['style_custom_js'])    ? (string)$row['style_custom_js']    : ''),
    'style_custom_fonts'                => isset($row['style_custom_fonts']) ? (string)$row['style_custom_fonts'] : '',
    'last_modified_timestamp'           => $row['style_timestamp'],
    'last_modified_username'            => (string)$row['last_modified_username'],
);

// Every page on this design, in creation order. Also performs the legacy
// name-match link for designs saved by the single-page editor.
$pages = pg_designer_load_pages($style_id, $style['name']);

// Bridge for designs that pre-date the migration AND have not been opened
// since: the assets are still on the (first) page. Read them from there so
// the assets panel is not empty on first open; the save writes them to the
// style and clears the page copies.
if ($style['style_custom_css'] === '' && $style['style_custom_js'] === '' && $style['style_custom_fonts'] === '' && !empty($pages)) {
    $legacy = db_item(
        "SELECT page_custom_css, page_custom_js, page_custom_fonts FROM page
         WHERE page_id = '" . (int)$pages[0]['page_id'] . "' LIMIT 1");
    if (is_array($legacy)) {
        $style['style_custom_css']   = _filter_orphan_designer_files((string)$legacy['page_custom_css']);
        $style['style_custom_js']    = _filter_orphan_designer_files((string)$legacy['page_custom_js']);
        $style['style_custom_fonts'] = (string)$legacy['page_custom_fonts'];
    }
}

$from_pages = (isset($_GET['from']) && $_GET['from'] === 'pages')
           || ($liveform->field_in_session('from') && $liveform->get_field_value('from') === 'pages');

if (!$_POST) {
    if ($liveform->field_in_session('name') == FALSE) {
        $liveform->assign_field_value('name', $style['name']);
        $liveform->assign_field_value('theme_id', $style['theme_id']);
        $liveform->assign_field_value('additional_body_classes', $style['additional_body_classes']);
        $liveform->assign_field_value('collection', $style['collection']);
        $liveform->assign_field_value('style_head', $style['style_head']);
        $liveform->assign_field_value('style_empty_cell_width_percentage', $style['style_empty_cell_width_percentage']);
        if (SOCIAL_NETWORKING == TRUE) {
            $liveform->assign_field_value('social_networking_position', $style['social_networking_position']);
        }
    }

    // Delete is offered once nothing LIVE uses the style: a design whose
    // pages were all sent to the recycle bin counts as empty, and deleting
    // it purges those binned pages with it (they could only ever come back
    // onto this design).
    $in_use = pg_designer_style_live_usage($style_id);
    if ($in_use > 0) {
        $delete_button = '<button type="button" class="sd-icon-btn sd-icon-btn-danger disabled" title="' . lang('You may not delete this page style because it is being used by at least one folder or page.') . '"><span class="bi bi-trash"></span></button>';
    } else {
        $delete_button = '<button type="button" id="sd-delete-style" class="sd-icon-btn sd-icon-btn-danger" title="' . lang('Delete') . '" data-confirm-content="' . lang(array('string' => 'WARNING: This {var:1} will be permanently deleted.', 'vars' => array(lang('page style')))) . '"><span class="bi bi-trash"></span></button>';
    }

    $active = isset($_GET['page']) ? (int)$_GET['page'] : 0;
    if ($active <= 0 && !empty($pages)) $active = (int)$pages[0]['page_id'];

    pg_designer_screen_render(array(
        'mode'           => 'edit',
        'style_id'       => $style_id,
        'style'          => $style,
        'pages'          => $pages,
        'active_page_id' => $active,
        'liveform'       => $liveform,
        'user'           => $user,
        'send_to'        => $liveform->get_field_value('send_to'),
        'from_pages'     => $from_pages,
        'delete_button'  => $delete_button,
    ));

    $liveform->remove_form();

} else {
    validate_token_field();
    $liveform->add_fields_to_session();

    // Delete the design. Reachable only when no page or folder uses it (the
    // button is disabled otherwise), but re-checked here — the POST does not
    // have to come from that screen.
    if (!empty($_POST['submit_delete'])) {
        if (pg_designer_style_live_usage($style_id) > 0) {
            output_error(lang('You may not delete this page style because it is being used by at least one folder or page.'));
        }
        // Pages of this design that sit in the recycle bin stay there: each
        // carries its own tree, so a restored page can join another design
        // through "Select Page".
        db("DELETE FROM style WHERE style_id = '$style_id'");
        db("DELETE FROM system_style_cells WHERE style_id = '$style_id'");
        db("DELETE FROM preview_styles WHERE style_id = '$style_id'");
        log_activity(lang(array('string' => 'style ({var:1}) was deleted', 'vars' => array($style['name']))), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $lf_view = new liveform('view_system_styles');
        $lf_view->add_notice(lang('The style has been deleted.'));
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_system_styles.php');
        exit();
    }

    pg_designer_screen_post(array(
        'mode'       => 'edit',
        'style_id'   => $style_id,
        'liveform'   => $liveform,
        'user'       => $user,
        'from_pages' => $from_pages,
    ));
}
?>
