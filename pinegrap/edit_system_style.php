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
    // Absent before 2026.4.5: every design until then was Bootstrap 5.
    'framework'                         => pg_design_framework_key(isset($row['style_framework']) ? $row['style_framework'] : ''),
    'template'                          => isset($row['style_template']) ? (string)$row['style_template'] : '',
    'template_version'                  => isset($row['style_template_version']) ? (string)$row['style_template_version'] : '',
    // Absent before 2026.4.5 (5.3): plain Bootstrap.
    'look'                              => pg_design_look_key(isset($row['style_look']) ? $row['style_look'] : ''),
    'palette'                           => pg_design_palette_key(isset($row['style_palette']) ? $row['style_palette'] : ''),
    // Absent before 2026.4.8 (8.19): the tabs follow page_id.
    'tab_layout'                        => isset($row['style_tab_layout']) ? (string)$row['style_tab_layout'] : '',
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

    // Deleting the design sends its live pages to the recycle bin and clears
    // the folders that use it (pg_designer_delete_design()); the button is
    // there for those who may do that, and the warning says what will happen.
    $delete_button = '';
    if (pg_designer_is_full($user)) {
        $del_folders = (int)db_value("SELECT COUNT(folder_id) FROM folder WHERE folder_style = '$style_id' OR mobile_style_id = '$style_id'");
        $del_message = str_replace('{name}', $style['name'], lang('The design "{name}" will be deleted.')) . ' '
            . (count($pages) > 0
                ? str_replace('{n}', count($pages), lang('Its {n} page(s) will be moved to the Recycle Bin.'))
                : lang('It has no pages.'));
        if ($del_folders > 0) {
            $del_message .= ' ' . lang(array('string' => '{var:1} folder(s) use it as their default design; that setting will be cleared.', 'vars' => $del_folders));
        }
        $delete_button = '<button type="button" id="sd-delete-style" class="sd-icon-btn sd-icon-btn-danger" title="' . lang('Delete') . '" data-confirm-content="' . h($del_message) . '"><span class="bi bi-trash"></span></button>';
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

    // Delete the design. The POST does not have to come from the toolbar,
    // so every check is the function's own; collab_key is this editor tab,
    // which must not count as somebody else having the design open.
    if (!empty($_POST['submit_delete'])) {
        $dd = pg_designer_delete_design($style_id, $user, isset($_POST['collab_key']) ? (string)$_POST['collab_key'] : '');
        if (!$dd['ok']) {
            output_error(h($dd['error']));
        }
        $dd_message = $dd['binned'] > 0
            ? lang(array('string' => 'The design was deleted; {var:1} page(s) were moved to the Recycle Bin.', 'vars' => $dd['binned']))
            : lang('The style has been deleted.');
        if ($dd['home'] > 0) {
            $dd_message .= ' ' . lang('The home page was among them: the site has no home page until another page is marked as home.');
        }
        if ($dd['folders'] > 0) {
            $dd_message .= ' ' . lang(array('string' => '{var:1} folder(s) used it as their default design; that setting was cleared.', 'vars' => $dd['folders']));
        }
        $liveform->remove_form();
        $lf_view = new liveform('view_system_styles');
        $lf_view->add_notice($dd_message);
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
