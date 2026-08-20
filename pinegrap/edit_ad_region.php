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

// we allow users and managers to use this script, even though it is in the design tab, however they can't update the name
validate_area_access($user, 'user');

// if user has a user role and if they do not have access to this ad region, output error
if (($user['role'] == 3) && (in_array($_REQUEST['id'], get_items_user_can_edit('ad_regions', $user['id'])) == FALSE)) {
    // get ad region name
    $query = "SELECT name FROM ad_regions WHERE id = '" . escape($_REQUEST['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    log_activity(lang(array('string'=>'access denied because user does not have access to edit the ad region ({var:1})','vars'=>$row['name'])), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

include_once('liveform.class.php');
$liveform = new liveform('edit_ad_region');

// assume that there are no ads assigned to this ad region until we find out otherwise
$ads_assigned_to_this_ad_region = false;

// determine if there are ads assigned to this ad region
$query = "SELECT id FROM ads WHERE ad_region_id = '" . escape($_REQUEST['id']) . "' LIMIT 1";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

// if there is a result, then there are ads assigned to this ad region, so remember that
if (mysqli_num_rows($result) > 0) {
    $ads_assigned_to_this_ad_region = true;
}

// if the form has not just been submitted
if (!$_POST) {
    // get ad region data
    $query =
        "SELECT
            name,
            display_type,
            transition_type,
            transition_duration,
            slideshow,
            slideshow_interval,
            slideshow_continuous
        FROM ad_regions
        WHERE id = '" . escape($_GET['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    $name = $row['name'];
    
    // if the form has not been submitted yet, pre-populate fields with data
    if ($liveform->field_in_session('id') == false) {
        $display_type = $row['display_type'];
        $transition_type = $row['transition_type'];
        $transition_duration = $row['transition_duration'];
        $slideshow = $row['slideshow'];
        $slideshow_interval = $row['slideshow_interval'];
        $slideshow_continuous = $row['slideshow_continuous'];
        
        // if the transition duration is 0, then set it to empty string
        if ($transition_duration == 0) {
            $transition_duration = '';
        }
        
        // set field values
        $liveform->assign_field_value('name', $name);
        $liveform->assign_field_value('display_type', $display_type);
        $liveform->assign_field_value('transition_type', $transition_type);
        $liveform->assign_field_value('transition_duration', $transition_duration);
        $liveform->assign_field_value('slideshow', $slideshow);
        $liveform->assign_field_value('slideshow_interval', $slideshow_interval);
        $liveform->assign_field_value('slideshow_continuous', $slideshow_continuous);
    }
    
    $output_name = h($name);
    
    $output_subheading = '';
    
    // if the user is a designer or above, then prepare to display subheading and name field
    if ($user['role'] <= 1) {
        $output_subheading = '<p>' . lang('Page Style Body Tag') . ': <strong>' . h('<ad>' . $output_name . '</ad>') . '</strong></p>';
        
        $output_name_field_or_value = $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control add-header-content-updater', 'maxlength'=>'100'));

    // else the user is a manager, so prepare to just display name value,
    // because we don't want a manager to have access to update the name
    } else {
        $output_name_field_or_value = '<div class="input-group-text">' . $output_name . '</div>';
    }
    
  
    $output_delete_button = '';
    
    // if the user is a designer or above, then prepare to output delete button
    if ($user['role'] <= 1) {
        // if there are ads assigned to this ad region, then prepare inactive delete button with alert
        if ($ads_assigned_to_this_ad_region == true) {

            $output_delete_button = '<button type="button" name="submit_delete" value="Delete" class="btn my-1  btn-danger " onclick="alert(\'' . lang('Please delete or remove all ads from this ad region before deleting this ad region.') . '\');return false; " ><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>';

        // else there are no ads assigned to this ad region, so prepare active delete button with warning
        } else {
            $output_delete_button = '<button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('ad region')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>';

        }
    }
    
    print
    pg_page_shell([
        'title'=> lang(array('string'=>'Edit {var:1}','vars'=>lang('Ad Region'))),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang(array('string'=>'Edit {var:1}','vars'=>lang('Ad Region'))),
        'cancel'=>array('enable'=>'true','url'=>'view_regions.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Ad Regions'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_ad_regions'), array('label' => lang(array('string'=>'Edit {var:1}','vars'=>lang('Ad Region'))))),
        ]) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Update this ad region which displays rotating ad content. (A rename will require its tag to be updated in any page styles.)') . '" title="' . lang(array('string'=>'Edit {var:1}','vars'=>lang('Ad Region'))) . '">[' . $output_name . ']</h2>
                        ' . $output_subheading . '
                    </div>
                </div>
                <form name="form" action="edit_ad_region.php" method="post">
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
                                        <div class="col-12 col-md-6 my-2">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Ad Region'))) . '</label>
                                            <div class="input-group">
                                                <div class="input-group-text">' . h('<ad>') . '</div>
                                                ' . $output_name_field_or_value . '
                                                <div class="input-group-text">' . h('</ad>') . '</div>
                                            </div>
                                        </div>
                                        <h5 class="mt-5">' . lang('Region Behavior') . '</h5>
                                        <div class="col-12">
                                            <label class="form-label">' . lang('Display Type') . '</label>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-check">
                                                ' . $liveform->output_field(array('type'=>'radio', 'name'=>'display_type', 'id'=>'static', 'value'=>'static', 'class'=>'form-check-input collapse-switcher')) . '
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
                                                                ' . $liveform->output_field(array('type'=>'radio', 'name'=>'transition_type', 'id'=>'slide', 'value'=>'slide', 'class'=>'form-check-input')) . '
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
                                                                                ' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'slideshow_continuous', 'id'=>'slideshow_continuous', 'value'=>'1', 'class'=>'form-check-input')) . '
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
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-success" data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text">' . lang(array('string'=>'Save') ) . '</span></button>
                                ' . $output_delete_button . '
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
    
    // if the user selected to delete the ad region
    if ($liveform->get_field_value('submit_delete') == 'Delete') {
        // if the user is a manager who does not have access to delete the ad region,
        // then mark error and send user back to previous screen
        if ($user['role'] > 1) {
            $liveform->mark_error('', lang('You do not have access to delete ad regions.'));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_ad_region.php?id=' . $liveform->get_field_value('id') . '&send_to=' . urlencode($liveform->get_field_value('send_to')));
            exit();
        }
        
        // if there are ads assigned to this ad region,
        // then mark error and send user back to previous screen
        if ($ads_assigned_to_this_ad_region == true) {
            $liveform->mark_error('', lang('Please delete or remove all ads from this ad region before deleting this ad region.'));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_ad_region.php?id=' . $liveform->get_field_value('id') . '&send_to=' . urlencode($liveform->get_field_value('send_to')));
            exit();
        }        
        
        // delete ad region
        $query = "DELETE FROM ad_regions WHERE id = '" . escape($liveform->get_field_value('id')) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete users_ad_regions_xref records
        $query = "DELETE FROM users_ad_regions_xref WHERE ad_region_id = '" . escape($liveform->get_field_value('id')) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was deleted','vars'=>array(lang('ad region'), $liveform->get_field_value('name')) )), $_SESSION['sessionusername']);
        
        // if there is a send to, then forward user to send to
        if ($liveform->get_field_value('send_to') != '') {
            header('Location: ' . URL_SCHEME . HOSTNAME . $liveform->get_field_value('send_to'));
            $liveform->remove_form();
        // else there is not a send to, so prepare notice and send user to view ad regions screen
        } else {
            $liveform->remove_form();
            $liveform_view_ad_regions = new liveform('view_regions');
            $liveform_view_ad_regions->add_notice(lang(array('string'=>'{var:1} was deleted successfully','vars'=>lang('Ad Region') )));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_ad_regions');
        }
        
        
        
        exit();
        
    // else the user selected to save the ad region
    } else {
        $sql_name = "";
        $name = '';
        
        // if the user is a designer or above, then validate name field and prepare to update it
        if ($user['role'] <= 1) {
            $liveform->validate_required_field('name', lang('Name is required.'));
            
            // if there is not already an error for the name field, then check that valid characters were entered for name field
            if ($liveform->check_field_error('name') == false) {
                // Get previous name in order to determine if we should allow underscores or not.
                // We don't want to allow ad regions to be created or new ad region names to be set with underscores,
                // because the theme designer does not support underscores in names.  We are going to allow
                // underscores as long as it was set in the past in order to prevent their styles or themes
                // from being messed up.
                $query = "SELECT name FROM ad_regions WHERE id = '" . escape($liveform->get_field_value('id')) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $row = mysqli_fetch_assoc($result);
                $previous_name = $row['name'];
                
                // If the previous name contains underscores, then set regex code to allow underscores.
                if (mb_strpos($previous_name, '_') !== FALSE) {
                    $regex = '/[^A-Za-z0-9_-]/';
                    $message = lang('Please only enter letters, numbers, dashes, and underscores for the name.');

                // Otherwise the previous name does not contain underscores, so set regex code so it does not allow underscores.
                } else {
                    $regex = '/[^A-Za-z0-9-]/';
                    $message = lang('Please only enter letters, numbers, or dashes for the name.');
                }

                // If the name is not valid, then add error.
                if (preg_match($regex, $liveform->get_field_value('name')) == 1) {
                    $liveform->mark_error('name', $message);
                }
            }

            // if there is not already an error for the name field, check to see if name is already in use by a different ad region
            if ($liveform->check_field_error('name') == false) {
                $query =
                    "SELECT id
                    FROM ad_regions
                    WHERE
                        (name = '" . escape($liveform->get_field_value('name')) . "')
                        AND (id != '" . escape($liveform->get_field_value('id')) . "')";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                
                // if the name is already in use by a different ad region, then mark error
                if (mysqli_num_rows($result) > 0) {
                    $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
                }
            }
            
            // if there is an error, forward user back to edit ad region screen
            if ($liveform->check_form_errors() == true) {
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_ad_region.php?id=' . $liveform->get_field_value('id') . '&send_to=' . urlencode($liveform->get_field_value('send_to')));
                exit();
            }
            
            $sql_name = "name = '" . escape($liveform->get_field_value('name')) . "',";
            
            $name = $liveform->get_field_value('name');
            
        // else the user is a manager, so get ad region name for log message
        } else {
            $query = "SELECT name FROM ad_regions WHERE id = '" . escape($liveform->get_field_value('id')) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $name = $row['name'];
        }
        
        // update ad region
        $query =
            "UPDATE ad_regions
            SET
                $sql_name
                display_type = '" . escape($liveform->get_field_value('display_type')) . "',
                transition_type = '" . escape($liveform->get_field_value('transition_type')) . "',
                transition_duration = '" . escape($liveform->get_field_value('transition_duration')) . "',
                slideshow = '" . escape($liveform->get_field_value('slideshow')) . "',
                slideshow_interval = '" . escape($liveform->get_field_value('slideshow_interval')) . "',
                slideshow_continuous = '" . escape($liveform->get_field_value('slideshow_continuous')) . "',
                last_modified_user_id = '" . $user['id'] . "',
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . escape($liveform->get_field_value('id')) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was modified','vars'=>array(lang('ad region'), $name) )), $_SESSION['sessionusername']);
        
        // if there is a send to, then forward user to send to
        if ($liveform->get_field_value('send_to') != '') {
            header('Location: ' . URL_SCHEME . HOSTNAME . $liveform->get_field_value('send_to'));
            $liveform->remove_form();
        // else there is not a send to, so prepare notice and send user to view ad regions screen
        } else {
            $liveform->remove_form();
            $liveform_view_regions = new liveform('view_regions');
            $liveform_view_regions->add_notice(lang(array('string'=>'{var:1} was edited successfully','vars'=>lang('Ad Region') )));

            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_ad_regions');
        }
        
        
        
        exit();
    }
}
?>