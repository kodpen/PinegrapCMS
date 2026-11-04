<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The staff agenda of appointment bookings: a day, a week or a month's list
 * of bookings, narrowed to a staff member and to statuses, with a period
 * navigator. The day and week views draw a column per day; the list view is
 * paged and searched on the server (booking_list_rows,
 * includes/panel/lists.php). Roles 0-2 see every staff member; a role-3
 * staff member sees their own bookings (includes/fn/booking_agenda.php).
 *
 * Reading only: bookings are made and changed on edit_booking.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/booking_agenda.php');

$user = validate_user();

if (!pg_booking_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$bookings_scope = pg_booking_agenda_user_scope($user);

if ($bookings_scope === false) {
    log_activity('access denied to bookings', 'ACCESS DENIED');
    output_error(lang('Access denied'), 403);
}

$bookings_staff = pg_booking_agenda_staff();
$bookings_view = pg_booking_agenda_view($_GET['view'] ?? null);
$bookings_period = pg_booking_agenda_period($bookings_view, $_GET['date'] ?? null, time(), pg_list_period_week_first_day(), date_default_timezone_get());
$bookings_staff_id = pg_booking_agenda_staff_filter($_GET['staff'] ?? '', $bookings_scope, array_keys($bookings_staff));
$bookings_statuses = pg_booking_agenda_status_filter($_GET['status'] ?? null);

// The state every link of the screen carries. The chips are kept in the
// address only when they differ from the default choice.
$bookings_state = array(
    'view'   => $bookings_view,
    'date'   => ($bookings_period['date'] !== $bookings_period['today']) ? $bookings_period['date'] : '',
    'staff'  => ($bookings_scope === null) ? $bookings_staff_id : 0,
    'status' => ($bookings_statuses !== pg_booking_agenda_status_filter(null)) ? $bookings_statuses : array(),
);

$bookings_days = $bookings_period['days'];
$bookings_heading = pg_list_period_heading(pg_list_period_range_from_dates($bookings_days[0], $bookings_days[count($bookings_days) - 1]));

$bookings_step_labels = array(
    'day'  => array(lang('Previous day'), lang('Next day')),
    'week' => array(lang('Previous week'), lang('Next week')),
    'list' => array(lang('Previous month'), lang('Next month')),
);

$bookings_navigator = '
                    <div class="d-inline-flex align-items-center flex-wrap" role="group" aria-label="' . h(lang('Period')) . '">
                        <a class="btn btn-sm btn-text theme-secondary" href="' . h(pg_booking_agenda_link($bookings_state, array('date' => $bookings_period['previous']))) . '" title="' . h($bookings_step_labels[$bookings_view][0]) . '" aria-label="' . h($bookings_step_labels[$bookings_view][0]) . '"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
                        <a class="btn btn-sm btn-text theme-secondary" href="' . h(pg_booking_agenda_link($bookings_state, array('date' => ''))) . '">' . h(lang('Today')) . '</a>
                        <a class="btn btn-sm btn-text theme-secondary" href="' . h(pg_booking_agenda_link($bookings_state, array('date' => $bookings_period['next']))) . '" title="' . h($bookings_step_labels[$bookings_view][1]) . '" aria-label="' . h($bookings_step_labels[$bookings_view][1]) . '"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                        <span class="small fg-secondary ms-2">' . h($bookings_heading) . '</span>
                    </div>';

$bookings_views = '';

foreach (array('day' => array(lang('Day'), 'bi-calendar-day'), 'week' => array(lang('Week'), 'bi-calendar-week'), 'list' => array(lang('List'), 'bi-list-ul')) as $bookings_key => $bookings_label) {
    $bookings_active = ($bookings_key === $bookings_view);
    $bookings_views .= '<a class="btn btn-text theme-secondary' . ($bookings_active ? ' active' : '') . '"' . ($bookings_active ? ' aria-current="page"' : '') . ' href="' . h(pg_booking_agenda_link($bookings_state, array('view' => $bookings_key))) . '"><i class="bi ' . $bookings_label[1] . ' me-1" aria-hidden="true"></i>' . h($bookings_label[0]) . '</a>';
}

$bookings_new_query = array_filter(array('staff_id' => $bookings_staff_id, 'date' => ($bookings_view === 'day') ? $bookings_period['date'] : ''));

$bookings_toolbar = pg_screen_toolbar(array(
    'primary' => array('label' => lang('New booking'), 'url' => 'edit_booking.php' . ($bookings_new_query ? '?' . http_build_query($bookings_new_query) : ''), 'icon' => 'bi-plus-lg'),
    'grow'    => $bookings_navigator,
    'view'    => '<div class="btn-group btn-group-sm" role="group" aria-label="' . h(lang('View')) . '">' . $bookings_views . '</div>',
));

// The filters: the staff member (for those who see more than their own)
// and the status chips. A change sends the form at once; the button is for
// a browser without scripts.
$bookings_staff_select = '';

if ($bookings_scope === null) {
    $bookings_options = '<option value="">' . h(lang('All staff')) . '</option>';

    foreach ($bookings_staff as $bookings_member) {
        $bookings_options .= '<option value="' . (int) $bookings_member['id'] . '"' . (($bookings_member['id'] === $bookings_staff_id) ? ' selected' : '') . '>' . h($bookings_member['name']) . '</option>';
    }

    $bookings_staff_select = '
                        <div class="col-12 md:col-4 xl:col-3">
                            <label for="bookings_staff" class="form-label small mb-1">' . h(lang('Staff')) . '</label>
                            <select class="form-control form-control-sm" id="bookings_staff" name="staff">' . $bookings_options . '</select>
                        </div>';
}

$bookings_chips = '';

foreach (pg_booking_agenda_status_badges() as $bookings_status => $bookings_badge) {
    $bookings_chip_id = 'bookings_status_' . $bookings_status;
    $bookings_chips .= '<label class="btn btn-sm btn-outline theme-secondary btn-check rounded-pill me-2 mb-2" for="' . $bookings_chip_id . '"><input type="checkbox" name="status[]" value="' . h($bookings_status) . '" id="' . $bookings_chip_id . '"' . (in_array($bookings_status, $bookings_statuses, true) ? ' checked' : '') . '>' . h($bookings_badge[0]) . '</label>';
}

$bookings_filters = '
            <form action="view_bookings.php" method="get" id="bookings_filter" class="disable_shortcut card mb-7">
                <div class="card-body">
                    <input type="hidden" name="view" value="' . h($bookings_view) . '">
                    ' . (($bookings_state['date'] !== '') ? '<input type="hidden" name="date" value="' . h($bookings_state['date']) . '">' : '') . '
                    <div class="row g-3 align-items-end">
                        ' . $bookings_staff_select . '
                        <div class="col-12 md:col-8 xl:col-9">
                            <fieldset>
                                <legend class="form-label small mb-1">' . h(lang('Status')) . '</legend>
                                ' . $bookings_chips . '
                                <noscript><button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5 mb-2">' . h(lang('Filter')) . '</button></noscript>
                            </fieldset>
                        </div>
                    </div>
                </div>
            </form>
            <script>
                document.getElementById("bookings_filter").addEventListener("change", function () { this.submit(); });
            </script>';

if ($bookings_view === 'list') {

    $bookings_params = array('filters' => array(
        'start'  => $bookings_period['start'],
        'end'    => $bookings_period['end'],
        'staff'  => $bookings_staff_id,
        'status' => $bookings_statuses,
    ));

    $bookings_body = '
            <div class="card mb-12">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table align-top" style="width:100%;display:none" data-pg-list-action="booking_list_rows" data-pg-list-params="' . h(encode_json($bookings_params)) . '">
                        <thead>
                            <tr>
                                <th>' . h(lang('Time')) . '</th>
                                <th>' . h(lang('Status')) . '</th>
                                <th>' . h(lang('Customer')) . '</th>
                                <th>' . h(lang('Contact')) . '</th>
                                <th>' . h(lang('Service')) . '</th>
                                <th>' . h(lang('Staff')) . '</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>';

} else {

    $bookings_by_day = pg_booking_agenda_by_day(
        pg_booking_agenda_rows($bookings_period['start'], $bookings_period['end'], $bookings_staff_id, $bookings_statuses),
        $bookings_days,
        date_default_timezone_get());

    // Seven columns from xl up, one per day; below that the days stack, so
    // a phone reads the week top to bottom.
    $bookings_column = ($bookings_view === 'week') ? 'col-12 md:col-6 xl:col' : 'col-12';
    $bookings_body = '<div class="row g-3 mb-12' . (($bookings_view === 'week') ? ' pg-bookings-week' : '') . '">';

    foreach ($bookings_by_day as $bookings_day => $bookings_list) {
        $bookings_today = ($bookings_day === $bookings_period['today']);
        $bookings_items = '';

        foreach ($bookings_list as $bookings_row) {
            $bookings_who = isset($bookings_staff[(int) $bookings_row['staff_id']]) ? $bookings_staff[(int) $bookings_row['staff_id']]['name'] : '';

            $bookings_items .= '
                        <li class="list-group-item">
                            <a class="d-block pg-link-body-emphasis text-decoration-none" href="edit_booking.php?id=' . (int) $bookings_row['id'] . '">
                                <span class="d-flex justify-content-between align-items-start gap-2">
                                    <span class="fw-semibold text-nowrap">' . h(pg_booking_agenda_time($bookings_row['start_at'])) . ' – ' . h(pg_booking_agenda_time($bookings_row['end_at'])) . '</span>
                                    <span class="text-nowrap">' . pg_booking_agenda_badge($bookings_row['status']) . pg_booking_deposit_badge($bookings_row) . '</span>
                                </span>
                                <span class="d-block text-break">' . h($bookings_row['name']) . '</span>
                                <span class="d-block small fg-secondary text-break">' . h((string) $bookings_row['service_name']) . (($bookings_scope === null) && ($bookings_staff_id === 0) && ($bookings_who !== '') ? ' · ' . h($bookings_who) : '') . '</span>
                            </a>
                        </li>';
        }

        if ($bookings_items === '') {
            $bookings_items = '<li class="list-group-item small fg-secondary">' . h(lang('No bookings')) . '</li>';
        }

        $bookings_body .= '
                <section class="' . $bookings_column . '" aria-labelledby="bookings_day_' . h($bookings_day) . '">
                    <div class="card h-100' . ($bookings_today ? ' border-primary' : '') . '">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h2 class="h6 mb-0" id="bookings_day_' . h($bookings_day) . '"' . ($bookings_today ? ' aria-current="date"' : '') . '>' . h(pg_booking_agenda_weekday($bookings_day)) . ' <span class="fg-secondary fw-normal">' . h(prepare_form_data_for_output($bookings_day, 'date')) . '</span></h2>
                            <span class="badge badge-subtle theme-secondary" title="' . h(lang('Bookings')) . '">' . count($bookings_list) . '</span>
                        </div>
                        <ul class="list-group list-group-flush">' . $bookings_items . '</ul>
                    </div>
                </section>';
    }

    $bookings_body .= '</div>';

}

echo
pg_page_shell(array(
        'title'               => lang('Bookings'),
        'extra classes'       => 'calendar',
        'icon'                => 'calendar',
        'heading'             => lang('Bookings'),
        'heading_description' => lang(array('string' => 'The appointments booked with your staff, by day, week or as a list. Times are in the site\'s time zone ({var:1}).', 'vars' => array(h(date_default_timezone_get())))),
    )) . '
<main id="content" class="container-fluid">
    ' . $bookings_toolbar . '
    ' . $bookings_filters . '
    ' . $bookings_body . '
</main>
' .
output_footer();
