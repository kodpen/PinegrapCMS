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

$liveform = new liveform('view_calendars');

$output_add_calendar_button = '';

// if user is a manager or above.
if ($user['role'] < 3) {
   
    $output_add_calendar_button = '<a class="btn btn-sm btn-primary m-1 " href="add_calendar.php"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>';
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

$list_state = pg_list_state('view_calendars', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'calendars.name',
    'created'       => 'calendars.created_timestamp',
    'last_modified' => 'calendars.last_modified_timestamp',
));

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
    ORDER BY $order_by";

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
                    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " title="' . lang('Expand') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-chevron-right"></i></button>
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
        'heading_description' => lang('All calendars that I can add events too.'),
    )
) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
                    
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        ' . $output_add_calendar_button . '
                        <div class="pg-toolbar-grow"></div>
                        ' . pg_list_sort_menu('view_calendars.php', $sort_columns, $sort_levels, $sort_default) . '
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis text-start">' . lang(array('string'=>'Action') ) . '</th>
                                <th>' . get_column_heading(lang('Name'), $sort_levels, '', '', 'name') . '</th>
                                <th>' . get_column_heading(lang('Created'), $sort_levels, '', '', 'created') . '</th>
                                <th>' . get_column_heading(lang('Last Modified'), $sort_levels, '', '', 'last_modified') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();