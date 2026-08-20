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

include_once('liveform.class.php');
$liveform = new liveform('add_ad');

// if the form has not been submitted
if (!$_POST) {
    print
        pg_page_shell([
            'title'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Ad'))),
            'extra classes'=>'ads',
            'icon'=>'ads',
            'heading'=>lang(array('string'=>'Create {var:1}','vars'=>lang('Ad'))),
            'cancel'=>array('enable'=>'true','url'=>'view_ads.php')
        ,
            'breadcrumb' => array(array('label' => lang('All My Ads'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_ads.php?filter=all_ad_regions'), array('label' => lang(array('string'=>'Create {var:1}','vars'=>lang('Ad'))))),
        ]) . '
                    <div class="row">
                <div class="col-12">
                    ' . get_wysiwyg_editor_code(array('content_textarea', 'caption')) . '
                    ' . $liveform->output_errors() . '
                    ' . $liveform->output_notices() . '
                    <div class="row mb-2  flex-wrap">
                        <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a new ad and assign it to any existing ad region.') . '" title="' . lang(array('string'=>'Create {var:1}','vars'=>lang('Ad'))) . '">[' . lang(array('string'=>'new {var:1} name','vars'=>lang('Ad'))) . ']</h2>
                        </div>
                    </div>
                    <form name="form" action="add_ad.php" method="post">
                        ' . get_token_field() . '
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
                                    <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Creating') ) . '"><span class="bi bi-plus-circle me-2"></span><span class="btn-text">' . lang(array('string'=>'Create') ) . '</span></button>
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

    $liveform->add_fields_to_session();
    
    $liveform->validate_required_field('name', lang('Name is required.'));
    $liveform->validate_required_field('ad_region_id', lang('Ad Region is required.'));
    
    // if there is not already an error for the name field, check to see if name is already in use by a different ad
    if ($liveform->check_field_error('name') == false) {
        $query =
            "SELECT id
            FROM ads
            WHERE (name = '" . escape($liveform->get_field_value('name')) . "')";
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
    
    // if there is an error, forward user back to add ad screen
    if ($liveform->check_form_errors() == true) {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_ad.php');
        exit();
    }
    
    // create ad
    $query =
        "INSERT ads (
            name,
            content,
            caption,
            ad_region_id,
            label,
            sort_order,
            created_user_id, 
            created_timestamp,
            last_modified_user_id, 
            last_modified_timestamp)
        VALUES (
            '" . escape($liveform->get_field_value('name')) . "',
            '" . escape(prepare_rich_text_editor_content_for_input($liveform->get_field_value('content'))) . "',
            '" . escape(prepare_rich_text_editor_content_for_input($liveform->get_field_value('caption'))) . "',
            '" . escape($liveform->get_field_value('ad_region_id')) . "',
            '" . escape($liveform->get_field_value('label')) . "',
            '" . escape($liveform->get_field_value('sort_order')) . "',
            '" . $user['id'] . "',
            UNIX_TIMESTAMP(),
            '" . $user['id'] . "',
            UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    log_activity(lang(array('string'=>'ad ({var:1}) was created','vars'=>$liveform->get_field_value('name') )), $_SESSION['sessionusername']);



    $liveform->remove_form();
    $liveform_view_ads = new liveform('view_ads');
    $liveform_view_ads->add_notice(lang('The ad has been created.'));

    // forward user to view ads screen
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_ads.php');
}
?>