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

if (!isset($_POST['name'])) {
    $query = "SELECT dregion_name, dregion_code "
            ."FROM dregion "
            ."WHERE dregion_id = ?";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed');
    $row = mysqli_fetch_array($result);
    $dregion_name = $row['dregion_name'];
    $dregion_code = $row['dregion_code'];

    print 
    pg_page_shell([
        'title'=> lang(array('string'=>'Edit {var:1}','vars'=>lang('Dynamic Region'))),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang(array('string'=>'Edit {var:1}','vars'=>lang('Dynamic Region'))),
        'heading_description' => lang('Update this dynamic region of shared content. (A rename will require its tag to be updated in any page styles.)'),
        'cancel'=>array('enable'=>'true','url'=>'view_regions.php'),
        'breadcrumb' => array(
            array('label' => lang('All Dynamic Regions'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_dynamic_regions'),
            array('label' => lang(array('string'=>'Edit {var:1}','vars'=>lang('Dynamic Region')))),
        ),
    ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                <div class="row mb-3  flex-wrap">
                    <div class="col-12 sm:col-12 text-center md:text-start">
                        
                        <p>' . lang('Page Style Body Tag') . ': <strong>' . h('<dregion>' . $dregion_name . '</dregion>') . '</strong></p>
                    </div>
                </div>
                <form action="edit_dynamic_region.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '" />
                    ' . pg_dynamic_region_form_card($dregion_name, $dregion_code) . '
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('dynamic region')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' .
    output_footer();
} else {
    validate_token_field();
    
    include_once('liveform.class.php');
    
    // if region was selected for delete
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        $query = "DELETE FROM dregion "
                ."WHERE dregion_id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed');
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was deleted','vars'=>array(lang('dynamic region'), $_POST['name']) )), $_SESSION['sessionusername']);
        $notice = lang(array('string'=>'{var:1} was deleted successfully','vars'=>lang('Dynamic Region') ));
    } else {
        list($name, $code) = pg_dynamic_region_form_values($_POST);
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