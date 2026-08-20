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

// if user has a user role and if they do not have access to edit any ad regions, output error
if (($user['role'] == 3) && (count(get_items_user_can_edit('ad_regions', $user['id'])) == 0)) {
    log_activity(lang('access denied because user does not have access to ads'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

// if the user has a user role, then determine if user has access to the ad region that this ad is in
if ($user['role'] == 3) {
    // get this ad's name and ad region id
    $query = "SELECT name, ad_region_id FROM ads WHERE id = '" . escape($_REQUEST['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $name = $row['name'];
    $ad_region_id = $row['ad_region_id'];
    
    // if the user does not have access to the ad region that this ad is in, then log activity and output error
    if (in_array($ad_region_id, get_items_user_can_edit('ad_regions', $user['id'])) == FALSE) {
        log_activity(lang(array('string'=>'access denied because user does not have access to edit ad ({var:1})','vars'=>$name )), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
    }
}

include_once('liveform.class.php');
$liveform = new liveform('edit_ad');

// if the form has not just been submitted
if (!$_POST) {
    // get ad data
    $query =
        "SELECT 
            ads.name,
            ads.content,
            ads.caption,
            ads.ad_region_id,
            ads.label,
            ads.sort_order,
            ad_regions.name as ad_region_name
        FROM ads
        LEFT JOIN ad_regions ON ads.ad_region_id = ad_regions.id
        WHERE ads.id = '" . escape($_GET['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    $name = $row['name'];
    $ad_region_name = $row['ad_region_name'];
    
    // if the form has not been submitted yet, pre-populate fields with data
    if ($liveform->field_in_session('id') == false) {
        $content = $row['content'];
        $caption = $row['caption'];
        $ad_region_id = $row['ad_region_id'];
        $label = $row['label'];
        $sort_order = $row['sort_order'];
        
        // if the sort order is 0, then set it to empty string
        if ($sort_order == 0) {
            $sort_order = '';
        }
        
        // Assign values to fields
        $liveform->assign_field_value('name', $name);
        $liveform->assign_field_value('content', prepare_rich_text_editor_content_for_output($content));
        $liveform->assign_field_value('caption', prepare_rich_text_editor_content_for_output($caption));
        $liveform->assign_field_value('ad_region_id', $ad_region_id);
        $liveform->assign_field_value('label', $label);
        $liveform->assign_field_value('sort_order', $sort_order);
    }
    
    print
        pg_page_shell([
            'title'=> lang('Edit Ad'),
            'extra classes'=>'ads',
            'icon'=>'ads',
            'heading'=>lang('Edit Ad'),
            'cancel'=>array('enable'=>'true','url'=>'view_ads.php')
        ,
            'breadcrumb' => array(array('label' => lang('All My Ads'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_ads.php?filter=all_ad_regions'), array('label' => lang('Edit Ad'))),
        ]) . '
                    <div class="row">
                <div class="col-12">
                    ' . get_wysiwyg_editor_code(array('content_textarea', 'caption')) . '
                    ' . $liveform->output_errors() . '
                    ' . $liveform->output_notices() . '
                    <div class="row mb-2  flex-wrap">
                        <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Update this ad and assign it to any existing ad region.') . '" title="' . lang('Edit Ad') . '">' . h($ad_region_name) . '</h2>
                        </div>
                    </div>
                    <form name="form" action="edit_ad.php" method="post">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . h($_GET['id']) . '" />
                        <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '" />
                        <div class="row">
                            <div class="col-12">
                                <div class="card my-4">
                                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                        ' . lang('Options') . '
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                                <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Ad'))) . '</label>
                                                ' . $liveform->output_field(array('type'=>'text', 'id'=>'name', 'name'=>'name', 'size'=>'60', 'class'=>'form-control add-header-content-updater', 'maxlength'=>'100')) . '
                                                <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                            </div>
                                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                                <label for="ad_region_id" class="form-label">' . lang('Ad Region') . '</label>
                                                ' . $liveform->output_field(array('type'=>'select', 'name'=>'ad_region_id', 'id'=>'ad_region_id', 'class'=>'form-select', 'options'=>get_ad_region_options())) . '
                                                <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                                <div class="form-text">' . lang('Assign to Ad Region') . '</div>
                                            </div>
                                            <div class="col-12 mt-1 mb-2">
                                                <label for="content_textarea" class="form-label">' . lang('Ad Content') . '</label>
                                                ' . $liveform->output_field(array('type'=>'textarea', 'id'=>'content_textarea', 'name'=>'content')) . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="card my-4">
                                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                        ' . lang('Dynamic Ad Region Display Options') . '
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 col-md-4 my-2">
                                                <label for="label" class="form-label">' . lang('Label') . '</label>
                                                ' . $liveform->output_field(array('type'=>'text', 'id'=>'label', 'name'=>'label', 'size'=>'60', 'class'=>'form-control', 'maxlength'=>'255')) . '
                                            </div>
                                            <div class="col-12 col-md-4 my-2">
                                                <label for="sort_order" class="form-label">' . lang('Sort Order') . '</label>
                                                ' . $liveform->output_field(array('type'=>'text', 'id'=>'sort_order', 'name'=>'sort_order', 'size'=>'5', 'class'=>'form-control', 'maxlength'=>'4')) . '
                                                <div class="form-text">' . lang('leave blank for random order') . '</div>
                                            </div>
                                            <div class="col-12 mt-1 mb-2">
                                                <label for="caption" class="form-label">' . lang('Caption') . ' ( ' . lang('Add Caption on Top of Content') . ' )</label>
                                                ' . $liveform->output_field(array('type'=>'textarea', 'id'=>'caption', 'name'=>'caption')) . '
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
                                    <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('ad')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>
                                </div>
                            </div>
                        </nav>
                    </form>
                </div>
            </div>
        </main>' .
        output_footer();
    
    $liveform->remove_form();

// else the form has been submitted
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    // if the user selected to delete the ad
    if ($liveform->get_field_value('submit_delete') == 'Delete') {
        // delete ad
        $query = "DELETE FROM ads WHERE id = '" . escape($liveform->get_field_value('id')) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was deleted','vars'=>array(lang('ad'), $liveform->get_field_value('name') ) )), $_SESSION['sessionusername']);

        // if there is a send to, then forward user to send to
        if ($liveform->get_field_value('send_to') != '') {
            header('Location: ' . URL_SCHEME . HOSTNAME . $liveform->get_field_value('send_to'));
            
        // else there is not a send to, so prepare notice and send user to view ads screen
        } else {
            
            $liveform->remove_form();
            $liveform_view_ads = new liveform('view_ads');
            $liveform_view_ads->add_notice( lang(array('string'=>'{var:1} was deleted successfully','vars'=>lang('Ad') )) );
            
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_ads.php');
        }
        
        
        exit();
        
    // else the user selected to save the ad region
    } else {
    
        $liveform->validate_required_field('name', lang('Name is required.'));
        $liveform->validate_required_field('ad_region_id', lang('Ad Region is required.'));
        
        // if there is not already an error for the name field, check to see if name is already in use by a different ad
        if ($liveform->check_field_error('name') == false) {
            $query =
                "SELECT id
                FROM ads
                WHERE
                    (name = '" . escape($liveform->get_field_value('name')) . "')
                    AND (id != '" . escape($liveform->get_field_value('id')) . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // if the name is already in use by a different ad, then mark error
            if (mysqli_num_rows($result) > 0) {
                $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
            }
        }
        
        // if there is not already an error for the ad region field and the user has a user role and if they do not have access to the selected ad region, then do not allow the user to create the ad
        if (($liveform->check_field_error('ad_region_id') == false) && ($user['role'] == 3) && (in_array($liveform->get_field_value('ad_region_id'), get_items_user_can_edit('ad_regions', $user['id'])) == FALSE)) {
            $liveform->mark_error('ad_region_id', lang('You do not have access to the selected Ad Region, so please select a different Ad Region.'));
        }
        
        // if there is an error, forward user back to edit ad screen
        if ($liveform->check_form_errors() == true) {
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_ad.php?id=' . $liveform->get_field_value('id') . '&send_to=' . urlencode($liveform->get_field_value('send_to')));
            exit();
        }
        
        // update ad
        $query =
            "UPDATE ads
            SET
                name = '" . escape($liveform->get_field_value('name')) . "',
                content = '" . escape(prepare_rich_text_editor_content_for_input($liveform->get_field_value('content'))) . "',
                caption = '" . escape(prepare_rich_text_editor_content_for_input($liveform->get_field_value('caption'))) . "',
                ad_region_id = '" . escape($liveform->get_field_value('ad_region_id')) . "',
                label = '" . escape($liveform->get_field_value('label')) . "',
                sort_order = '" . escape($liveform->get_field_value('sort_order')) . "',
                last_modified_user_id = '" . $user['id'] . "',
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        log_activity(lang(array('string'=>'{var:1} ({var:2}) was modified','vars'=>array(lang('ad'), $liveform->get_field_value('name') ) )), $_SESSION['sessionusername']);
        // if there is a send to, then forward user to send to
        if ($liveform->get_field_value('send_to') != '') {
            header('Location: ' . URL_SCHEME . HOSTNAME . $liveform->get_field_value('send_to'));
            
        // else there is not a send to, so prepare notice and send user to view ads screen
        } else {
            
            $liveform->remove_form();
            $liveform_view_ads = new liveform('view_ads');
            $liveform_view_ads->add_notice(lang(array('string'=>'{var:1} was edited successfully','vars'=>lang('Ad') )));
            
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_ads.php');
        }
        
        
        exit();
    }
}
?>