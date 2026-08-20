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

$liveform = new liveform('view_calendars');

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value) == TRUE) {
        $_SESSION['software']['calendars']['view_calendars'][$key] = trim($value);
    }
}

$output_add_calendar_button = '';

// if user is a manager or above.
if ($user['role'] < 3) {
   
    $output_add_calendar_button = '<a class="btn btn-sm btn-primary m-1 " href="add_calendar.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>';
}

// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['calendars']['view_calendars']['sort']) == false) {
    $_SESSION['software']['calendars']['view_calendars']['sort'] = '';
}

switch (($_SESSION['software']['calendars']['view_calendars']['sort'] ?? '')) {
    case lang('Name'):
        $sort_column = 'calendars.name';
        break;

    case lang('Created'):
        $sort_column = 'calendars.created_timestamp';
        break;

    case lang('Last Modified'):
        $sort_column = 'calendars.last_modified_timestamp';
        break;

    default:
        $sort_column = 'calendars.last_modified_timestamp';
        $_SESSION['software']['calendars']['view_calendars']['sort'] = lang('Last Modified');
        $_SESSION['software']['calendars']['view_calendars']['order'] = 'desc';
        break;
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['calendars']['view_calendars']['order']) == false) {
    $_SESSION['software']['calendars']['view_calendars']['order'] = 'asc';
}

$my_calendars = 0;
$all_calendars = 0;

// get all calendars
$query =
    "SELECT
        calendars.id,
        calendars.name,
        created_user.user_username as created_username,
        calendars.created_timestamp,
        last_modified_user.user_username as last_modified_username,
        calendars.last_modified_timestamp
    FROM calendars
    LEFT JOIN user AS created_user ON calendars.created_user_id = created_user.user_id
    LEFT JOIN user AS last_modified_user ON calendars.last_modified_user_id = last_modified_user.user_id
    ORDER BY $sort_column " . escape(($_SESSION['software']['calendars']['view_calendars']['order'] ?? ''));

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$calendars = array();

while ($row = mysqli_fetch_assoc($result)) {
    // Add one to all calendars
    $all_calendars++;
    // if user has access to calendar then add calendar to calendars array
    if (validate_calendar_access($row['id']) == true) {
        $calendars[] = $row;
        // Add one to my calendars
        $my_calendars++;
    }
}

$output_rows = '';

// if there is at least one result to display
if ($calendars) {

    foreach ($calendars as $calendar) {

        $created_username = '';
        $last_modified_username = '';

        if ($calendar['created_username']) {
            $created_username = lang(array('string'=>'by {var:1}','vars'=>$calendar['created_username']));
        } 

        if ($calendar['last_modified_username']) {
            $last_modified_username = lang(array('string'=>'by {var:1}','vars'=>$calendar['last_modified_username']));
        } 

        $output_link_url = 'calendars.php?calendar_id=' . $calendar['id'];

        $output_rows .=
            '<tr>
                <td class="align-middle text-start action-buttons">
                    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Expand') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-chevron-right"></i></button>
                </td>
                <td class="align-middle chart_label">' . h($calendar['name']) . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $calendar['created_timestamp'])) . ' ' . $created_username . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $calendar['last_modified_timestamp'])) . ' ' . $last_modified_username . '</td>
            </tr>';
    }
}

echo

    
pg_page_shell(
    array(
        'title'=> lang('Calendars'),
        'extra classes'=>'calendar',
        'icon'=>'calendar', 
        'heading'=>lang('Calendars'),
    )
) . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="' . lang('All calendars that I can add events too.') . '" title="' . lang('Calendars') . '">' . lang('Calendars') . '</h2>
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        ' . $output_add_calendar_button . '
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis text-start">' . lang(array('string'=>'Action') ) . '</th>
                                <th>' . get_column_heading(lang('Name'), ($_SESSION['software']['calendars']['view_calendars']['sort'] ?? ''), ($_SESSION['software']['calendars']['view_calendars']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Created'), ($_SESSION['software']['calendars']['view_calendars']['sort'] ?? ''), ($_SESSION['software']['calendars']['view_calendars']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['calendars']['view_calendars']['sort'] ?? ''), ($_SESSION['software']['calendars']['view_calendars']['order'] ?? '')) . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>' .
output_footer();

$liveform->remove_form();