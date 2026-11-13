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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/common_region_form.php');

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM cregion WHERE cregion_id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

include_once('liveform.class.php');
$liveform = new liveform('edit_common_region');

// A region made a shared component is drawn by the component; its content
// here is no longer read, and the type stays common.
require_once(PG_FUNCTIONS_DIR . '/includes/designer_access.php');
$component_id = pg_common_region_components(array($id))[$id];

// A designer region opened here (a stale recent-items link, a type changed
// on the list, a hand-typed id) goes to its own screen: this one would show
// it as common and save it as common, through the rich text filter.
if (!isset($_POST['name']) && !isset($_POST['submit_convert']) && !$component_id
    && (db_value("SELECT cregion_designer_type FROM cregion WHERE cregion_id = '" . $id . "'") === 'yes')) {
    $send_to = is_scalar($_GET['send_to'] ?? null) ? (string) $_GET['send_to'] : '';
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_designer_region.php?id=' . $id . (($send_to !== '') ? '&send_to=' . urlencode($send_to) : ''));
    exit();
}

// The conversion form posts no name: it is a POST all the same.
if (!isset($_POST['name']) && !isset($_POST['submit_convert'])) {
    $query = "SELECT cregion_name, cregion_content, cregion_designer_type, cregion_timestamp, user.user_username "
            ."FROM cregion "
            ."LEFT JOIN user ON cregion.cregion_user = user.user_id "
            ."WHERE cregion_id = ?";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed');
    $row = mysqli_fetch_array($result);
    $cregion_name = $row['cregion_name'];
    $cregion_content = $row['cregion_content'];
    $cregion_designer_type = $row['cregion_designer_type'];
    $cregion_timestamp = (int) $row['cregion_timestamp'];
    $cregion_username = (string) $row['user_username'];

    // The stored values, or the posted ones after a refused name.
    $form_name = $cregion_name;
    $form_content = $cregion_content;
    $form_type = 'common';
    $name_error = pg_region_form_name_error_text($liveform);

    if ($liveform->field_in_session('name')) {
        list($form_name, $form_content) = pg_common_region_form_values(array('name' => $liveform->get_field_value('name'), 'content' => $liveform->get_field_value('content')));
        $form_type = ($liveform->get_field_value('type') == 'designer') ? 'designer' : 'common';
    }

    if ($component_id) {
        $component_name = (string) db_value("SELECT name FROM shared_components WHERE id = '" . (int) $component_id . "'");
        $output_component =
            '<div class="alert theme-primary my-7">
                <p>' . h(lang(array('string' => 'This common region is now the shared component "{var:1}". Its content is edited in the Page Design Studio, by managers and by the users granted the component. The region\'s earlier content is kept and comes back if the component is deleted.', 'vars' => $component_name))) . '</p>
                <a class="btn btn-sm btn-solid theme-primary" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_system_style.php?component=' . (int) $component_id . '&amp;send_to=' . h(urlencode(get_request_uri())) . '"><span class="material-icons me-1">edit</span>' . h(lang('Edit in the Page Design Studio')) . '</a>
            </div>';
    } elseif (($user['role'] <= 1) && pg_shared_component_content_ready()) {
        $output_component =
            '<form class="alert theme-warning d-flex gap-3 align-items-start my-7 disable_shortcut" action="edit_common_region.php" method="post">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $id . '">
                <div class="flex-grow-1">
                    <p>' . h(lang('Make this region a shared component to edit its content in the Page Design Studio. Its tag then draws the component on every page; managers and the users granted this region edit the component, and front-end inline editing of the region ends. Deleting the component brings back the content kept here.')) . '</p>
                    <button type="submit" name="submit_convert" value="Convert" class="btn btn-sm btn-solid theme-primary" data-confirm-content="' . h(lang('Make this common region a shared component? Every page will draw the component from now on.')) . '"><span class="material-icons me-1">widgets</span>' . h(lang('Make shared component')) . '</button>
                </div>
            </form>';
    } else {
        $output_component = '';
    }

    // Summary rows of the status column: the component drawing the region,
    // then when and by whom it was last saved.
    $output_status = '';

    if ($component_id) {
        $output_status .= pg_common_region_form_status_row(lang('Shared Component'), h($component_name), false);
    }

    $output_status .= pg_common_region_form_status_row(lang('Last Modified'), get_relative_time(array('timestamp' => $cregion_timestamp)) . ($cregion_username !== '' ? ' ' . h($cregion_username) : ''));

    $output = '';
    $output .= 
    pg_page_shell([
        'title'=> lang('Edit Common Region'),
        'extra classes'=>'page pg-measure-edit',
        'icon'=>'page',
        'heading'=>lang('Edit Common Region'),
        'heading_description' => lang('Update this common region of shared content. (A rename will require its tag to be updated in any page styles.)'),
        'cancel'=>array('enable'=>'true','url'=>'view_regions.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Common Regions'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_common_regions'), array('label' => lang('Edit Common Region'))),
        ]) . '
<main id="content" class="container-fluid">
    ' . pg_screen_toolbar(array(
        'secondary' => array(
            '<a class="btn btn-sm btn-text theme-secondary" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/duplicate_common_region.php?id=' . h(escape_javascript(urlencode($_REQUEST['id']))) . get_token_query_string_field() . '"><i class="bi bi-copy me-1" aria-hidden="true"></i>' . lang('Duplicate') . '</a>',
        ),
    )) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                ' . $output_component . '
                <form name="form" action="edit_common_region.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '">
                    ' . pg_common_region_form_layout(
                        pg_common_region_form_cards($form_name, $form_content, array(
                            'type' => $form_type,
                            'type_locked' => (bool) $component_id,
                            'with_content' => !$component_id,
                            'name_error' => $name_error,
                            'status' => $output_status,
                        )),
                        '
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" formnovalidate class="btn my-1  btn-solid theme-danger " data-confirm-content="' . h($component_id ? lang('This region is a shared component and cannot be deleted while the component exists. Delete the component first.') : lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('common region'))))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>'
                    ) . '
                </form>
            </div>
        </div>
    
</main>' .
    output_footer();
    
    if (!$component_id) {
        // Get wysiwyg editor code
        $output_wysiwyg_editor_code = get_wysiwyg_editor_code(array('content_textarea'), $activate_editors = true);

        // Puts the wysiwyg code in the header.
        $output = preg_replace('/(<\/head>)/i', $output_wysiwyg_editor_code . '$1', $output);
    }
    
    print $output;

    $liveform->remove_form();

} else {
    validate_token_field();
    
    // Make the region a shared component, then come back to this screen.
    if (($_POST['submit_convert'] ?? '') == 'Convert') {
        if (($user['role'] > 1) || !pg_shared_component_content_ready()) {
            output_error(lang('Access denied.'));
        }

        require_once(PG_FUNCTIONS_DIR . '/includes/designer_region_import.php');
        $warnings = array();
        $made = pg_common_region_component($id, $user, $warnings);

        if (!is_array($made)) {
            $liveform->add_error(lang('The region could not be made a shared component.'));
        } else {
            $liveform->add_notice(h(lang(array('string' => 'The common region is now the shared component "{var:1}".', 'vars' => $made['name']))));

            foreach ($warnings as $warning) {
                $liveform->add_warning(h($warning));
            }
        }

        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_common_region.php?id=' . $id);
        exit();
    }

    // if region was selected for delete
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // The row is the <cregion> tag's only link to its shared component
        // and the component's undo: while the component exists the region
        // stays, and the notice points at the component.
        if ($component_id) {
            $component_name = (string) db_value("SELECT name FROM shared_components WHERE id = '" . (int) $component_id . "'");
            $liveform->add_error(
                h(lang(array('string' => 'This common region is the shared component "{var:1}". Delete the component first; its content comes back here, then the region can be deleted.', 'vars' => $component_name)))
                . ' <a href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_system_style.php?component=' . (int) $component_id . '">' . h(lang('Edit in the Page Design Studio')) . '</a>');
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_common_region.php?id=' . $id);
            exit();
        }

        // The region and the users' access to it.
        pg_cregion_delete($id) or output_error('Query failed');
        
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was deleted','vars'=>array(lang('common region'), $_POST['name']) )), $_SESSION['sessionusername']);
        $notice = lang(array('string'=>'{var:1} was deleted successfully','vars'=>lang('Common Region') ));
    } else {
        list($name, $content) = pg_common_region_form_values($_POST);

        // A blank or too long name, or a rename onto a name another region
        // already has (the site reads only one row per name), is refused. The
        // screen comes back with the posted values.
        $name_error = pg_region_form_name_refusal($name, pg_cregion_name_taken($name, $id));

        if ($name_error !== '') {
            $liveform->add_fields_to_session(array('trim' => false));
            $liveform->mark_error('name', $name_error);
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_common_region.php?id=' . $id);
            exit();
        }

        if ($_POST['type'] == 'common') {
            $cregion_designer_type = 'no';
        } else {
            $cregion_designer_type = 'yes';
        }

        // A converted region keeps its content and its type: the component
        // draws it.
        if ($component_id) {
            $cregion_designer_type = 'no';
            $new_content = null;
        } else {
            $new_content = (string) prepare_rich_text_editor_content_for_input($content);
        }

        pg_cregion_save($id, $name, $new_content, $cregion_designer_type, (int) $user['id']) or output_error('Query failed');
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was modified','vars'=>array(lang('common region'), $name) )), $_SESSION['sessionusername']);
        $notice = lang(array('string'=>'{var:1} was edited successfully','vars'=>lang('Common Region') ));

    }
    
    $liveform_view_styles = new liveform('view_regions');
    $liveform_view_styles->add_notice($notice);
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_' . $_POST['type'] . '_regions');
}