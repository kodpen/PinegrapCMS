<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Settings > Bookings: what the category of the settings dialog reads and
 * writes for appointment bookings (includes/fn/bookings.php holds the model).
 *
 * Services and staff are rows of booking_services and booking_staff; the
 * policies (lead time, horizon, cancellation window, reschedule, hold
 * minutes, reminder hours, confirmation, slot step) and the booking widget's
 * texts are two JSON rows of config_text, booking_policy and booking_texts,
 * since the config row takes no new column. Every reader goes through
 * pg_booking_policy() and pg_booking_texts(), which clean what is stored, so
 * a hand-edited row or one written by an older build still reads as bounded
 * values.
 *
 * The weekly hours are a grid of two ranges a day; the dated exceptions are
 * typed as a range of days with one range of hours (none: the days off) and
 * stored day by day in the shape pg_booking_exceptions_clean() keeps.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * A posted or stored number as an int inside its bounds; anything unreadable
 * is the lower bound.
 *
 * @param mixed $value
 * @param int   $min
 * @param int   $max
 * @return int
 */
function pg_booking_settings_int($value, $min, $max)
{
    if (is_bool($value)) {
        $value = $value ? 1 : 0;
    }

    if (!is_scalar($value) || (preg_match('/^\s*-?\d+\s*$/', (string) $value) !== 1)) {
        return $min;
    }

    return max($min, min($max, (int) $value));
}

/**
 * The policies cleaned, every missing key at its default.
 *
 *   lead_minutes    how soon a slot may start, from now (0-43200)
 *   horizon_days    how far ahead slots are offered (1-730)
 *   cancel_hours    until how long before the start the visitor may cancel (0-720)
 *   reschedule      whether the visitor may move the booking through the manage link
 *   hold_minutes    how long a slot is held while the details are typed (2-60)
 *   reminder_hours  hours before the start a reminder goes, latest first, three at most (1-168)
 *   auto_confirm    a visitor's booking is confirmed at once; off, it waits as requested
 *   step_minutes    the distance between slot starts, 0 for the service's length (0, 5-240)
 *
 * @param mixed $input
 * @return array
 */
function pg_booking_settings_policy_clean($input)
{
    $input = is_array($input) ? $input : array();

    $value = function ($key, $default) use ($input) {
        return array_key_exists($key, $input) ? $input[$key] : $default;
    };

    $reminders = $value('reminder_hours', array(24, 2));

    if (is_scalar($reminders)) {
        $reminders = preg_split('/[\s,;]+/', (string) $reminders, -1, PREG_SPLIT_NO_EMPTY);
    }

    $hours = array();

    foreach ((array) $reminders as $reminder) {
        if (is_scalar($reminder) && (preg_match('/^\d+$/', trim((string) $reminder)) === 1)) {
            $reminder = (int) $reminder;
            if (($reminder >= 1) && ($reminder <= 168)) {
                $hours[$reminder] = $reminder;
            }
        }
    }

    // The nearest reminders are the ones a visitor needs; latest first.
    sort($hours);
    $hours = array_reverse(array_slice(array_values($hours), 0, 3));

    $step = pg_booking_settings_int($value('step_minutes', 0), 0, 240);

    if ($step > 0) {
        $step = max(5, (int) (round($step / 5) * 5));
    }

    return array(
        'lead_minutes'   => pg_booking_settings_int($value('lead_minutes', 120), 0, 43200),
        'horizon_days'   => pg_booking_settings_int($value('horizon_days', 60), 1, 730),
        'cancel_hours'   => pg_booking_settings_int($value('cancel_hours', 24), 0, 720),
        'reschedule'     => (bool) pg_booking_settings_int($value('reschedule', 1), 0, 1),
        'hold_minutes'   => pg_booking_settings_int($value('hold_minutes', 10), 2, 60),
        'reminder_hours' => $hours,
        'auto_confirm'   => (bool) pg_booking_settings_int($value('auto_confirm', 1), 0, 1),
        'step_minutes'   => $step,
    );
}

/**
 * The policies from their stored JSON text.
 *
 * @param string $json
 * @return array as pg_booking_settings_policy_clean()
 */
function pg_booking_settings_policy_read($json)
{
    $data = json_decode((string) $json, true);

    return pg_booking_settings_policy_clean(is_array($data) ? $data : array());
}

/**
 * The site's booking policies (config_text booking_policy).
 *
 * @return array as pg_booking_settings_policy_clean()
 */
function pg_booking_policy()
{
    return pg_booking_settings_policy_read(function_exists('pg_config_text') ? pg_config_text('booking_policy') : '');
}

/**
 * The booking widget's own texts: key => array(label, longest length). An
 * empty one is the widget's default wording.
 *
 * @return array
 */
function pg_booking_settings_text_fields()
{
    return array(
        'title' => array(lang('Heading'), 100),
        'intro' => array(lang('Introduction'), 500),
        'done'  => array(lang('Message after booking'), 500),
        'taken' => array(lang('Message when the time was just taken'), 300),
    );
}

/**
 * The widget's texts cleaned: known keys only, plain text, trimmed and
 * bounded; empty ones are left out.
 *
 * @param mixed $input
 * @return array key => text
 */
function pg_booking_settings_texts_clean($input)
{
    $input = is_array($input) ? $input : array();
    $clean = array();

    foreach (pg_booking_settings_text_fields() as $key => $field) {

        if (!isset($input[$key]) || !is_scalar($input[$key])) {
            continue;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $input[$key])));
        $text = mb_substr($text, 0, $field[1], 'UTF-8');

        if ($text !== '') {
            $clean[$key] = $text;
        }
    }

    return $clean;
}

/**
 * The widget's texts (config_text booking_texts).
 *
 * @return array key => text, as pg_booking_settings_texts_clean()
 */
function pg_booking_texts()
{
    $data = json_decode(function_exists('pg_config_text') ? pg_config_text('booking_texts') : '', true);

    return pg_booking_settings_texts_clean(is_array($data) ? $data : array());
}

/**
 * A typed price in minor units. The last separator is the decimal point when
 * one or two digits follow it, every other one groups thousands, so "12,50"
 * and "12.50" are both twelve fifty and "1.234" is one thousand two hundred
 * thirty-four. Null when the amount is above what the column holds.
 *
 * @param mixed $value
 * @return int|null
 */
function pg_booking_settings_price_cents($value)
{
    $value = (string) preg_replace('/[^0-9.,]/', '', is_scalar($value) ? (string) $value : '');

    if (preg_match('/[0-9]/', $value) !== 1) {
        return 0;
    }

    if (strlen((string) preg_replace('/[^0-9]/', '', $value)) > 12) {
        return null;
    }

    if (preg_match('/^(.*)([.,])([0-9]{1,2})$/', $value, $match) === 1) {
        $whole = (string) preg_replace('/[.,]/', '', $match[1]);
        $figure = (($whole === '') ? '0' : $whole) . '.' . $match[3];
    } else {
        $figure = (string) preg_replace('/[.,]/', '', $value);
    }

    // round(), never a cast: 0.29 * 100 is 28.999... as a float.
    $cents = round(((float) $figure) * 100);

    return ($cents > 2147483647) ? null : (int) $cents;
}

/**
 * Minor units as the price box shows them.
 *
 * @param int   $cents
 * @param array $separators decimal, thousands (pg_number_separators())
 * @return string
 */
function pg_booking_settings_price_text($cents, $separators)
{
    return number_format(((int) $cents) / 100, 2, (string) $separators['decimal'], (string) $separators['thousands']);
}

/**
 * The products a service's deposit may name, as the deposit picker lists
 * them: id => name, the first 2000 by name (the designed form's fee product
 * list, list_fee_products).
 *
 * @return array
 */
function pg_booking_settings_deposit_products()
{
    $products = array();

    foreach ((array) db_items("SELECT id, name FROM products ORDER BY name ASC, id ASC LIMIT 2000") as $row) {
        $products[(int) $row['id']] = (string) $row['name'];
    }

    return $products;
}

/**
 * Whether a posted new-service row was left empty.
 *
 * @param array $row
 * @return bool
 */
function pg_booking_settings_service_blank($row)
{
    return !isset($row['name']) || !is_scalar($row['name']) || (trim((string) $row['name']) === '');
}

/**
 * One posted service row as columns of booking_services, with the first box
 * that is wrong ('' when none): a name and a length of 5 minutes to a day are
 * needed, the buffers are at most eight hours. A row that carries the deposit
 * boxes (drawn once the deposit columns exist and the store is on) also
 * gives deposit and deposit_product_id (pg_booking_deposit_clean()); a row
 * without them leaves the stored deposit as it is.
 *
 * @param array $row name, description, length, before, after, price, enabled,
 *                   order, and deposit, deposit_product when drawn
 * @return array values => columns, error => box key or ''
 */
function pg_booking_settings_service_clean($row)
{
    $row = is_array($row) ? $row : array();

    $text = function ($key, $length) use ($row) {
        $value = (isset($row[$key]) && is_scalar($row[$key])) ? (string) $row[$key] : '';
        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', strip_tags($value))), 0, $length, 'UTF-8');
    };

    $length = isset($row['length']) ? $row['length'] : '';
    $price = pg_booking_settings_price_cents(isset($row['price']) ? $row['price'] : '');

    $values = array(
        'name'           => $text('name', 100),
        'description'    => $text('description', 255),
        'length_minutes' => pg_booking_settings_int($length, 0, 1440),
        'buffer_before'  => pg_booking_settings_int(isset($row['before']) ? $row['before'] : 0, 0, 480),
        'buffer_after'   => pg_booking_settings_int(isset($row['after']) ? $row['after'] : 0, 0, 480),
        'price'          => ($price === null) ? 0 : $price,
        'enabled'        => !empty($row['enabled']) ? 1 : 0,
        'sort_order'     => pg_booking_settings_int(isset($row['order']) ? $row['order'] : 0, 0, 65535),
    );

    $error = '';

    if ($values['name'] === '') {
        $error = 'name';
    } elseif ((pg_booking_settings_int($length, 0, 100000) < 5) || (pg_booking_settings_int($length, 0, 100000) > 1440)) {
        $error = 'length';
    } elseif ($price === null) {
        $error = 'price';
    }

    if (array_key_exists('deposit', $row)) {
        $deposit = pg_booking_deposit_clean(
            pg_booking_settings_price_cents($row['deposit']),
            isset($row['deposit_product']) ? $row['deposit_product'] : 0,
            $values['price']);

        $values += $deposit['values'];

        if ($error === '') {
            $error = $deposit['error'];
        }
    }

    return array('values' => $values, 'error' => $error);
}

/**
 * The id of a service's drawer in the Bookings pane: the service's id, or
 * 'new' for the add drawer.
 *
 * @param int|string $key
 * @return string
 */
function pg_booking_settings_service_drawer($key)
{
    return 'pgset-booking-service-' . $key;
}

/**
 * The drawer holding a box a save refused, so the pane opens it again: a
 * service's boxes sit in its drawer. '' for a box drawn in the pane itself
 * (the order, every staff box) or a row that is no service. Pure.
 *
 * @param string     $list  the posted list (booking_service, booking_staff)
 * @param int|string $row   the row's key: a service id or 'new'
 * @param string     $error the box's key, as pg_booking_settings_service_clean() names it
 * @return string drawer id or ''
 */
function pg_booking_settings_error_drawer($list, $row, $error)
{
    $boxes = array('name', 'length', 'before', 'after', 'price', 'deposit', 'deposit_product', 'description', 'enabled');

    if (($list !== 'booking_service') || !is_scalar($row) || !in_array((string) $error, $boxes, true)) {
        return '';
    }

    $row = (string) $row;

    return (($row === 'new') || (preg_match('/^[1-9]\d*$/', $row) === 1)) ? pg_booking_settings_service_drawer($row) : '';
}

/**
 * A service's line in its row: its length, its price when it has one, and
 * whether it can be booked; a service switched off says only that.
 *
 * @param array  $service    a booking_services row
 * @param array  $separators decimal, thousands (pg_number_separators())
 * @param string $currency
 * @return string
 */
function pg_booking_settings_service_line($service, $separators, $currency)
{
    if (empty($service['enabled'])) {
        return lang('Switched off');
    }

    $parts = array(lang(array('string' => '{var:1} min', 'vars' => (int) ($service['length_minutes'] ?? 0))));

    if ((int) ($service['price'] ?? 0) > 0) {
        $parts[] = trim(pg_booking_settings_price_text((int) $service['price'], $separators) . ' ' . $currency);
    }

    $parts[] = lang('Can be booked');

    return implode(' · ', $parts);
}

/**
 * The line of state of each folded row of the Bookings pane.
 *
 * @param array $services id => booking_services row
 * @param array $staff    user id => booking_staff row
 * @param array $policy   as pg_booking_policy()
 * @param array $texts    as pg_booking_texts()
 * @return array services, staff, policy, texts => line
 */
function pg_booking_settings_fold_lines($services, $staff, $policy, $texts)
{
    $count = function ($rows) {
        return count(array_filter((array) $rows, function ($row) {
            return !empty($row['enabled']);
        }));
    };

    $services = (array) $services;
    $bookable = $count($services);
    $taking = $count($staff);
    $days = (int) ($policy['horizon_days'] ?? 0);

    if (!$services) {
        $services_line = lang('No service yet');
    } else {
        $services_line = lang(array(
            'string' => '{var:1} service{suffix:1}, {var:2} can be booked',
            'vars'   => array(count($services), $bookable),
            'suffix' => array((count($services) === 1) ? '' : 's'),
        ));
    }

    if ($taking === 0) {
        $staff_line = lang('Nobody takes bookings yet');
    } elseif ($taking === 1) {
        $staff_line = lang('1 takes bookings');
    } else {
        $staff_line = lang(array('string' => '{var:1} take bookings', 'vars' => $taking));
    }

    $filled = count(array_filter(array_intersect_key((array) $texts, pg_booking_settings_text_fields()), function ($text) {
        return trim((string) $text) !== '';
    }));

    return array(
        'services' => $services_line,
        'staff'    => $staff_line,
        'policy'   => lang(array('string' => 'Up to {var:1} day{suffix:1} ahead', 'vars' => $days, 'suffix' => array(($days === 1) ? '' : 's')))
            . ' · ' . (!empty($policy['auto_confirm']) ? lang('confirmed at once') : lang('staff confirm each booking')),
        'texts'    => ($filled > 0)
            ? lang(array('string' => '{var:1} of {var:2} texts set', 'vars' => array($filled, count(pg_booking_settings_text_fields()))))
            : lang('The widget\'s own wording'),
    );
}

/**
 * The ISO weekdays in the order the week starts on.
 *
 * @param int $first_day 0 (Sunday) or 1 (Monday), as date('w') counts
 * @return int[]
 */
function pg_booking_settings_week_days($first_day)
{
    return ((int) $first_day === 0) ? array(7, 1, 2, 3, 4, 5, 6) : array(1, 2, 3, 4, 5, 6, 7);
}

/**
 * The weekly hours grid read back: h<day>a-h<day>b is the first range of an
 * ISO weekday and h<day>c-h<day>d the second. A day with both boxes of a
 * range empty has no such range; a range with one box, an unreadable time or
 * an end not after the start is named in bad (its box that is wrong). An
 * end of 00:00 is the end of the day (24:00).
 *
 * @param array $fields
 * @return array hours => as pg_booking_hours_clean(), bad => box keys
 */
function pg_booking_settings_hours_from_fields($fields)
{
    $fields = is_array($fields) ? $fields : array();
    $hours = array();
    $bad = array();

    for ($day = 1; $day <= 7; $day++) {

        foreach (array(array('a', 'b'), array('c', 'd')) as $pair) {

            $from_key = 'h' . $day . $pair[0];
            $to_key = 'h' . $day . $pair[1];
            $from = (isset($fields[$from_key]) && is_scalar($fields[$from_key])) ? trim((string) $fields[$from_key]) : '';
            $to = (isset($fields[$to_key]) && is_scalar($fields[$to_key])) ? trim((string) $fields[$to_key]) : '';

            if (($from === '') && ($to === '')) {
                continue;
            }

            $start = pg_booking_minutes($from);
            $end = pg_booking_minutes($to);

            // A time box ends at 23:59 and cannot post 24:00: an end of
            // 00:00 is the end of the day.
            if ($end === 0) {
                $end = 1440;
                $to = '24:00';
            }

            if ($start === null) {
                $bad[] = $from_key;
            } elseif (($end === null) || ($end <= $start)) {
                $bad[] = $to_key;
            } else {
                $hours[$day][] = array($from, $to);
            }
        }
    }

    return array('hours' => pg_booking_hours_clean($hours), 'bad' => $bad);
}

/**
 * A range's end as a time box shows it: the end of the day (24:00) as 00:00.
 *
 * @param string $end 'HH:MM'
 * @return string
 */
function pg_booking_settings_box_end($end)
{
    return ($end === '24:00') ? '00:00' : $end;
}

/**
 * The weekly hours as the grid's boxes: ISO weekday => array(from 1, to 1,
 * from 2, to 2). A day with more than two ranges (never written by this
 * screen) shows its first two.
 *
 * @param mixed $hours
 * @return array
 */
function pg_booking_settings_hours_fields($hours)
{
    $hours = pg_booking_hours_clean($hours);
    $fields = array();

    for ($day = 1; $day <= 7; $day++) {

        $ranges = isset($hours[$day]) ? $hours[$day] : array();

        // A time box cannot show 24:00 and would come back empty: the end
        // of the day is shown as 00:00, which the save reads as 24:00.
        $fields[$day] = array(
            isset($ranges[0]) ? $ranges[0][0] : '',
            isset($ranges[0]) ? pg_booking_settings_box_end($ranges[0][1]) : '',
            isset($ranges[1]) ? $ranges[1][0] : '',
            isset($ranges[1]) ? pg_booking_settings_box_end($ranges[1][1]) : '',
        );
    }

    return $fields;
}

/**
 * The exception rows read back as day-by-day exceptions. A row is a first
 * day, an optional last day, and an optional range of hours: without hours
 * every day of the row is a day off. Days before today are dropped, and a
 * row covers a year at most. A row with an unreadable first day, a last day
 * before the first or a broken range of hours is named in bad by its index.
 * A later row wins over an earlier one on the same day.
 *
 * @param array  $from  first days 'YYYY-MM-DD'
 * @param array  $to    last days, '' for one day
 * @param array  $open  start of the hours, '' for a day off
 * @param array  $close end of the hours
 * @param string $today 'YYYY-MM-DD' in the site's zone
 * @return array exceptions => as pg_booking_exceptions_clean(), bad => row indexes
 */
function pg_booking_settings_exceptions_from_rows($from, $to, $open, $close, $today)
{
    $pick = function ($list, $index) {
        return (is_array($list) && isset($list[$index]) && is_scalar($list[$index])) ? trim((string) $list[$index]) : '';
    };

    $exceptions = array();
    $bad = array();

    foreach (array_keys(is_array($from) ? $from : array()) as $index) {

        $first = $pick($from, $index);
        $last = $pick($to, $index);
        $start = $pick($open, $index);
        $end = $pick($close, $index);

        if (($first === '') && ($last === '') && ($start === '') && ($end === '')) {
            continue;
        }

        if (!pg_booking_date_valid($first) || (($last !== '') && (!pg_booking_date_valid($last) || ($last < $first)))) {
            $bad[] = $index;
            continue;
        }

        $ranges = array();

        if (($start !== '') || ($end !== '')) {

            // As in the weekly grid, an end of 00:00 is the end of the day.
            if (pg_booking_minutes($end) === 0) {
                $end = '24:00';
            }

            $ranges = pg_booking_ranges_clean(array(array($start, $end)));

            if (!$ranges) {
                $bad[] = $index;
                continue;
            }
        }

        $day = new DateTime($first . ' 12:00:00', new DateTimeZone('UTC'));
        $stop = ($last === '') ? $first : $last;

        for ($count = 0; ($count < 366) && ($day->format('Y-m-d') <= $stop); $count++) {

            if ($day->format('Y-m-d') >= $today) {
                $exceptions[$day->format('Y-m-d')] = $ranges;
            }

            $day->modify('+1 day');
        }
    }

    return array('exceptions' => pg_booking_exceptions_clean($exceptions), 'bad' => $bad);
}

/**
 * The stored exceptions from today on as the screen's rows: following days
 * with the same hours become one row (to '' when it is one day), a day with
 * several ranges shows its first.
 *
 * @param mixed  $exceptions
 * @param string $today 'YYYY-MM-DD'
 * @return array list of array(from, to, open, close)
 */
function pg_booking_settings_exception_rows($exceptions, $today)
{
    $rows = array();
    $previous = '';

    foreach (pg_booking_exceptions_clean($exceptions) as $date => $ranges) {

        if ($date < $today) {
            continue;
        }

        $open = isset($ranges[0]) ? $ranges[0][0] : '';
        $close = isset($ranges[0]) ? pg_booking_settings_box_end($ranges[0][1]) : '';
        $last = count($rows) - 1;

        if (($last >= 0) && ($previous !== '')
            && (gmdate('Y-m-d', strtotime($previous . ' 12:00:00 UTC') + 86400) === $date)
            && ($rows[$last]['open'] === $open) && ($rows[$last]['close'] === $close)) {
            $rows[$last]['to'] = $date;
        } else {
            $rows[] = array('from' => $date, 'to' => '', 'open' => $open, 'close' => $close);
        }

        $previous = $date;
    }

    return $rows;
}

/**
 * A staff member's posted services as ids of known services, ascending.
 *
 * @param mixed $ids
 * @param int[] $known
 * @return int[]
 */
function pg_booking_settings_service_ids($ids, $known)
{
    $clean = array();

    foreach ((is_array($ids) ? $ids : array()) as $id) {
        if (is_scalar($id) && (preg_match('/^\d+$/', (string) $id) === 1) && in_array((int) $id, $known, true)) {
            $clean[(int) $id] = (int) $id;
        }
    }

    sort($clean);

    return array_values($clean);
}

/**
 * Every service, in its order.
 *
 * @return array id => row
 */
function pg_booking_settings_services()
{
    return (array) db_items("SELECT * FROM booking_services ORDER BY sort_order, id", 'id');
}

/**
 * Every staff member with the name of the panel user, in the order of names.
 *
 * @return array user id => row (+ person)
 */
function pg_booking_settings_staff()
{
    $rows = (array) db_items(
        "SELECT booking_staff.*, user.user_username, contacts.first_name, contacts.last_name
        FROM booking_staff
        LEFT JOIN user ON user.user_id = booking_staff.user_id
        LEFT JOIN contacts ON contacts.id = user.user_contact
        ORDER BY booking_staff.id");

    $staff = array();

    foreach ($rows as $row) {
        $row['person'] = pg_user_display_name($row['first_name'], $row['last_name'], (string) $row['user_username']);
        $staff[(int) $row['user_id']] = $row;
    }

    uasort($staff, function ($a, $b) {
        return strcasecmp($a['person'], $b['person']);
    });

    return $staff;
}

/**
 * The panel users who may be added as staff: every user of the panel (role
 * below 3) who is not one yet.
 *
 * @param array $staff as pg_booking_settings_staff()
 * @return array user id => name
 */
function pg_booking_settings_candidates($staff)
{
    $rows = (array) db_items(
        "SELECT user.user_id, user.user_username, contacts.first_name, contacts.last_name
        FROM user
        LEFT JOIN contacts ON contacts.id = user.user_contact
        WHERE user.user_role < 3
        ORDER BY user.user_username");

    $people = array();

    foreach ($rows as $row) {
        if (!isset($staff[(int) $row['user_id']])) {
            $people[(int) $row['user_id']] = pg_user_display_name($row['first_name'], $row['last_name'], (string) $row['user_username']);
        }
    }

    asort($people, SORT_FLAG_CASE | SORT_STRING);

    return $people;
}

/**
 * Write the services posted by the Services card: each existing row by its
 * id, each filled new row inserted. Runs after the check, so every row is
 * known to be right.
 *
 * @param array $rows id => columns (as pg_booking_settings_service_clean() values)
 * @param array $new  list of columns
 * @param int   $now
 * @return void
 */
function pg_booking_settings_save_services($rows, $new, $now)
{
    // The deposit columns arrive with 11.0.169; a row cleaned without the
    // deposit boxes carries no deposit and leaves the stored one alone.
    $deposit_ready = function_exists('pg_booking_deposit_ready') && pg_booking_deposit_ready();
    $deposit = function ($id, $values) use ($deposit_ready) {
        if ($deposit_ready && ((int) $id > 0) && array_key_exists('deposit', $values)) {
            db_q("UPDATE booking_services SET deposit = ?, deposit_product_id = ? WHERE id = ?",
                array((int) $values['deposit'], (int) $values['deposit_product_id'], (int) $id));
        }
    };

    foreach ($rows as $id => $values) {
        db_q("UPDATE booking_services SET name = ?, description = ?, length_minutes = ?, buffer_before = ?, buffer_after = ?, price = ?, enabled = ?, sort_order = ?, updated_at = ? WHERE id = ?",
            array($values['name'], $values['description'], $values['length_minutes'], $values['buffer_before'], $values['buffer_after'], $values['price'], $values['enabled'], $values['sort_order'], (int) $now, (int) $id));
        $deposit($id, $values);
    }

    foreach ($new as $values) {
        if (db_q("INSERT INTO booking_services (name, description, length_minutes, buffer_before, buffer_after, price, capacity, enabled, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)",
            array($values['name'], $values['description'], $values['length_minutes'], $values['buffer_before'], $values['buffer_after'], $values['price'], $values['enabled'], $values['sort_order'], (int) $now, (int) $now))) {
            $deposit(mysqli_insert_id(db::$con), $values);
        }
    }
}

/**
 * Write the staff posted by the Staff card, by user id, and add the user
 * picked to be added with the weekly hours of a working week (Monday to
 * Friday, 09:00-17:00) and every enabled service.
 *
 * @param array $rows   user id => array(service_ids, hours, exceptions, enabled)
 * @param int   $add    user id to add, 0 for none
 * @param int[] $services enabled service ids, for the new staff member
 * @param int   $now
 * @return void
 */
function pg_booking_settings_save_staff($rows, $add, $services, $now)
{
    foreach ($rows as $user_id => $values) {
        db_q("UPDATE booking_staff SET service_ids = ?, hours = ?, exceptions = ?, enabled = ?, updated_at = ? WHERE user_id = ?",
            array(json_encode($values['service_ids']), json_encode((object) $values['hours']), json_encode((object) $values['exceptions']), $values['enabled'], (int) $now, (int) $user_id));
    }

    if ((int) $add > 0) {

        $week = array();

        for ($day = 1; $day <= 5; $day++) {
            $week[$day] = array(array('09:00', '17:00'));
        }

        db_q("INSERT IGNORE INTO booking_staff (user_id, service_ids, hours, exceptions, enabled, created_at, updated_at) VALUES (?, ?, ?, '{}', 1, ?, ?)",
            array((int) $add, json_encode(array_values(array_map('intval', $services))), json_encode((object) $week), (int) $now, (int) $now));
    }
}
