<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 20 - Calendars: upcoming calendar events.
 *
 * Loaded and called by includes/dashboard/widgets.php; see that file for the
 * contract every widget follows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_WIDGETS')) {
    exit;
}

function pg_dashboard_widget_20($request, $user)
{
    if (validate_calendars_access($user, $only_return = true) != false) {
        // get all calendars for calendar pick list
        $query =
            "SELECT
               id,
               name
            FROM calendars
            ORDER BY name";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $calendars = array();
        // loop through all calendars in order to prepare calendar pick list
        while ($row = mysqli_fetch_assoc($result)) {
            // if user has access to calendar, then include this calendar
            if (validate_calendar_access($row['id']) == true) {
                $calendars[] = $row;
            }
        }

        // No calendar this user may see. get_calendar() answers
        // that case with a bare sentence and no wrapper, which
        // arrived flush against the top left corner of the card --
        // the one card on the dashboard whose empty state was not
        // centred. Answering it here keeps get_calendar() alone,
        // since calendars.php prints that same sentence into a
        // full page where a widget-sized empty state would be
        // wrong.
        if (empty($calendars)) {

            $output_data = '
            <div class="card-body p-0 d-flex flex-column">
                ' . pg_widget_empty('bi-calendar3', lang('There are no calendars, so no calendar events could be displayed.')) . '
            </div>';

            $response = array(
                'status' => 'success',
                'message' => 'Action Success',
                'data' => $output_data,
            );
            return $response;
        }

        $output_data = '
        <div class="card-body p-0 overflow-auto">
            ' . get_calendar('', $calendars, '', '', $user, '', '', $number_of_upcoming_events = '', $return = 'html', $output_minimal_calendar = true) . '
            <a href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/calendars.php?date=&calendar_id=&view=monthly&status=" class=" stretched-link this-after-top-30"></a>
        </div>';

        //return success json output
        $response = array(
            'status' => 'success',
            'message' => 'Action Success',
            'data' => $output_data,
        );
        return $response;
    } else {
        $response = array(
            'status' => 'error',
            'message' => 'Access denied'
        );
        return $response;
    }
}
