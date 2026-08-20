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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'designer');

include_once('liveform.class.php');
$liveform = new liveform('edit_designer_region');

if (!isset($_POST['name'])) {
    // Get designer region information.
    $query = "SELECT cregion_name, cregion_content, cregion_designer_type "
            ."FROM cregion "
            ."WHERE cregion_id = '" . escape($_GET['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed');
    $row = mysqli_fetch_array($result);
    $cregion_name = $row['cregion_name'];
    $cregion_content = $row['cregion_content'];
    
    print
    pg_page_shell([
        'title'=> lang(array('string'=>'Edit {var:1}','vars'=>lang('Designer Region'))),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang(array('string'=>'Edit {var:1}','vars'=>lang('Designer Region'))),
        'cancel'=>array('enable'=>'true','url'=>'view_regions.php'),
        'breadcrumb' => array(
            array('label' => lang('All Designer Regions'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_designer_regions'),
            array('label' => lang(array('string'=>'Edit {var:1}','vars'=>lang('Designer Region')))),
        ),
    ]) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
                        <h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Update this designer region of shared content. (A rename will require its tag to be updated in any page styles.)') . '" title="' . lang(array('string'=>'Edit {var:1}','vars'=>lang('Designer Region'))) . '">[' . h($cregion_name) . ']</h2>
                        <p>' . lang('Page Style Body Tag') . ': <strong>' . h('<cregion>' . $cregion_name . '</cregion>') . '</strong></p>
                        
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <div class=" btn-group btn-group-sm flex-wrap">
                                <a class="btn btn-link link-secondary py-0 mb-2 " data-loading-content="' . lang('Duplicating') . '" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/duplicate_designer_region.php?id=' . h(escape_javascript(urlencode($_REQUEST['id']))) . get_token_query_string_field() . '"><span class="material-icons me-1">control_point_duplicate</span>' . lang('Duplicate') . '</a>
                            </div>
                        </nav>
                    </div>
                </div>
                <form name="form" action="edit_designer_region.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '">
                    <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '" />
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-6 my-2">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Designer Region'))) . '</label>
                                            <div class="input-group">
                                                <div class="input-group-text">' . h('<cregion>') . '</div>
                                                <input value="' . h($cregion_name) . '" name="name" id="name" type="text" class="form-control add-header-content-updater" maxlength="100" />
                                                <div class="input-group-text">' . h('</cregion>') . '</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6 my-2">
                                            <label for="name" class="form-label">' . lang('Region Type') . '</label>
                                            <select name="type" id="type" class="form-select"><option value="common">' . lang('Common Region') . '</option><option value="designer" selected="selected">' . lang('Designer Region') . '</option></select>
                                            <div class="form-text text-end">' . lang('You can change Region Type') . '</div>
                                        </div>
                                        <h5 class="mt-5">' . lang('Shared Content to appear on associated Pages') . '</h5>
                                        <div class="col-12 my-2">
                                            <label for="name" class="form-label">' . lang('HTML Code Snippet') . '</label>
                                            <textarea name="content" id="code" rows="30" cols="60" wrap="off">' . h($cregion_content) . '</textarea>
                                            ' . get_codemirror_includes() . '
                                            ' . get_codemirror_javascript(array('id' => 'code', 'code_type' => 'mixed')) . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-success" data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text">' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('designer region')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>
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
                ."WHERE cregion_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed');
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was deleted','vars'=>array(lang('designer region'), $_POST['name']) )), $_SESSION['sessionusername']);


        // if there is not a send to, then prepare notice
        if ($_POST['send_to'] == '') {
            $notice = lang(array('string'=>'{var:1} was deleted successfully','vars'=>lang('Designer Region') ));
        }
        
    } else {
        $_POST['name'] = trim($_POST['name']);

        if ($_POST['type'] == 'common') {
            $cregion_designer_type = 'no';
        } else {
            $cregion_designer_type = 'yes';
        }
        
        $query = "UPDATE cregion "
                ."SET cregion_name = '" . escape($_POST['name'] ?? '') . "', "
                    ."cregion_content = '" . escape($_POST['content'] ?? '') . "', "
                    ."cregion_designer_type = '$cregion_designer_type', "
                    ."cregion_user = '" . $user['id'] . "', "
                    ."cregion_timestamp = UNIX_TIMESTAMP() "
                ."WHERE cregion_id = '" . escape($_POST['id'] ?? '') . "'";
        // update region
        $result = mysqli_query(db::$con, $query) or output_error('Query failed');
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was modified','vars'=>array(lang('designer region'), $_POST['name']) )), $_SESSION['sessionusername']);
        
        // if there is not a send to, then prepare notice
        if ($_POST['send_to'] == '') {
            $notice = lang(array('string'=>'{var:1} was edited successfully','vars'=>lang('Designer Region') ));
        }
    }
    
    // if there is a send to, then forward user to send to
    if ($_POST['send_to'] != '') {
        header('Location: ' . URL_SCHEME . HOSTNAME . $_POST['send_to']);
        
    // else there is not a send to, so prepare notice and send user to view ads screen
    } else {
        $liveform_view_styles = new liveform('view_regions');
        $liveform_view_styles->add_notice($notice);
        
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_' . $_POST['type'] . '_regions');
    }
}
?>