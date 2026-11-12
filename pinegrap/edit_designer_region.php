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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/designer_region_form.php');

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM cregion WHERE cregion_id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

include_once('liveform.class.php');
$liveform = new liveform('edit_designer_region');

// A region made a shared component is drawn by the component; its row stays
// until the component is deleted.
require_once(PG_FUNCTIONS_DIR . '/includes/designer_access.php');
$component_id = pg_common_region_components(array($id))[$id];

// A common region opened here (a stale recent-items link, a type changed
// on the list, a hand-typed id) goes to its own screen, which keeps the
// type and the shared component's guard.
if (!isset($_POST['name']) && (db_value("SELECT cregion_designer_type FROM cregion WHERE cregion_id = '" . $id . "'") !== 'yes')) {
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_common_region.php?id=' . $id);
    exit();
}

if (!isset($_POST['name'])) {
    // Get designer region information.
    $query = "SELECT cregion_name, cregion_content, cregion_designer_type, cregion_timestamp, user.user_username "
            ."FROM cregion "
            ."LEFT JOIN user ON cregion.cregion_user = user.user_id "
            ."WHERE cregion_id = ?";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed');
    $row = mysqli_fetch_array($result);
    $cregion_name = $row['cregion_name'];
    $cregion_content = $row['cregion_content'];
    $cregion_timestamp = (int) $row['cregion_timestamp'];
    $cregion_username = (string) $row['user_username'];

    // The stored values, or the posted ones after a refused name.
    $form_name = $cregion_name;
    $form_content = $cregion_content;
    $form_type = 'designer';
    $name_error = pg_region_form_name_error_text($liveform);

    if ($liveform->field_in_session('name')) {
        list($form_name, $form_content) = pg_designer_region_form_values(array('name' => $liveform->get_field_value('name'), 'content' => $liveform->get_field_value('content')));
        $form_type = ($liveform->get_field_value('type') == 'common') ? 'common' : 'designer';
    }

    // Summary row of the status column: when and by whom it was last saved.
    $output_status = pg_common_region_form_status_row(lang('Last Modified'), get_relative_time(array('timestamp' => $cregion_timestamp)) . ($cregion_username !== '' ? ' ' . h($cregion_username) : ''));

    print
    pg_page_shell([
        'title'=> lang('Edit Designer Region'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('Edit Designer Region'),
        'heading_description' => lang('Update this designer region of shared content. (A rename will require its tag to be updated in any page styles.)'),
        'cancel'=>array('enable'=>'true','url'=>pg_send_to_url('view_regions.php?filter=all_designer_regions')),
        'breadcrumb' => array(
            array('label' => lang('All Designer Regions'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_designer_regions'),
            array('label' => lang('Edit Designer Region')),
        ),
    ]) . '
<main id="content" class="container-fluid">
    ' . pg_screen_toolbar(array(
        'secondary' => array(
            '<a class="btn btn-sm btn-text theme-secondary" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/duplicate_designer_region.php?id=' . h(escape_javascript(urlencode($_REQUEST['id']))) . get_token_query_string_field() . '"><i class="bi bi-copy me-1" aria-hidden="true"></i>' . lang('Duplicate') . '</a>',
        ),
    )) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                <form name="form" action="edit_designer_region.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '">
                    <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '" />
                    ' . pg_common_region_form_layout(
                        pg_designer_region_form_cards($form_name, $form_content, array(
                            'type' => $form_type,
                            'name_error' => $name_error,
                            'status' => $output_status,
                        )),
                        '
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" formnovalidate class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('designer region')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>'
                    ) . '
                </form>
            </div>
        </div>
    
</main>' .
    output_footer();

$liveform->remove_form();
    
} else {
    validate_token_field();
    
    // if region was selected for delete
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // The row is the <cregion> tag's only link to its shared component
        // and the component's undo: while the component exists the region
        // stays, and its own screen points at the component.
        if ($component_id) {
            $component_name = (string) db_value("SELECT name FROM shared_components WHERE id = '" . (int) $component_id . "'");
            $liveform_common = new liveform('edit_common_region');
            $liveform_common->add_error(
                h(lang(array('string' => 'This common region is the shared component "{var:1}". Delete the component first; its content comes back here, then the region can be deleted.', 'vars' => $component_name)))
                . ' <a href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_system_style.php?component=' . (int) $component_id . '">' . h(lang('Edit in the Page Design Studio')) . '</a>');
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_common_region.php?id=' . $id);
            exit();
        }

        // Delete skips the browser's form check, so the posted name may be
        // blank: the log names the stored one.
        $stored_name = (string) db_value("SELECT cregion_name FROM cregion WHERE cregion_id = '" . $id . "'");
        $query = "DELETE FROM cregion "
                ."WHERE cregion_id = ?";
        $result = db_q($query, array((string) $id)) or output_error('Query failed');

        // The users granted the region lose the grant with it.
        $query = "DELETE FROM users_common_regions_xref WHERE common_region_id = ?";
        $result = db_q($query, array((string) $id)) or output_error('Query failed');

        log_activity(lang(array('string'=>'{var:1} ({var:2}) was deleted','vars'=>array(lang('designer region'), $stored_name) )), $_SESSION['sessionusername']);


        // if there is not a send to, then prepare notice
        if ($_POST['send_to'] == '') {
            $notice = lang(array('string'=>'{var:1} was deleted successfully','vars'=>lang('Designer Region') ));
        }
        
    } else {
        // A stale form of a region made a shared component meanwhile: its
        // kept content is the component's undo and is not overwritten here.
        if ($component_id) {
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_common_region.php?id=' . $id);
            exit();
        }

        list($name, $content) = pg_designer_region_form_values($_POST);

        // A blank or too long name, or a rename onto a name another region
        // already has (the site reads only one row per name), is refused. The
        // screen comes back with the posted values.
        $name_error = pg_region_form_name_refusal($name, pg_cregion_name_taken($name, $id));

        if ($name_error !== '') {
            $liveform->add_fields_to_session(array('trim' => false));
            $liveform->mark_error('name', $name_error);
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_designer_region.php?id=' . $id . ((($_POST['send_to'] ?? '') != '') ? '&send_to=' . urlencode((string) $_POST['send_to']) : ''));
            exit();
        }

        if ($_POST['type'] == 'common') {
            $cregion_designer_type = 'no';
        } else {
            $cregion_designer_type = 'yes';
        }
        
        $query = "UPDATE cregion "
                ."SET cregion_name = ?, "
                    ."cregion_content = ?, "
                    ."cregion_designer_type = ?, "
                    ."cregion_user = ?, "
                    ."cregion_timestamp = UNIX_TIMESTAMP() "
                ."WHERE cregion_id = ?";
        // update region
        $result = db_q($query, array(
            $name,
            $content,
            (string) $cregion_designer_type,
            (string) $user['id'],
            (string) ($_POST['id'] ?? ''),
        )) or output_error('Query failed');
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was modified','vars'=>array(lang('designer region'), $name) )), $_SESSION['sessionusername']);
        
        // if there is not a send to, then prepare notice
        if ($_POST['send_to'] == '') {
            $notice = lang(array('string'=>'{var:1} was edited successfully','vars'=>lang('Designer Region') ));
        }
    }
    
    // if there is a send to, then forward user to send to
    if ($_POST['send_to'] != '') {
        header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_POST['send_to'] ?? '')));
        
    // else there is not a send to, so prepare notice and send user to view ads screen
    } else {
        $liveform_view_styles = new liveform('view_regions');
        $liveform_view_styles->add_notice($notice);
        
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_' . $_POST['type'] . '_regions');
    }
}
?>