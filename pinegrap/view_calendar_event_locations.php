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

include_once('liveform.class.php');
$liveform = new liveform('view_calendar_event_locations');

// if user does not have access to calendar event location, then output error
if ($user['role'] > 2) {
    log_activity(lang('access denied to view calendar event locations because user does not have access '), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'created'       => lang('Created'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_calendar_event_locations', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'calendar_event_locations.name',
    'created'       => 'calendar_event_locations.created_timestamp',
    'last_modified' => 'calendar_event_locations.last_modified_timestamp',
));

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
    ORDER BY $order_by";

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
                    <button type="button" class="m-1 btn-data-control btn btn-outline theme-primary border-2 " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                    <!--<button type="button" class="m-1 btn-data-control btn btn-outline theme-danger border-2 " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
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
        'heading_description' => lang('All event locations shared by all calendars.'),
    )
) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
           
            <div class="row mb-3  flex-wrap">
                <div class="col-12 text-center md:text-start">
                    
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-solid theme-primary m-1 " href="add_calendar_event_location.php"><span class="bi bi-plus-circle me-3"></span>' . lang(array('string'=>'Create') ) . '</a>
                        <div class="pg-toolbar-grow"></div>
                        ' . pg_list_sort_menu('view_calendar_event_locations.php', $sort_columns, $sort_levels, $sort_default) . '
                    </nav>
                </div>
            </div>
            <div class="card my-7">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table" style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                <th>' . get_column_heading(lang('Name'), $sort_levels, '', '', 'name') . '</th>
                                <th>' . get_column_heading(lang('Created'), $sort_levels, '', '', 'created') . '</th>
                                <th>' . get_column_heading(lang('Last Modified'), $sort_levels, '', '', 'last_modified') . '</th>
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
</main>
' .
output_footer();

$liveform->remove_form();
?>