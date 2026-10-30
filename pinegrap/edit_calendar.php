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
validate_calendars_access($user);

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile): a "not found" screen instead
// of a half-empty form and PHP notices from the unchecked row below.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM calendars WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// if user does not have access to edit calendar, output error
if (validate_calendar_access($_REQUEST['id']) == false) {
    log_activity(lang('access denied to edit calendar'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/calendar_form.php');
$liveform = new liveform('edit_calendar', $_REQUEST['id']);

// get number of calendar events in this calendar
$query = "SELECT COUNT(calendar_event_id) FROM calendar_events_calendars_xref WHERE calendar_id = ?";
$result = db_q($query, array((string) $_REQUEST['id'])) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$number_of_calendar_events = $row[0];

if (!$_POST) {
    // get calendar data
    $query =
        "SELECT name
        FROM calendars
        WHERE id = ?";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

    // A name sent back by a refused save stays as it was typed.
    if ($liveform->prefill()) {
        $liveform->assign_field_value('name', $row['name']);
    }
    
    // if there are no calendar events in this calendar, allow delete
    if ($number_of_calendar_events == 0) {
        $output_delete_button = '<button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('calendar')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>';

    // else there is at least one calendar event in this calendar, so disable delete button
    } else {
        $output_delete_button = '<button type="button" value="Delete" class="btn my-1 btn-solid theme-danger "  data-confirm-content="' . lang('Please delete or remove all calendar events from this calendar before deleting this calendar.') . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>';
    }

    echo 
    
    pg_page_shell(
        array(
            'title'=> lang(array('string'=>'Edit Calendar Properties') ),
            'extra classes'=>'calendar',
            'icon'=>'calendar',
            'heading'=> lang(array('string'=>'Edit Calendar Properties') ),
            'heading_description' => lang('Edit the properties for this calendar.'),
            'cancel'=>array('enable'=>'true','url' => 'calendars.php?calendar_id=' . (int) $_GET['id']),
            'breadcrumb' => array(
                array('label' => lang('Calendars'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_calendars.php'),
                array('label' => lang('View Calendars'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/calendars.php?calendar_id=' . h($_GET['id'])),
                array('label' => lang(array('string'=>'Edit Calendar Properties'))),
            ),
        )
    ) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                
                <form name="form" action="edit_calendar.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '" />
                    ' . pg_calendar_form_card($liveform) . '
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

} else {
    validate_token_field();
    
    // if calendar was selected for delete
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // if there are no calendar events in this calendar, proceed with deleting calendar
        if ($number_of_calendar_events == 0) {
            // get calendar name for log
            $query = "SELECT name FROM calendars WHERE id = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $calendar_name = $row['name'];
            
            // delete calendar
            $query = "DELETE FROM calendars WHERE id = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
            
            // delete users_calendars_xref records
            $query = "DELETE FROM users_calendars_xref WHERE calendar_id = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
            
            // delete calendar_views_calendars_xref records
            $query = "DELETE FROM calendar_views_calendars_xref WHERE calendar_id = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
            
            // delete calendar_event_views_calendars_xref records
            $query = "DELETE FROM calendar_event_views_calendars_xref WHERE calendar_id = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
            
            // delete calendar_events_calendars_xref records
            $query = "DELETE FROM calendar_events_calendars_xref WHERE calendar_id = ?";
            $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
            log_activity(lang(array('string'=>'{var:1} ({var:2}) was deleted','vars'=>array(lang('calendar'), $calendar_name) )), $_SESSION['sessionusername']);

            $liveform->remove_form();
            $liveform_view_calendars = new liveform('view_calendars');
            $liveform_view_calendars->add_notice(lang(array('string'=>'{var:1} was deleted successfully','vars'=>lang('Calendar') )));
            
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_calendars.php');
            
            
        
        // else there is at least one calendar event in this calendar, so prepare error
        } else {
            $liveform->add_fields_to_session();
            
            $liveform->mark_error('', lang('Please delete or remove all calendar events from this calendar before deleting this calendar.'));
            
            // forward user to edit calendar screen
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_calendar.php?id=' . $_POST['id']);
        }
        
    // else calendar was not selected for delete
    } else {
        $liveform->add_fields_to_session();
        
        pg_calendar_form_validate_name($liveform, ($_POST['id'] ?? ''));
        
        // if there is an error, forward user back to edit calendar screen
        if ($liveform->check_form_errors() == true) {
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_calendar.php?id=' . $_POST['id']);
            exit();
        }
        
        // update calendar
        $query =
            "UPDATE calendars
            SET
                name = ?,
                last_modified_user_id = ?,
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = ?";
        $result = db_q($query, array((string) $liveform->get_field_value('name'), (string) $user['id'], (string) ($_POST['id'] ?? ''))) or output_error('Query failed.');

        log_activity(lang(array('string'=>'{var:1} ({var:2}) was modified','vars'=>array(lang('calendar'), $liveform->get_field_value('name') ) )), $_SESSION['sessionusername']);
        
        $liveform->remove_form();
        $liveform_calendars = new liveform('calendars');
        $liveform_calendars->add_notice(lang(array('string'=>'{var:1} was edited successfully','vars'=>lang('Calendar') )));
        
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/calendars.php?calendar_id=' . $_POST['id']);
        
        
    }
}