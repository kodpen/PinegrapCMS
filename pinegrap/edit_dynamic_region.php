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
validate_area_access($user, 'administrator');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/dynamic_region_form.php');

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM dregion WHERE dregion_id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

include_once('liveform.class.php');
$liveform = new liveform('edit_dynamic_region');

if (!isset($_POST['name'])) {
    $query = "SELECT dregion_name, dregion_code, dregion_timestamp, user.user_username "
            ."FROM dregion "
            ."LEFT JOIN user ON dregion.dregion_user = user.user_id "
            ."WHERE dregion_id = ?";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed');
    $row = mysqli_fetch_array($result);
    $dregion_name = $row['dregion_name'];
    $dregion_code = $row['dregion_code'];
    $dregion_timestamp = (int) $row['dregion_timestamp'];
    $dregion_username = (string) $row['user_username'];

    // The stored values, or the posted ones after a refused name.
    $form_name = $dregion_name;
    $form_code = $dregion_code;
    $name_error = pg_region_form_name_error_text($liveform);

    if ($liveform->field_in_session('name')) {
        list($form_name, $form_code) = pg_dynamic_region_form_values(array('name' => $liveform->get_field_value('name'), 'code' => $liveform->get_field_value('code')));
    }

    // Summary row of the status column: when and by whom it was last saved
    // (a dash for a row that never recorded a save time).
    $output_status = pg_common_region_form_status_row(lang('Last Modified'), ($dregion_timestamp > 0 ? get_relative_time(array('timestamp' => $dregion_timestamp)) . ($dregion_username !== '' ? ' ' . h($dregion_username) : '') : '&mdash;'));

    print 
    pg_page_shell([
        'title'=> lang('Edit Dynamic Region'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('Edit Dynamic Region'),
        'heading_description' => lang('Update this dynamic region of shared content. (A rename will require its tag to be updated in any page styles.)'),
        'cancel'=>array('enable'=>'true','url'=>'view_regions.php?filter=all_dynamic_regions'),
        'breadcrumb' => array(
            array('label' => lang('All Dynamic Regions'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_dynamic_regions'),
            array('label' => lang('Edit Dynamic Region')),
        ),
    ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                <form action="edit_dynamic_region.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '" />
                    ' . pg_common_region_form_layout(
                        pg_dynamic_region_form_cards($form_name, $form_code, array('name_error' => $name_error, 'status' => $output_status)),
                        '
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" formnovalidate class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('dynamic region')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>'
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
        // Delete skips the browser's form check, so the posted name may be
        // blank: the log names the stored one.
        $stored_name = (string) db_value("SELECT dregion_name FROM dregion WHERE dregion_id = '" . $id . "'");
        $query = "DELETE FROM dregion "
                ."WHERE dregion_id = ?";
        $result = db_q($query, array((string) $id)) or output_error('Query failed');
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was deleted','vars'=>array(lang('dynamic region'), $stored_name) )), $_SESSION['sessionusername']);
        $notice = lang(array('string'=>'{var:1} was deleted successfully','vars'=>lang('Dynamic Region') ));
    } else {
        list($name, $code) = pg_dynamic_region_form_values($_POST);

        // A blank or too long name, or a rename onto a name another region
        // already has (the site reads only one row per name), is refused. The
        // screen comes back with the posted values.
        $name_error = pg_region_form_name_refusal($name, pg_dregion_name_taken($name, $id));

        if ($name_error !== '') {
            $liveform->add_fields_to_session(array('trim' => false));
            $liveform->mark_error('name', $name_error);
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_dynamic_region.php?id=' . $id);
            exit();
        }
        // update region
        $query = "UPDATE dregion "
                ."SET dregion_name = ?, "
                    ."dregion_code = ?, "
                    ."dregion_user = ?, "
                    ."dregion_timestamp = UNIX_TIMESTAMP() "
                ."WHERE dregion_id = ?";
        $result = db_q($query, array(
            $name,
            $code,
            (int) $user['id'],
            (string) ($_POST['id'] ?? ''),
        )) or output_error('Query failed');
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was modified','vars'=>array(lang('dynamic region'), $name) )), $_SESSION['sessionusername']);
        $notice = lang(array('string'=>'{var:1} was edited successfully','vars'=>lang('Dynamic Region') ));
    }
    
    $liveform_view_styles = new liveform('view_regions');
    $liveform_view_styles->add_notice($notice);
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_dynamic_regions');
}
?>