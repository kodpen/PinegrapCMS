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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

$user = validate_user();
validate_calendars_access($user);

$liveform = new liveform('view_calendars');

$output_add_calendar_button = '';

// if user is a manager or above.
if ($user['role'] < 3) {
   
    $output_add_calendar_button = '<a class="btn btn-sm btn-solid theme-primary m-1 " href="add_calendar.php"><span class="bi bi-plus-circle me-3"></span>' . lang(array('string'=>'Create') ) . '</a>';
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

// Every calendar the user may use is printed: the list table driver sorts
// and searches them in the browser (browser mode) and keeps a heading's sort.
$list_rows = array();

foreach ($calendars as $calendar) {
    $list_rows[] = array(
        'id' => (int) $calendar['id'],
        'cells' => array(
            // The name opens the calendar's events, as the Expand button did.
            'name' => pg_list_cell('<a href="calendars.php?calendar_id=' . (int) $calendar['id'] . '" data-pg-open>' . h($calendar['name']) . '</a>'),
            'created' => pg_list_table_when_cell($calendar['created_timestamp'], (string) $calendar['created_username']),
            'last_modified' => pg_list_table_when_cell($calendar['last_modified_timestamp'], (string) $calendar['last_modified_username']),
        ),
    );
}

$list = pg_list_table_printed(array(
    'list' => 'view_calendars',
    'columns' => array(
        'name' => array('heading' => lang('Name')),
        'created' => array('heading' => lang('Created'), 'kind' => 'date'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    ),
    'levels' => $sort_levels,
    'rows' => $list_rows,
));

echo
pg_page_shell(
    array(
        'title'=> lang('Calendars'),
        'extra classes'=>'calendar pg-measure-list',
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
            ' . pg_screen_toolbar(array(
                'primary' => $output_add_calendar_button,
                'search'  => array(pg_list_table_search_box('view_calendars'), pg_list_sort_menu('view_calendars.php', $sort_columns, $sort_levels, $sort_default)),
            )) . '
            ' . pg_list_table_html($list['source'], $list['page'], lang('Calendars')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();

$liveform->remove_form();
