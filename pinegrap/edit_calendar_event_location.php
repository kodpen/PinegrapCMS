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

// if user does not have access to edit calendar event location, output error
if ($user['role'] == 3) {
    log_activity(lang('access denied to edit calendar event location.'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM calendar_event_locations WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/calendar_event_location_form.php');
$liveform = new liveform('edit_calendar_event_location', $_REQUEST['id']);

if (!$_POST) {
    // The stored record, with the number of events booking the location and
    // who saved it last, for the Summary.
    $query =
        "SELECT
            calendar_event_locations.name,
            calendar_event_locations.last_modified_timestamp,
            user.user_username,
            (SELECT COUNT(*) FROM calendar_events_calendar_event_locations_xref WHERE calendar_events_calendar_event_locations_xref.calendar_event_location_id = calendar_event_locations.id) AS number_of_events
        FROM calendar_event_locations
        LEFT JOIN user ON calendar_event_locations.last_modified_user_id = user.user_id
        WHERE calendar_event_locations.id = ?";
    $result = db_q($query, array($id)) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

    // Load the stored name only when no typed value waits in the session:
    // after a refused rename (blank or already in use) the screen shows the
    // name the user typed next to the error.
    if (!$liveform->field_in_session('name')) {
        $liveform->assign_field_value('name', $row['name']);
    }

    // Summary rows of the status column: the events held at the location,
    // then when and by whom it was last saved.
    $output_status =
        pg_calendar_event_location_form_status_row(lang('Events'), number_format((int) $row['number_of_events']), false)
        . pg_calendar_event_location_form_status_row(lang('Last Modified'), get_relative_time(array('timestamp' => (int) $row['last_modified_timestamp'])) . ((string) $row['user_username'] !== '' ? ' ' . h($row['user_username']) : ''));

    print 
    pg_page_shell(
        array(
            'title'=> lang(array('string'=>'Edit {var:1}','vars'=>lang('Event Location'))),
            'extra classes'=>'calendar',
            'icon'=>'calendar', 
            'heading'=> lang(array('string'=>'Edit {var:1}','vars'=>lang('Event Location'))),
            'heading_description' => lang('View or rename this common event location.'),
            'cancel'=>array('enable'=>'true','url'=>'view_calendar_event_locations.php'),
        
            'breadcrumb' => array(array('label' => lang('All Event Locations'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_calendar_event_locations.php'), array('label' => lang(array('string'=>'Edit {var:1}','vars'=>lang('Event Location'))))),
        )
    ) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                
                <form name="form" action="edit_calendar_event_location.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '" />
                    ' . pg_calendar_event_location_form_layout(
                        pg_calendar_event_location_form_cards($liveform, $output_status),
                        '
                                        <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                                        <button type="submit" name="submit_delete" value="Delete" formnovalidate class="btn my-1 btn-solid theme-danger" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('event location')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label">' . lang(array('string'=>'Delete') ) . '</span></button>'
                    ) . '
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
        $query = "SELECT name FROM calendar_event_locations WHERE id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $calendar_event_location_name = $row['name'];
        
        // delete calendar event location and the rows that book it for events
        if (!pg_calendar_location_delete($id)) {
            output_error('Query failed.');
        }

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
        
        // if name is already in use by a different calendar event location, prepare error and forward user back to screen
        if (pg_calendar_event_location_form_name_in_use($liveform->get_field_value('name'), (string) ($_POST['id'] ?? ''))) {
            $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
            
            // forward user to edit calendar event location screen
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_calendar_event_location.php?id=' . $_POST['id']);
            exit();
        }
        
        // update calendar event location
        if (pg_calendar_location_save($id, (string) $liveform->get_field_value('name'), (int) $user['id']) <= 0) {
            output_error('Query failed.');
        }

        log_activity(lang(array('string'=>'{var:1} ({var:2}) was modified','vars'=>array(lang('event location'), $liveform->get_field_value('name')) )), $_SESSION['sessionusername']);


        $liveform_view_calendar_event_locations = new liveform('view_calendar_event_locations');
        $liveform_view_calendar_event_locations->add_notice(lang(array('string'=>'{var:1} was edited successfully','vars'=>lang('Event Location') )));
        
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_calendar_event_locations.php');
        
        $liveform->remove_form();
    }
}
?>