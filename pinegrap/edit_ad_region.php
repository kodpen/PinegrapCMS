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

// we allow users and managers to use this script, even though it is in the design tab, however they can't update the name
validate_area_access($user, 'user');

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM ad_regions WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// if user has a user role and if they do not have access to this ad region, output error
if (($user['role'] == 3) && (in_array($_REQUEST['id'], get_items_user_can_edit('ad_regions', $user['id'])) == FALSE)) {
    // get ad region name
    $query = "SELECT name FROM ad_regions WHERE id = ?";
    $result = db_q($query, array((string) $_REQUEST['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    log_activity(lang(array('string'=>'access denied because user does not have access to edit the ad region ({var:1})','vars'=>$row['name'])), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/ad_region_form.php');
$liveform = new liveform('edit_ad_region');

// assume that there are no ads assigned to this ad region until we find out otherwise
$ads_assigned_to_this_ad_region = false;

// determine if there are ads assigned to this ad region
$query = "SELECT id FROM ads WHERE ad_region_id = ? LIMIT 1";
$result = db_q($query, array((string) $_REQUEST['id'])) or output_error('Query failed.');

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
        WHERE id = ?";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
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
        
        $output_name_field_or_value = $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>'100'));

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

            $output_delete_button = '<button type="button" name="submit_delete" value="Delete" class="btn my-1  btn-solid theme-danger " onclick="alert(\'' . lang('Please delete or remove all ads from this ad region before deleting this ad region.') . '\');return false; " ><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>';

        // else there are no ads assigned to this ad region, so prepare active delete button with warning
        } else {
            $output_delete_button = '<button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('ad region')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>';

        }
    }
    
    print
    pg_page_shell([
        'title'=> lang(array('string'=>'Edit {var:1}','vars'=>lang('Ad Region'))),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang(array('string'=>'Edit {var:1}','vars'=>lang('Ad Region'))),
        'heading_description' => lang('Update this ad region which displays rotating ad content. (A rename will require its tag to be updated in any page styles.)'),
        'cancel'=>array('enable'=>'true','url'=>pg_send_to_url('view_regions.php?filter=all_ad_regions'))
    ,
            'breadcrumb' => array(array('label' => lang('All Ad Regions'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_ad_regions'), array('label' => lang(array('string'=>'Edit {var:1}','vars'=>lang('Ad Region'))))),
        ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-3  flex-wrap">
                    <div class="col-12 sm:col-12 text-center md:text-start">

                        ' . $output_subheading . '
                    </div>
                </div>
                <form name="form" action="edit_ad_region.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '" />
                    <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '" />
                    ' . pg_ad_region_form_card($liveform, $output_name_field_or_value) . '

                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
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
        $query = "DELETE FROM ad_regions WHERE id = ?";
        $result = db_q($query, array((string) $liveform->get_field_value('id'))) or output_error('Query failed.');
        
        // delete users_ad_regions_xref records
        $query = "DELETE FROM users_ad_regions_xref WHERE ad_region_id = ?";
        $result = db_q($query, array((string) $liveform->get_field_value('id'))) or output_error('Query failed.');
        
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was deleted','vars'=>array(lang('ad region'), $liveform->get_field_value('name')) )), $_SESSION['sessionusername']);
        
        // if there is a send to, then forward user to send to
        if ($liveform->get_field_value('send_to') != '') {
            header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($liveform->get_field_value('send_to')));
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
        $name_values = array();
        $name = '';
        
        // if the user is a designer or above, then validate name field and prepare to update it
        if ($user['role'] <= 1) {
            pg_ad_region_form_validate_name($liveform, (string) $liveform->get_field_value('id'));
            
            // if there is an error, forward user back to edit ad region screen
            if ($liveform->check_form_errors() == true) {
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_ad_region.php?id=' . $liveform->get_field_value('id') . '&send_to=' . urlencode($liveform->get_field_value('send_to')));
                exit();
            }
            
            $sql_name = "name = ?,";
            $name_values = array((string) $liveform->get_field_value('name'));
            
            $name = $liveform->get_field_value('name');
            
        // else the user is a manager, so get ad region name for log message
        } else {
            $query = "SELECT name FROM ad_regions WHERE id = ?";
            $result = db_q($query, array((string) $liveform->get_field_value('id'))) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $name = $row['name'];
        }
        
        // update ad region
        $query =
            "UPDATE ad_regions
            SET
                $sql_name
                display_type = ?,
                transition_type = ?,
                transition_duration = ?,
                slideshow = ?,
                slideshow_interval = ?,
                slideshow_continuous = ?,
                last_modified_user_id = ?,
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = ?";
        $result = db_q($query, array_merge($name_values, pg_ad_region_form_values($liveform), array((string) $user['id'], (string) $liveform->get_field_value('id')))) or output_error('Query failed.');
        
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was modified','vars'=>array(lang('ad region'), $name) )), $_SESSION['sessionusername']);
        
        // if there is a send to, then forward user to send to
        if ($liveform->get_field_value('send_to') != '') {
            header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($liveform->get_field_value('send_to')));
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