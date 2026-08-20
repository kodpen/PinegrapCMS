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
$liveform = new liveform('add_ad_region');

// if the form has not been submitted
if (!$_POST) {
    print
    pg_page_shell([
        'title'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Ad Region'))),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang(array('string'=>'Create {var:1}','vars'=>lang('Ad Region'))),
        'cancel'=>array('enable'=>'true','url'=>'view_regions.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Ad Regions'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_ad_regions'), array('label' => lang(array('string'=>'Create {var:1}','vars'=>lang('Ad Region'))))),
        ]) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create an ad region to display rotating ad content.') . '" title="' . lang(array('string'=>'Create {var:1}','vars'=>lang('Ad Region'))) . '">[' . lang(array('string'=>'new {var:1} name','vars'=>lang('ad region'))) . ']</h2>
                    </div>
                </div>
                <form name="form" action="add_ad_region.php" method="post">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-6 my-2">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Ad Region'))) . '</label>
                                            <div class="input-group">
                                                <div class="input-group-text">' . h('<ad>') . '</div>
                                                <input name="name" id="name" type="text" class="form-control add-header-content-updater" maxlength="100" />
                                                <div class="input-group-text">' . h('</ad>') . '</div>
                                            </div>
                                        </div>
                                        <h5 class="mt-5">' . lang('Region Behavior') . '</h5>
                                        <div class="col-12">
                                            <label class="form-label">' . lang('Display Type') . '</label>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-check">
                                                ' . $liveform->output_field(array('type'=>'radio', 'name'=>'display_type', 'id'=>'static', 'value'=>'static', 'checked'=>'checked', 'class'=>'form-check-input collapse-switcher')) . '
                                                <label class="form-check-label" for="static">' . lang('Static') . ' (' . lang('i.e. display one ad per page view') . ')</label>
                                            </div>
                                            <div class="form-check">
                                                ' . $liveform->output_field(array('type'=>'radio', 'name'=>'display_type', 'id'=>'dynamic', 'value'=>'dynamic', 'class'=>'form-check-input collapse-switcher', 'data-bs-target'=>'#display_type_dynamic_row')) . '
                                                <label class="form-check-label" for="dynamic">' . lang('Dynamic') . ' (' . lang('i.e. display multiple ads per page view') . ')</label>
                                            </div>
                                            
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="display_type_dynamic_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 my-1">
                                                            <label class="form-label">' . lang('Transition Type') . '</label>
                                                        </div>
                                                        <div class="col-12 my-1">
                                                            <div class="form-check">
                                                                ' . $liveform->output_field(array('type'=>'radio', 'name'=>'transition_type', 'id'=>'slide', 'value'=>'slide', 'checked'=>'checked', 'class'=>'form-check-input')) . '
                                                                <label class="form-check-label" for="slide">' . lang('Slide') . '</label>
                                                            </div>
                                                            <div class="form-check">
                                                                ' . $liveform->output_field(array('type'=>'radio', 'name'=>'transition_type', 'id'=>'fade', 'value'=>'fade', 'class'=>'form-check-input')) . '
                                                                <label class="form-check-label" for="fade">' . lang('Fade') . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-4 my-1">
                                                            <label class="form-label" for="transition_duration">' . lang('Transition Duration') . '</label>
                                                            <div class="input-group">
                                                                ' . $liveform->output_field(array('type'=>'text', 'name'=>'transition_duration', 'id'=>'transition_duration', 'class'=>'form-control text-end', 'inputmode'=>'numeric', 'maxlength'=>'4', 'data-inputmask-alias'=>'decimal', 'data-inputmask-placeholder'=>'0')) . '
                                                                <div class="input-group-text">' . lang('milliseconds') . '</div>
                                                            </div>
                                                            <div class="form-text text-end">' . lang('leave blank for default, 1 for instant, 1000 for 1 second') . '</div>
                                                        </div>
                                                        <div class="col-12 my-1">
                                                            <div class="form-check form-switch">
                                                                ' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'slideshow', 'id'=>'slideshow', 'value'=>'1', 'class'=>'form-check-input collapse-switcher', 'data-bs-target'=>'#slideshow_row')) . '
                                                                <label class="form-check-label" for="slideshow">' . lang('Enable Autoplay') . '</label>
                                                            </div>
                                                            <div class="collapse popover  fade bs-popover-bottom p-0 mb-2 w-100" id="slideshow_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row">
                                                                        <div class="col-12 col-md-6 col-lg-4 my-1">
                                                                            <label class="form-label" for="slideshow_interval">' . lang('Interval') . '</label>
                                                                            <div class="input-group">
                                                                                ' . $liveform->output_field(array('type'=>'text', 'name'=>'slideshow_interval', 'id'=>'slideshow_interval', 'class'=>'form-control text-end', 'inputmode'=>'numeric', 'maxlength'=>'3', 'data-inputmask-alias'=>'decimal', 'data-inputmask-placeholder'=>'0')) . '
                                                                                <div class="input-group-text">' . lang('seconds') . '</div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                ' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'slideshow_continuous', 'id'=>'slideshow_continuous', 'value'=>'1', 'checked'=>'checked', 'class'=>'form-check-input')) . '
                                                                                <label class="form-check-label" for="slideshow_continuous">' . lang('Play Continuously') . '</label>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
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

// else the form has been submitted
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    $liveform->validate_required_field('name', lang('Name is required.'));
    
    // if there is not already an error for the name field, then check that valid characters were entered for name field
    if ($liveform->check_field_error('name') == false) {
        // if the name is not valid, then mark error
        if (preg_match('/[^A-Za-z0-9-]/', $liveform->get_field_value('name')) == 1) {
            $liveform->mark_error('name', lang('Please only enter letters, numbers, and dashes for the name.'));
        }
    }
    
    // if there is not already an error for the name field, check to see if name is already in use by a different ad region
    if ($liveform->check_field_error('name') == false) {
        $query =
            "SELECT id
            FROM ad_regions
            WHERE (name = '" . escape($liveform->get_field_value('name')) . "')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // if the name is already in use by a different ad region, then mark error
        if (mysqli_num_rows($result) > 0) {
            $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
        }
    }
    
    // if there is an error, forward user back to add ad region screen
    if ($liveform->check_form_errors() == true) {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_ad_region.php');
        exit();
    }
    
    // create ad region
    $query =
        "INSERT INTO ad_regions (
            name,
            display_type,
            transition_type,
            transition_duration,
            slideshow,
            slideshow_interval,
            slideshow_continuous,
            created_user_id,
            created_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES (
            '" . escape($liveform->get_field_value('name')) . "',
            '" . escape($liveform->get_field_value('display_type')) . "',
            '" . escape($liveform->get_field_value('transition_type')) . "',
            '" . escape($liveform->get_field_value('transition_duration')) . "',
            '" . escape($liveform->get_field_value('slideshow')) . "',
            '" . escape($liveform->get_field_value('slideshow_interval')) . "',
            '" . escape($liveform->get_field_value('slideshow_continuous')) . "',
            '" . $user['id'] . "',
            UNIX_TIMESTAMP(),
            '" . $user['id'] . "',
            UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    log_activity(lang(array('string'=>'{var:1} ({var:2}) was created','vars'=>array(lang('ad region'),$liveform->get_field_value('name')))), $_SESSION['sessionusername']);
    
    $liveform->remove_form();
    $liveform_view_regions = new liveform('view_regions');
    $liveform_view_regions->add_notice(lang('The ad region has been created.'));
    
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_ad_regions');
    
   
}
?>