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

// if user does not have access to edit calendar event location, output error
if ($user['role'] == 3) {
    log_activity(lang('access denied to edit calendar event location.'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

include_once('liveform.class.php');
$liveform = new liveform('edit_calendar_event_location', $_REQUEST['id']);

if (!$_POST) {
    // get calendar event location data
    $query =
        "SELECT name
        FROM calendar_event_locations
        WHERE id = '" . escape($_GET['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    $liveform->assign_field_value('name', $row['name']);



    print 
    pg_page_shell(
        array(
            'title'=> lang(array('string'=>'Edit {var:1}','vars'=>lang('Event Location'))),
            'extra classes'=>'calendar',
            'icon'=>'calendar', 
            'heading'=> lang(array('string'=>'Edit {var:1}','vars'=>lang('Event Location'))),
            'cancel'=>array('enable'=>'true','url'=>'view_calendar_event_locations.php'),
        
            'breadcrumb' => array(array('label' => lang('All Event Locations'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_calendar_event_locations.php'), array('label' => lang(array('string'=>'Edit {var:1}','vars'=>lang('Event Location'))))),
        )
    ) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('View or rename this common event location.') . '" title="' . lang(array('string'=>'Edit {var:1}','vars'=>lang('Event Location'))) . '">[' . h($liveform->get_field_value('name')) . ']</h2>
                    </div>
                </div>
                <form name="form" action="edit_calendar_event_location.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '" />
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-6 my-2">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Event Location'))) . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control add-header-content-updater', 'maxlength'=>'100')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
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
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('event location')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>
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
    
    // if calendar event location was selected for delete
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // get calendar event location name for log
        $query = "SELECT name FROM calendar_event_locations WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $calendar_event_location_name = $row['name'];
        
        // delete calendar event location
        $query = "DELETE FROM calendar_event_locations WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was deleted','vars'=>array(lang('event location'), $calendar_event_location_name) )), $_SESSION['sessionusername']);
        $liveform_view_calendar_event_locations = new liveform('view_calendar_event_locations');
        $liveform_view_calendar_event_locations->add_notice(lang(array('string'=>'{var:1} was deleted successfully','vars'=>lang('Event Location') )));
        
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_calendar_event_locations.php');
        
        $liveform->remove_form();
        
    // else calendar event location was not selected for delete
    } else {
        $liveform->add_fields_to_session();
        
        $liveform->validate_required_field('name', lang('Name is required.'));
        
        // if there is an error, forward user back to edit calendar event location screen
        if ($liveform->check_form_errors() == true) {
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_calendar_event_location.php?id=' . $_POST['id']);
            exit();
        }
        
        // check to see if name is already in use by a different calendar location
        $query =
            "SELECT id
            FROM calendar_event_locations
            WHERE
                (name = '" . escape($liveform->get_field_value('name')) . "')
                AND (id != '" . escape($_POST['id'] ?? '') . "')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // if name is already in use by a different calendar event location, prepare error and forward user back to screen
        if (mysqli_num_rows($result) > 0) {
            $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
            
            // forward user to edit calendar event location screen
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_calendar_event_location.php?id=' . $_POST['id']);
            exit();
        }
        
        // update calendar event location
        $query =
            "UPDATE calendar_event_locations
            SET
                name = '" . escape($liveform->get_field_value('name')) . "',
                last_modified_user_id = '" . $user['id'] . "',
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

      
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was modified','vars'=>array(lang('event location'), $liveform->get_field_value('name')) )), $_SESSION['sessionusername']);


        $liveform_view_calendar_event_locations = new liveform('view_calendar_event_locations');
        $liveform_view_calendar_event_locations->add_notice(lang(array('string'=>'{var:1} was edited successfully','vars'=>lang('Event Location') )));
        
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_calendar_event_locations.php');
        
        $liveform->remove_form();
    }
}
?>