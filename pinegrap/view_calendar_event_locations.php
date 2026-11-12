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

// Every location is printed: the list table driver sorts and searches them
// in the browser (browser mode) and keeps a heading's sort.
$list_rows = array();

foreach ($locations as $location) {
    $list_rows[] = array(
        'id' => (int) $location['id'],
        'cells' => array(
            'name' => pg_list_cell('<a href="edit_calendar_event_location.php?id=' . (int) $location['id'] . '" data-pg-open>' . h($location['name']) . '</a>'),
            'created' => pg_list_table_when_cell($location['created_timestamp'], (string) $location['created_username']),
            'last_modified' => pg_list_table_when_cell($location['last_modified_timestamp'], (string) $location['last_modified_username']),
        ),
    );
}

$list = pg_list_table_printed(array(
    'list' => 'view_calendar_event_locations',
    'columns' => array(
        'name' => array('heading' => lang('Name')),
        'created' => array('heading' => lang('Created'), 'kind' => 'date'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    ),
    'levels' => $sort_levels,
    'rows' => $list_rows,
));

print
pg_page_shell(
    array(
        'title'=> lang('Event Locations'),
        'extra classes'=>'calendar pg-measure-list',
        'icon'=>'calendar', 
        'heading'=> lang('Event Locations'),
        'heading_description' => lang('All event locations shared by all calendars.'),
        'breadcrumb' => array(
            array('label' => lang('Calendars'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_calendars.php'),
            array('label' => lang('Event Locations')),
        ),
    )
) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . pg_screen_toolbar(array(
                'primary' => array('label' => lang('Create'), 'url' => 'add_calendar_event_location.php', 'icon' => 'bi-plus-circle'),
                'search'  => array(pg_list_table_search_box('view_calendar_event_locations'), pg_list_sort_menu('view_calendar_event_locations.php', $sort_columns, $sort_levels, $sort_default)),
            )) . '
            ' . pg_list_table_html($list['source'], $list['page'], lang('Event Locations')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();

$liveform->remove_form();
