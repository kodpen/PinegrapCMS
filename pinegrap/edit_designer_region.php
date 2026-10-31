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

if (!isset($_POST['name'])) {
    // Get designer region information.
    $query = "SELECT cregion_name, cregion_content, cregion_designer_type "
            ."FROM cregion "
            ."WHERE cregion_id = ?";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed');
    $row = mysqli_fetch_array($result);
    $cregion_name = $row['cregion_name'];
    $cregion_content = $row['cregion_content'];

    // The stored values, or the posted ones after a refused name.
    $form_name = $cregion_name;
    $form_content = $cregion_content;
    $form_type = 'designer';
    $name_taken = $liveform->check_field_error('name');

    if ($liveform->field_in_session('name')) {
        list($form_name, $form_content) = pg_designer_region_form_values(array('name' => $liveform->get_field_value('name'), 'content' => $liveform->get_field_value('content')));
        $form_type = ($liveform->get_field_value('type') == 'common') ? 'common' : 'designer';
    }

    print
    pg_page_shell([
        'title'=> lang(array('string'=>'Edit {var:1}','vars'=>lang('Designer Region'))),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang(array('string'=>'Edit {var:1}','vars'=>lang('Designer Region'))),
        'heading_description' => lang('Update this designer region of shared content. (A rename will require its tag to be updated in any page styles.)'),
        'cancel'=>array('enable'=>'true','url'=>pg_send_to_url('view_regions.php?filter=all_designer_regions')),
        'breadcrumb' => array(
            array('label' => lang('All Designer Regions'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_designer_regions'),
            array('label' => lang(array('string'=>'Edit {var:1}','vars'=>lang('Designer Region')))),
        ),
    ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-3  flex-wrap">
                    <div class="col-12 sm:col-12 text-center md:text-start">
                        
                        <p>' . lang('Page Style Body Tag') . ': <strong>' . h('<cregion>' . $cregion_name . '</cregion>') . '</strong></p>
                        
                        <nav id="button_bar" class="navigation " aria-label="' . h(lang('Button Bar')) . '">
                            <div class=" btn-group btn-group-sm flex-wrap">
                                <a class="btn btn-link pg-link-secondary py-0 mb-3 " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/duplicate_designer_region.php?id=' . h(escape_javascript(urlencode($_REQUEST['id']))) . get_token_query_string_field() . '"><span class="material-icons me-1">control_point_duplicate</span>' . lang('Duplicate') . '</a>
                            </div>
                        </nav>
                    </div>
                </div>
                <form name="form" action="edit_designer_region.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '">
                    <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '" />
                    ' . pg_designer_region_form_card($form_name, $form_content, '
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="type" class="form-label">' . lang('Region Type') . '</label>
                                            <select name="type" id="type" class="form-control"><option value="common"' . ($form_type == 'common' ? ' selected="selected"' : '') . '>' . lang('Common Region') . '</option><option value="designer"' . ($form_type == 'designer' ? ' selected="selected"' : '') . '>' . lang('Designer Region') . '</option></select>
                                            <div class="form-text text-end">' . lang('You can change Region Type') . '</div>
                                        </div>', $name_taken) . '
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('designer region')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
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
        $query = "DELETE FROM cregion "
                ."WHERE cregion_id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed');
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was deleted','vars'=>array(lang('designer region'), $_POST['name']) )), $_SESSION['sessionusername']);


        // if there is not a send to, then prepare notice
        if ($_POST['send_to'] == '') {
            $notice = lang(array('string'=>'{var:1} was deleted successfully','vars'=>lang('Designer Region') ));
        }
        
    } else {
        list($name, $content) = pg_designer_region_form_values($_POST);

        // A rename onto a name another region already has is refused: the
        // site reads only one row per name. The screen comes back with the
        // posted values.
        if (pg_cregion_name_taken($name, $id)) {
            $liveform->add_fields_to_session(array('trim' => false));
            $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
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