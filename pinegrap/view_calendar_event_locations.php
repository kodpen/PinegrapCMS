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
validate_calendars_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_calendar_event_locations');

// if user does not have access to calendar event location, then output error
if ($user['role'] > 2) {
    log_activity(lang('access denied to view calendar event locations because user does not have access '), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value) == TRUE) {
        $_SESSION['software']['calendars']['view_calendar_event_locations'][$key] = trim($value);
    }
}


// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['calendars']['view_calendar_event_locations']['sort']) == false) {
    $_SESSION['software']['calendars']['view_calendar_event_locations']['sort'] = '';
}

switch (($_SESSION['software']['calendars']['view_calendar_event_locations']['sort'] ?? '')) {
    case 'Name':
        $sort_column = 'calendar_event_locations.name';
        break;

    case 'Created':
        $sort_column = 'calendar_event_locations.created_timestamp';
        break;

    case 'Last Modified':
        $sort_column = 'calendar_event_locations.last_modified_timestamp';
        break;

    default:
        $sort_column = 'calendar_event_locations.last_modified_timestamp';
        $_SESSION['software']['calendars']['view_calendar_event_locations']['sort'] = 'Last Modified';
        $_SESSION['software']['calendars']['view_calendar_event_locations']['order'] = 'desc';
        break;
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['calendars']['view_calendar_event_locations']['order']) == false) {
    $_SESSION['software']['calendars']['view_calendar_event_locations']['order'] = 'asc';
}

// get all calendar locations
$query =
    "SELECT
        calendar_event_locations.id,
        calendar_event_locations.name,
        calendar_event_locations.created_timestamp,
        calendar_event_locations.last_modified_timestamp,
        calendar_event_locations.last_modified_user_id,
        created_user.user_username as created_username,
        last_modified_user.user_username as last_modified_username
    FROM calendar_event_locations
    LEFT JOIN user as created_user ON calendar_event_locations.created_user_id = created_user.user_id
    LEFT JOIN user as last_modified_user ON calendar_event_locations.last_modified_user_id = last_modified_user.user_id
    ORDER BY $sort_column " . escape(($_SESSION['software']['calendars']['view_calendar_event_locations']['order'] ?? ''));

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$locations = array();

while ($row = mysqli_fetch_assoc($result)) {
    $locations[] = $row;
}

$output_rows = '';

// if there is at least one result to display
if ($locations) {

    foreach ($locations as $location) {
        if ($location['created_username']) {
            $created_username = lang(array('string'=>'by {var:1}','vars'=>array( h($location['created_username']) ) ) );
        } else {
            $created_username = '[' . lang('Unknown') . ']';
        }

        if ($location['last_modified_username']) {
            $last_modified_username = lang(array('string'=>'by {var:1}','vars'=>array( h($location['last_modified_username']) ) ) );
        } else {
            $last_modified_username = '[' . lang('Unknown') . ']';
        }

        $output_link_url = 'edit_calendar_event_location.php?id=' . $location['id'];

        $output_rows .=
            '<tr class="unselectable ">
                <td class="align-middle text-start action-buttons">
                    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                    <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
                </td>
                <td class="chart_label">' . h($location['name']) . '</td>
                <td>' . get_relative_time(array('timestamp' => $location['created_timestamp'])) . ' ' . $created_username . '</td>
                <td>' . get_relative_time(array('timestamp' => $location['last_modified_timestamp'])) . ' ' . $last_modified_username . '</td>
            </tr>';
    }
}


print
 
pg_page_shell(
    array(
        'title'=> lang('Event Locations'),
        'extra classes'=>'calendar',
        'icon'=>'calendar', 
        'heading'=> lang('Event Locations'),
    )
) . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
           
            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="' . lang('All event locations shared by all calendars.') . '" title="' . lang('All Event Locations') . '">' . lang('All Event Locations') . '</h2>
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1 " href="add_calendar_event_location.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table" style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                <th>' . get_column_heading(lang('Name'), ($_SESSION['software']['calendars']['view_calendar_event_locations']['sort'] ?? ''), ($_SESSION['software']['calendars']['view_calendar_event_locations']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Created'), ($_SESSION['software']['calendars']['view_calendar_event_locations']['sort'] ?? ''), ($_SESSION['software']['calendars']['view_calendar_event_locations']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['calendars']['view_calendar_event_locations']['sort'] ?? ''), ($_SESSION['software']['calendars']['view_calendar_event_locations']['order'] ?? '')) . '</th>
                            </tr>
                        </thead>
                        <tbody>
                            ' . $output_rows . '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>' .
output_footer();

$liveform->remove_form();
?>