<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Orders › Delivery problems: the desk where staff with the orders right see
 * the problems the hourly pass found on parcels (shipment_problems), give
 * one to a person, keep a note and close it with a reason. The finding and
 * the closing by a later status are includes/fn/delivery_tower_passes.php;
 * this module reads the rows, writes assignee, note and the closing by hand,
 * and draws the list's cells and a problem's drawer for
 * view_delivery_problems.php. Every change is an audit_log line
 * (entity_type = 'shipment_problem') that carries no name or address.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Rows the list prints at most (newest found first beyond it are left out).
define('PG_DELIVERY_PROBLEMS_LIST', 2000);

// Characters a problem's note keeps (shipment_problems.note).
define('PG_DELIVERY_PROBLEMS_NOTE', 500);

/**
 * The kinds' names on screen and their pill tones: waiting on the carrier
 * amber, a failed delivery rose. Pure.
 *
 * @return array kind code => array(label, tone)
 */
function pg_delivery_problem_kinds()
{
    $codes = pg_delivery_tower_kind_codes();

    return array(
        $codes['stuck']           => array(lang('Stuck'), 'amber'),
        $codes['not_handed_over'] => array(lang('Not handed over'), 'amber'),
        $codes['problem']         => array(lang('Delivery problem'), 'rose'),
        $codes['returned']        => array(lang('Returned'), 'rose'),
    );
}

/**
 * The reasons staff close a problem with, in the order the choice shows
 * them. Pure.
 *
 * @return array close_reason code => label
 */
function pg_delivery_problem_close_choices()
{
    $codes = pg_delivery_tower_close_reasons();

    return array(
        $codes['sent_again'] => lang('Sent again'),
        $codes['refunded']   => lang('Refunded'),
        $codes['found']      => lang('Parcel found'),
        $codes['other']      => lang('Other'),
    );
}

/**
 * The list's filters from the address: Open, Closed or All, a kind code or
 * 0 for every kind, a carrier or '' for every carrier. Pure.
 *
 * @param array $get
 * @return array view, kind, carrier
 */
function pg_delivery_problem_filters(array $get)
{
    $view = (isset($get['view']) && is_string($get['view']) && in_array($get['view'], array('open', 'closed', 'all'), true)) ? $get['view'] : 'open';
    $kind = (isset($get['kind']) && is_scalar($get['kind'])) ? (int) $get['kind'] : 0;
    $carrier = (isset($get['carrier']) && is_string($get['carrier'])) ? trim($get['carrier']) : '';

    return array(
        'view'    => $view,
        'kind'    => isset(pg_delivery_problem_kinds()[$kind]) ? $kind : 0,
        'carrier' => mb_substr($carrier, 0, 20),
    );
}

/**
 * The address of the list with the filters in force, plus more values.
 * Pure.
 *
 * @param array $filters pg_delivery_problem_filters()
 * @param array $more    name => value
 * @return string
 */
function pg_delivery_problem_url(array $filters, array $more = array())
{
    $query = array();

    if ($filters['view'] !== 'open') {
        $query['view'] = $filters['view'];
    }

    if ($filters['kind'] > 0) {
        $query['kind'] = (int) $filters['kind'];
    }

    if ($filters['carrier'] !== '') {
        $query['carrier'] = $filters['carrier'];
    }

    $query = array_merge($query, $more);

    return 'view_delivery_problems.php' . ($query ? '?' . http_build_query($query) : '');
}

/**
 * The WHERE of the list for its filters, with its bound values. Pure.
 *
 * @param array $filters pg_delivery_problem_filters()
 * @return array array(sql, params)
 */
function pg_delivery_problem_where(array $filters)
{
    $where = array();
    $params = array();

    if ($filters['view'] === 'open') {
        $where[] = '(shipment_problems.closed_at = 0)';
    } elseif ($filters['view'] === 'closed') {
        $where[] = '(shipment_problems.closed_at > 0)';
    }

    if ($filters['kind'] > 0) {
        $where[] = '(shipment_problems.kind = ?)';
        $params[] = (int) $filters['kind'];
    }

    if ($filters['carrier'] !== '') {
        $where[] = '(shipping_tracking_numbers.carrier = ?)';
        $params[] = $filters['carrier'];
    }

    return array($where ? implode(' AND ', $where) : '1', $params);
}

/**
 * The whole days a problem has been open: from found to closed, or to now.
 * Pure.
 *
 * @param array  $problem found_at, closed_at
 * @param int    $now
 * @param string $timezone '' = PHP's default
 * @return int
 */
function pg_delivery_problem_days(array $problem, $now, $timezone = '')
{
    $end = ((int) $problem['closed_at'] > 0) ? (int) $problem['closed_at'] : (int) $now;

    return pg_delivery_tower_calendar_days((int) $problem['found_at'], $end, $timezone);
}

/**
 * The first words of a note for the list: whole words up to $length
 * characters, an ellipsis when it was cut. Pure.
 *
 * @param string $note
 * @param int    $length
 * @return string
 */
function pg_delivery_problem_note_start($note, $length = 60)
{
    $note = trim(preg_replace('/\s+/u', ' ', (string) $note));

    if (mb_strlen($note) <= $length) {
        return $note;
    }

    $cut = mb_substr($note, 0, $length);
    $space = mb_strrpos($cut, ' ');

    if (($space !== false) && ($space > ($length / 2))) {
        $cut = mb_substr($cut, 0, $space);
    }

    return rtrim($cut, " ,.;:-") . '…';
}

/**
 * A note as it is kept: one line, at most PG_DELIVERY_PROBLEMS_NOTE
 * characters. A closing note is added after the note there was. Pure.
 *
 * @param string $note
 * @param string $added '' = none
 * @return string
 */
function pg_delivery_problem_note_clean($note, $added = '')
{
    $note = trim(preg_replace('/\s+/u', ' ', (string) $note));
    $added = trim(preg_replace('/\s+/u', ' ', (string) $added));

    if ($added !== '') {
        $note = ($note !== '') ? $note . ' · ' . $added : $added;
    }

    return mb_substr($note, 0, PG_DELIVERY_PROBLEMS_NOTE);
}

/**
 * Why a closed problem closed, in the words the drawer shows. A problem the
 * pass closed (close_reason 1) says what settled it: the parcel delivered,
 * removed, its order cancelled, else a later scan. Pure.
 *
 * @param array      $problem close_reason
 * @param array|null $parcel  status, delivered_at; null when the row is gone
 * @param string     $order_status '' when the order is gone
 * @return string '' for an open problem
 */
function pg_delivery_problem_close_label(array $problem, $parcel, $order_status)
{
    $reasons = pg_delivery_tower_close_reasons();
    $reason = (int) $problem['close_reason'];
    $choices = pg_delivery_problem_close_choices();

    if (isset($choices[$reason])) {
        return $choices[$reason];
    }

    if ($reason !== $reasons['status']) {
        return '';
    }

    if ($parcel === null) {
        return lang('The parcel was removed');
    }

    if (((int) $parcel['delivered_at'] > 0) || ((string) $parcel['status'] === 'delivered')) {
        return lang('Delivered');
    }

    if (($order_status === '') || in_array($order_status, array('incomplete', 'cancelled'), true)) {
        return lang('The order was cancelled');
    }

    return lang('Scanned by the carrier');
}

/**
 * What an assign, note or close changes on a problem, or why it changes
 * nothing. Pure.
 *
 * @param array $problem    the row: assigned_to, note, closed_at
 * @param array $input      assigned_to, note (absent = kept), close (a
 *                          close_reason code, 0 = not closing), close_note
 * @param array $assignees  user id => name, the people one may be given to
 * @param int   $now
 * @param int   $operator   the user closing
 * @return array changes (column => value), error ('' | closed | reason)
 */
function pg_delivery_problem_changes(array $problem, array $input, array $assignees, $now, $operator)
{
    if ((int) $problem['closed_at'] > 0) {
        return array('changes' => array(), 'error' => 'closed');
    }

    $changes = array();

    if (array_key_exists('assigned_to', $input)) {
        $assigned = (int) $input['assigned_to'];
        $assigned = isset($assignees[$assigned]) ? $assigned : 0;

        if ($assigned !== (int) $problem['assigned_to']) {
            $changes['assigned_to'] = $assigned;
        }
    }

    $note = array_key_exists('note', $input) ? pg_delivery_problem_note_clean($input['note']) : (string) $problem['note'];
    $close = isset($input['close']) ? (int) $input['close'] : 0;

    if ($close !== 0) {
        if (!isset(pg_delivery_problem_close_choices()[$close])) {
            return array('changes' => array(), 'error' => 'reason');
        }

        $note = pg_delivery_problem_note_clean($note, isset($input['close_note']) ? $input['close_note'] : '');
        $changes['closed_at'] = (int) $now;
        $changes['closed_by'] = (int) $operator;
        $changes['close_reason'] = $close;
    }

    if ($note !== (string) $problem['note']) {
        $changes['note'] = $note;
    }

    if ($changes) {
        $changes['updated_at'] = (int) $now;
    }

    return array('changes' => $changes, 'error' => '');
}

/**
 * The fields of a problem an audit line keeps: no name, no address. Pure.
 *
 * @param array $problem
 * @return array
 */
function pg_delivery_problem_audit_view(array $problem)
{
    $view = array();

    foreach (array('kind', 'assigned_to', 'note', 'closed_at', 'closed_by', 'close_reason') as $key) {
        if (isset($problem[$key])) {
            $view[$key] = $problem[$key];
        }
    }

    return $view;
}

/**
 * "What happened" on a parcel, newest first: the label made, the parcel
 * handed over, its last status, its delivery, and each problem found and
 * closed on it. The parcel row keeps only the moments of its latest status,
 * so a status between two of them is not listed. Pure.
 *
 * @param array|null $parcel   created_at, shipped_at, status, status_at, delivered_at
 * @param array      $problems rows on the parcel: kind, found_at, closed_at, close_reason, closed_by
 * @param array      $labels   close label per problem id (pg_delivery_problem_close_label())
 * @return array list of array(at, text)
 */
function pg_delivery_problem_events($parcel, array $problems, array $labels = array())
{
    $events = array();
    $kinds = pg_delivery_problem_kinds();

    if ($parcel !== null) {
        $created = (int) ($parcel['created_at'] ?? 0);
        $shipped = (int) ($parcel['shipped_at'] ?? 0);
        $delivered = (int) ($parcel['delivered_at'] ?? 0);
        $status = (string) ($parcel['status'] ?? 'created');
        $status_at = (int) ($parcel['status_at'] ?? 0);

        if ($created > 0) {
            $events[] = array('at' => $created, 'text' => lang('Label made'), 'order' => 0);
        }

        if (($shipped > 0) && ($shipped !== $created)) {
            $events[] = array('at' => $shipped, 'text' => lang('Shipped'), 'order' => 1);
        }

        if (($status_at > 0) && !in_array($status, array('created', 'delivered'), true)) {
            $events[] = array('at' => $status_at, 'text' => pg_shipment_status_label($status), 'order' => 2);
        }

        if ($delivered > 0) {
            $events[] = array('at' => $delivered, 'text' => lang('Delivered'), 'order' => 3);
        }
    }

    foreach ($problems as $problem) {
        $kind = isset($kinds[(int) $problem['kind']]) ? $kinds[(int) $problem['kind']][0] : '';

        if ((int) $problem['found_at'] > 0) {
            $events[] = array('at' => (int) $problem['found_at'], 'text' => lang(array('string' => 'Found: {var:1}', 'vars' => array($kind))), 'order' => 4);
        }

        if ((int) $problem['closed_at'] > 0) {
            $label = $labels[(int) ($problem['id'] ?? 0)] ?? '';
            $events[] = array('at' => (int) $problem['closed_at'], 'text' => lang(array('string' => 'Closed: {var:1}', 'vars' => array(($label !== '') ? $label : $kind))), 'order' => 5);
        }
    }

    usort($events, function ($a, $b) {
        if ($a['at'] !== $b['at']) {
            return ($a['at'] < $b['at']) ? 1 : -1;
        }

        return $b['order'] - $a['order'];
    });

    return array_map(function ($event) {
        return array('at' => $event['at'], 'text' => $event['text']);
    }, $events);
}

/**
 * The days a parcel has been on its way: from shipped to delivered, or to
 * now; null before it was shipped. Pure.
 *
 * @param array  $parcel shipped_at, delivered_at
 * @param int    $now
 * @param string $timezone
 * @return int|null
 */
function pg_delivery_problem_transit_days(array $parcel, $now, $timezone = '')
{
    $shipped = (int) ($parcel['shipped_at'] ?? 0);

    if ($shipped <= 0) {
        return null;
    }

    $end = ((int) ($parcel['delivered_at'] ?? 0) > 0) ? (int) $parcel['delivered_at'] : (int) $now;

    return pg_delivery_tower_calendar_days($shipped, $end, $timezone);
}

/**
 * The carrier's usual days for the parcel's province: the median of the
 * nightly summary's figure (the province's, else the country's), rounded to
 * a tenth. Pure.
 *
 * @param array|null $summary pg_delivery_tower_summary()
 * @param string     $carrier shipping_tracking_numbers.carrier
 * @param string     $state
 * @param string     $city
 * @return float|null
 */
function pg_delivery_problem_usual_days($summary, $carrier, $state, $city)
{
    if (!is_array($summary) || !isset($summary['figures']) || ((string) $carrier === '')) {
        return null;
    }

    $figure = pg_delivery_tower_figure_for($summary['figures'], (string) $carrier, pg_delivery_tower_province_key($state, $city));

    return (is_array($figure) && isset($figure['median'])) ? round((float) $figure['median'], 1) : null;
}

/**
 * Whether the desk's table is there.
 *
 * @return bool
 */
function pg_delivery_problems_ready()
{
    return function_exists('pg_delivery_tower_ready') && pg_delivery_tower_ready();
}

/**
 * The rows of a query with bound values.
 *
 * @param string $sql
 * @param array  $params
 * @return array
 */
function _pg_delivery_problems_rows($sql, array $params = array())
{
    $result = db_q($sql, $params);
    $rows = array();

    if ($result instanceof mysqli_result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }

    return $rows;
}

/**
 * The columns every read of a problem takes: the problem, its parcel and
 * the order's number, names and type.
 *
 * @return string
 */
function _pg_delivery_problems_select()
{
    return "SELECT
            shipment_problems.*,
            shipping_tracking_numbers.id AS parcel_id,
            shipping_tracking_numbers.number,
            shipping_tracking_numbers.carrier,
            shipping_tracking_numbers.tracking_url,
            shipping_tracking_numbers.status,
            shipping_tracking_numbers.status_at,
            shipping_tracking_numbers.shipped_at,
            shipping_tracking_numbers.delivered_at,
            shipping_tracking_numbers.created_at,
            orders.order_number,
            orders.status AS order_status,
            orders.type AS order_type,
            orders.billing_first_name,
            orders.billing_last_name,
            ship_tos.state,
            ship_tos.city,
            ship_tos.shipping_method_code,
            ship_tos.delivery_date,
            user.user_username AS assignee
        FROM shipment_problems
        LEFT JOIN shipping_tracking_numbers ON shipping_tracking_numbers.id = shipment_problems.shipment_id
        LEFT JOIN orders ON orders.id = shipment_problems.order_id
        LEFT JOIN ship_tos ON ship_tos.id = shipment_problems.ship_to_id
        LEFT JOIN user ON user.user_id = shipment_problems.assigned_to";
}

/**
 * The list's rows for its filters: open problems oldest first, closed ones
 * newest closed first.
 *
 * @param array $filters pg_delivery_problem_filters()
 * @return array
 */
function pg_delivery_problems_list(array $filters)
{
    list($where, $params) = pg_delivery_problem_where($filters);

    return _pg_delivery_problems_rows(_pg_delivery_problems_select() . "
        WHERE " . $where . "
        ORDER BY (shipment_problems.closed_at = 0) DESC, shipment_problems.closed_at DESC, shipment_problems.found_at ASC, shipment_problems.id ASC
        LIMIT " . PG_DELIVERY_PROBLEMS_LIST, $params);
}

/**
 * One problem with its parcel and order, or null.
 *
 * @param int $id
 * @return array|null
 */
function pg_delivery_problem_get($id)
{
    if ((int) $id <= 0) {
        return null;
    }

    $rows = _pg_delivery_problems_rows(_pg_delivery_problems_select() . " WHERE shipment_problems.id = ?", array((int) $id));

    return $rows ? $rows[0] : null;
}

/**
 * Every problem found on one parcel, for its "What happened".
 *
 * @param int $shipment_id
 * @return array
 */
function pg_delivery_problems_of_parcel($shipment_id)
{
    return _pg_delivery_problems_rows("SELECT id, kind, found_at, closed_at, closed_by, close_reason FROM shipment_problems WHERE shipment_id = ? ORDER BY found_at", array((int) $shipment_id));
}

/**
 * The carriers that have a problem, for the carrier filter.
 *
 * @return array carrier => name
 */
function pg_delivery_problems_carriers()
{
    $carriers = array();

    foreach (_pg_delivery_problems_rows("SELECT DISTINCT shipping_tracking_numbers.carrier FROM shipment_problems
        INNER JOIN shipping_tracking_numbers ON shipping_tracking_numbers.id = shipment_problems.shipment_id
        WHERE shipping_tracking_numbers.carrier != '' ORDER BY shipping_tracking_numbers.carrier") as $row) {
        $carriers[(string) $row['carrier']] = pg_delivery_problem_carrier_name((string) $row['carrier']);
    }

    asort($carriers);

    return $carriers;
}

/**
 * A carrier's name as the order screen shows it: the known carrier's name,
 * else the name as it was typed.
 *
 * @param string $carrier
 * @return string
 */
function pg_delivery_problem_carrier_name($carrier)
{
    $carriers = pg_shipping_carriers();
    $key = pg_shipping_carrier_text((string) $carrier);

    return isset($carriers[$key]) ? (string) $carriers[$key]['name'] : (string) $carrier;
}

/**
 * The people a problem can be given to: those with the orders right
 * (Manager and up, or a user who manages the store).
 *
 * @return array user id => user name
 */
function pg_delivery_problem_assignees()
{
    $people = array();

    foreach (db_items("SELECT user_id, user_username FROM user WHERE (user_role < 3) OR (user_manage_ecommerce = 'yes') ORDER BY user_username") as $row) {
        $people[(int) $row['user_id']] = (string) $row['user_username'];
    }

    return $people;
}

/**
 * Write one problem's change and its audit line.
 *
 * @param array $problem the row before
 * @param array $changes column => value
 * @return bool
 */
function _pg_delivery_problem_write(array $problem, array $changes)
{
    $sets = array();

    foreach (array_keys($changes) as $column) {
        $sets[] = '`' . $column . '` = ?';
    }

    // closed_at = 0 in the WHERE: a pass that closed it meanwhile wins.
    $written = db_q("UPDATE shipment_problems SET " . implode(', ', $sets) . " WHERE id = ? AND closed_at = 0", array_merge(array_values($changes), array((int) $problem['id'])));

    if (($written === false) || (mysqli_affected_rows(db::$con) < 1)) {
        return false;
    }

    pg_audit(isset($changes['closed_at']) ? 'close' : 'update', 'shipment_problem', (int) $problem['id'],
        pg_delivery_problem_audit_view($problem), pg_delivery_problem_audit_view(array_merge($problem, $changes)), (string) ($problem['number'] ?? ''));

    return true;
}

/**
 * Save an assign, a note or a closing on problems.
 *
 * @param int[] $ids
 * @param array $input see pg_delivery_problem_changes()
 * @param int   $operator the user
 * @return array saved, skipped (already closed), error ('' | reason)
 */
function pg_delivery_problems_save(array $ids, array $input, $operator)
{
    $out = array('saved' => 0, 'skipped' => 0, 'error' => '');
    $assignees = pg_delivery_problem_assignees();
    $now = time();

    foreach (array_slice(array_unique(array_map('intval', $ids)), 0, PG_DELIVERY_PROBLEMS_LIST) as $id) {
        $problem = pg_delivery_problem_get($id);

        if ($problem === null) {
            continue;
        }

        $result = pg_delivery_problem_changes($problem, $input, $assignees, $now, $operator);

        if ($result['error'] === 'reason') {
            $out['error'] = 'reason';
            return $out;
        }

        if ($result['error'] === 'closed') {
            $out['skipped']++;
        } elseif (!$result['changes'] || _pg_delivery_problem_write($problem, $result['changes'])) {
            $out['saved']++;
        } else {
            $out['skipped']++;
        }
    }

    return $out;
}

/**
 * A kind's pill; grey once the problem is closed.
 *
 * @param int  $kind
 * @param bool $closed
 * @return string markup
 */
function pg_delivery_problem_kind_pill($kind, $closed = false)
{
    $kinds = pg_delivery_problem_kinds();
    $item = isset($kinds[(int) $kind]) ? $kinds[(int) $kind] : array('', 'gray');

    return '<span class="pg-pill pg-tone-' . ($closed ? 'gray' : $item[1]) . '">' . h($item[0]) . '</span>';
}

/**
 * The list's columns.
 *
 * @return array key => spec
 */
function pg_delivery_problem_list_columns()
{
    return array(
        'parcel'   => array('heading' => lang('Parcel'), 'kind' => 'link'),
        'order'    => array('heading' => lang('Order')),
        'carrier'  => array('heading' => lang('Carrier')),
        'kind'     => array('heading' => lang('Kind'), 'kind' => 'badge'),
        'since'    => array('heading' => lang('Since')),
        'assignee' => array('heading' => lang('Assignee')),
        'note'     => array('heading' => lang('Note')),
    );
}

/**
 * The days text: "1 day", "4 days".
 *
 * @param int $days
 * @return string
 */
function pg_delivery_problem_days_text($days)
{
    return ((int) $days === 1) ? lang('1 day') : lang(array('string' => '{var:1} days', 'vars' => array((int) $days)));
}

/**
 * One row of the list.
 *
 * @param array $row     a row of pg_delivery_problems_list()
 * @param array $filters the filters in force, kept by the row's link
 * @param int   $now
 * @return array key => pg_list_cell()
 */
function pg_delivery_problem_list_cells(array $row, array $filters, $now)
{
    $id = (int) $row['id'];
    $number = ((string) ($row['number'] ?? '') !== '') ? (string) $row['number'] : lang('Parcel removed');
    $order = ((string) ($row['order_number'] ?? '') !== '') ? '<a href="view_order.php?id=' . (int) $row['order_id'] . '">' . h((string) $row['order_number']) . '</a>' : '';
    $days = pg_delivery_problem_days($row, $now);

    if ((string) ($row['order_type'] ?? '') === 'marketplace') {
        $order .= ' <span class="pg-pill pg-tone-gray">' . h(lang('Marketplace')) . '</span>';
    }

    return array(
        'parcel'   => pg_list_cell('<a href="' . h(pg_delivery_problem_url($filters, array('problem' => $id))) . '" data-pg-open>' . h($number) . '</a>'),
        'order'    => pg_list_cell($order),
        'carrier'  => pg_list_cell(h(pg_delivery_problem_carrier_name((string) ($row['carrier'] ?? '')))),
        'kind'     => pg_list_cell(pg_delivery_problem_kind_pill((int) $row['kind'], (int) $row['closed_at'] > 0), 'align-middle', array('data-pg-sort-value' => (int) $row['kind'])),
        'since'    => pg_list_cell('<span class="pg-tabular">' . h(pg_delivery_problem_days_text($days)) . '</span>', 'align-middle', array('data-pg-sort-value' => $days)),
        'assignee' => pg_list_cell(h((string) ($row['assignee'] ?? ''))),
        'note'     => pg_list_cell(h(pg_delivery_problem_note_start((string) $row['note']))),
    );
}

/**
 * A drawer of the desk: Bootstrap 6's drawer as the Design examples screen
 * draws it, opened through pgUI. $form is the id of the form its fields
 * belong to when the drawer carries no form of its own.
 *
 * @param string $id
 * @param string $title
 * @param string $head  markup under the title
 * @param string $body
 * @param string $foot
 * @param array  $opts  open (bool), form ('' = its own form posting to $action), action
 * @return string
 */
function pg_delivery_problem_drawer_frame($id, $title, $head, $body, $foot, array $opts = array())
{
    $own = empty($opts['form']);
    $inner = '<div class="drawer-header">'
            . '<div class="flex-grow-1 min-width-0">'
            . '<h5 class="drawer-title mb-0" id="' . h($id) . '_title">' . h($title) . '</h5>'
            . (($head !== '') ? '<p class="small fg-2 mb-0 mt-1 d-flex align-items-center gap-3">' . $head . '</p>' : '')
            . '</div>'
            . '<button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>'
        . '</div>'
        . '<div class="drawer-body flex-grow-1 overflow-auto">' . $body . '</div>'
        . '<div class="drawer-footer border-top p-5 d-flex align-items-center gap-3">' . $foot . '</div>';

    return '<dialog class="drawer drawer-end" id="' . h($id) . '" aria-labelledby="' . h($id) . '_title" style="width:min(480px,100%)"' . (!empty($opts['open']) ? ' data-open="1"' : '') . '>'
        . ($own ? '<form method="post" action="' . h((string) ($opts['action'] ?? 'view_delivery_problems.php')) . '" class="d-flex flex-column h-100">' . get_token_field() . $inner . '</form>' : '<div class="d-flex flex-column h-100">' . $inner . '</div>')
        . '</dialog>';
}

/**
 * A labelled field of a drawer.
 *
 * @param string $id
 * @param string $label
 * @param string $control markup
 * @param string $help
 * @return string
 */
function pg_delivery_problem_field($id, $label, $control, $help = '')
{
    return '<div class="form-field mb-5"><label class="form-label pg-field-label" for="' . h($id) . '">' . h($label) . '</label>' . $control
        . (($help !== '') ? '<div class="form-text" id="' . h($id) . '_help">' . h($help) . '</div>' : '') . '</div>';
}

/**
 * The assignee select.
 *
 * @param string $id
 * @param array  $assignees user id => name
 * @param int    $current
 * @param string $form  the form the select belongs to, '' = the one around it
 * @return string
 */
function pg_delivery_problem_assignee_select($id, array $assignees, $current, $form = '')
{
    $options = '<option value="0">' . h(lang('Nobody yet')) . '</option>';

    foreach ($assignees as $user_id => $name) {
        $options .= '<option value="' . (int) $user_id . '"' . (((int) $current === (int) $user_id) ? ' selected' : '') . '>' . h($name) . '</option>';
    }

    return '<select class="pg-input" id="' . h($id) . '" name="assigned_to"' . (($form !== '') ? ' form="' . h($form) . '"' : '') . '>' . $options . '</select>';
}

/**
 * The close reason as a segmented choice with its note: the reason a
 * hidden field, the segments set it.
 *
 * @param string $id
 * @param string $form '' = the form around it
 * @return string
 */
function pg_delivery_problem_close_fields($id, $form = '')
{
    $owner = ($form !== '') ? ' form="' . h($form) . '"' : '';
    $segments = '';

    foreach (pg_delivery_problem_close_choices() as $code => $label) {
        $segments .= '<button type="button" class="pg-seg-item" aria-pressed="false" data-delivery-reason="' . (int) $code . '">' . h($label) . '</button>';
    }

    return '<div class="form-field mb-5"><div class="pg-field-label" id="' . h($id) . '_reason_label">' . h(lang('Why is it closed?')) . '</div>'
        . '<span class="pg-seg" role="group" aria-labelledby="' . h($id) . '_reason_label" data-delivery-reasons>' . $segments . '</span>'
        . '<input type="hidden" name="close" value="0"' . $owner . ' data-delivery-reason-value>'
        . '<div class="pg-field-help pg-field-error" data-delivery-reason-error hidden>' . h(lang('Choose why it is closed.')) . '</div></div>'
        . pg_delivery_problem_field($id . '_note', lang('Closing note'), '<input type="text" class="pg-input" id="' . h($id) . '_note" name="close_note" maxlength="' . PG_DELIVERY_PROBLEMS_NOTE . '"' . $owner . '>', lang('Added to the problem\'s note.'));
}

/**
 * A problem's drawer: the parcel number, the kind pill and the days at the
 * head; the order's facts as a label and value list; "What happened" as
 * gathered rows with the carrier's tracking page; Assignee and Note; Save
 * and "Close the problem" at the foot. A closed problem shows its facts as
 * plain text and its reason in place of the foot's buttons.
 *
 * @param array      $problem pg_delivery_problem_get()
 * @param array      $filters the list's filters, kept after a save
 * @param array|null $summary pg_delivery_tower_summary()
 * @param int        $now
 * @return string
 */
function pg_delivery_problem_drawer(array $problem, array $filters, $summary, $now)
{
    $id = (int) $problem['id'];
    $closed = ((int) $problem['closed_at'] > 0);
    $parcel = ($problem['parcel_id'] !== null) ? $problem : null;
    $number = ($parcel !== null) ? (string) $problem['number'] : lang('Parcel removed');
    $view = ($parcel !== null) ? pg_shipment_view($problem, (string) ($problem['shipping_method_code'] ?? ''), pg_shipping_default_carrier()) : array('carrier_name' => '', 'url' => '');
    $date = function ($at) {
        return ((int) $at > 0) ? h(get_absolute_time(array('timestamp' => (int) $at, 'type' => 'date', 'format' => 'plain_text'))) : '–';
    };

    // The order's facts.
    $customer = trim((string) ($problem['billing_first_name'] ?? '') . ' ' . (string) ($problem['billing_last_name'] ?? ''));
    $transit = ($parcel !== null) ? pg_delivery_problem_transit_days($problem, $now) : null;
    $usual = ($parcel !== null) ? pg_delivery_problem_usual_days($summary, (string) $problem['carrier'], (string) ($problem['state'] ?? ''), (string) ($problem['city'] ?? '')) : null;
    $estimate = (string) ($problem['delivery_date'] ?? '');
    $estimate = (($estimate !== '') && ($estimate !== '0000-00-00') && (strtotime($estimate) !== false)) ? $date(strtotime($estimate)) : h(lang('None given'));

    $facts = '<dl class="pg-kv mb-5">'
        . '<dt>' . h(lang('Order')) . '</dt><dd>' . (((string) ($problem['order_number'] ?? '') !== '') ? '<a href="view_order.php?id=' . (int) $problem['order_id'] . '">' . h((string) $problem['order_number']) . '</a>' : '–') . '</dd>'
        . '<dt>' . h(lang('Customer')) . '</dt><dd>' . (($customer !== '') ? h($customer) : '–') . '</dd>'
        . '<dt>' . h(lang('Carrier')) . '</dt><dd>' . ((($view['carrier_name'] ?? '') !== '') ? h($view['carrier_name']) : '–') . '</dd>'
        . '<dt>' . h(lang('Shipped on')) . '</dt><dd>' . $date($problem['shipped_at'] ?? 0) . '</dd>'
        . '<dt>' . h(lang('Days in transit')) . '</dt><dd>' . (($transit !== null) ? h(pg_delivery_problem_days_text($transit)) : '–')
            . (($usual !== null) ? '<span class="pg-kv-note">' . h(lang(array('string' => 'Usual days: {var:1}', 'vars' => array(pg_format_number($usual, ((float) $usual === floor($usual)) ? 0 : 1))))) . '</span>' : '') . '</dd>'
        . '<dt>' . h(lang('Estimate at checkout')) . '</dt><dd>' . $estimate . '</dd>'
        . ($closed ? '<dt>' . h(lang('Assignee')) . '</dt><dd>' . h(((string) ($problem['assignee'] ?? '') !== '') ? (string) $problem['assignee'] : lang('Nobody')) . '</dd>'
            . '<dt>' . h(lang('Note')) . '</dt><dd class="text-break">' . h(((string) $problem['note'] !== '') ? (string) $problem['note'] : '–') . '</dd>' : '')
        . '</dl>';

    // What happened, newest first, then the carrier's page.
    $close_labels = array();
    $problems = ($parcel !== null) ? pg_delivery_problems_of_parcel((int) $problem['shipment_id']) : array($problem);

    foreach ($problems as $item) {
        $close_labels[(int) $item['id']] = pg_delivery_problem_close_label($item, $parcel, (string) ($problem['order_status'] ?? ''));
    }

    $lines = '';

    foreach (pg_delivery_problem_events($parcel, $problems, $close_labels) as $event) {
        $lines .= '<div class="pg-gather-line"><b class="pg-tabular">' . $date($event['at']) . '</b><span>' . h($event['text']) . '</span></div>';
    }

    $facts .= '<h6 class="pg-title-status">' . h(lang('What happened')) . '</h6>'
        . (($lines !== '') ? '<div class="pg-gather mb-3">' . $lines . '</div>' : '<p class="pg-text-2">' . h(lang('Nothing is known about this parcel yet.')) . '</p>')
        . ((($view['url'] ?? '') !== '') ? '<p class="mb-5"><a href="' . h($view['url']) . '" target="_blank" rel="noopener noreferrer">' . h(lang('Open the carrier\'s tracking page')) . ' <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a></p>' : '');

    $body = '<input type="hidden" name="problem" value="' . $id . '">'
        . '<input type="hidden" name="back" value="' . h(pg_delivery_problem_url($filters)) . '">'
        . $facts;

    if ($closed) {
        $reason = pg_delivery_problem_close_label($problem, $parcel, (string) ($problem['order_status'] ?? ''));
        $foot = '<span class="pg-text-2">' . h(lang(array('string' => 'Closed: {var:1}', 'vars' => array($reason)))) . ' · ' . $date($problem['closed_at']) . '</span>'
            . '<span class="flex-grow-1"></span><button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Close')) . '</button>';
    } else {
        $body .= pg_delivery_problem_field('delivery_problem_assignee', lang('Assignee'), pg_delivery_problem_assignee_select('delivery_problem_assignee', pg_delivery_problem_assignees(), (int) $problem['assigned_to']))
            . pg_delivery_problem_field('delivery_problem_note', lang('Note'), '<input type="text" class="pg-input" id="delivery_problem_note" name="note" maxlength="' . PG_DELIVERY_PROBLEMS_NOTE . '" value="' . h((string) $problem['note']) . '" aria-describedby="delivery_problem_note_help">', lang('Only staff see the note.'))
            . '<div class="border-top pt-5" data-delivery-close-panel hidden>' . pg_delivery_problem_close_fields('delivery_problem_close') . '</div>';

        $foot = '<button type="button" class="pg-button pg-button-quiet" data-delivery-close-open>' . h(lang('Close the problem')) . '</button>'
            . '<button type="button" class="pg-button pg-button-quiet" data-delivery-close-back hidden>' . h(lang('Back')) . '</button>'
            . '<span class="flex-grow-1"></span>'
            . '<button type="submit" name="action" value="save" class="pg-button pg-button-fill" data-delivery-save>' . h(lang('Save')) . '</button>'
            . '<button type="submit" name="action" value="close" class="pg-button pg-button-fill" data-delivery-close-submit hidden>' . h(lang('Close')) . '</button>';
    }

    $head = pg_delivery_problem_kind_pill((int) $problem['kind'], $closed) . ' <span class="pg-tabular">' . h(pg_delivery_problem_days_text(pg_delivery_problem_days($problem, $now))) . '</span>';

    return pg_delivery_problem_drawer_frame('delivery_problem', $number, $head, $body, $foot, array('open' => true));
}

/**
 * The two drawers the bulk bar opens: "Assign to…" and "Close…", their
 * fields in the list's form.
 *
 * @param string $form the list's form id
 * @return string
 */
function pg_delivery_problem_bulk_drawers($form)
{
    $assign = pg_delivery_problem_drawer_frame('delivery_problems_assign', lang('Assign the chosen problems'), h(lang('The chosen open problems go to this person.')),
        pg_delivery_problem_field('delivery_problems_assign_to', lang('Assignee'), pg_delivery_problem_assignee_select('delivery_problems_assign_to', pg_delivery_problem_assignees(), 0, $form)),
        '<span class="flex-grow-1"></span><button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Cancel')) . '</button>'
            . '<button type="submit" form="' . h($form) . '" name="action" value="assign" class="pg-button pg-button-fill">' . h(lang('Assign')) . '</button>',
        array('form' => $form));

    $close = pg_delivery_problem_drawer_frame('delivery_problems_close', lang('Close the chosen problems'), h(lang('The chosen open problems are closed with this reason.')),
        pg_delivery_problem_close_fields('delivery_problems_close', $form),
        '<span class="flex-grow-1"></span><button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Cancel')) . '</button>'
            . '<button type="submit" form="' . h($form) . '" name="action" value="close" class="pg-button pg-button-fill" data-delivery-close-submit>' . h(lang('Close')) . '</button>',
        array('form' => $form));

    return $assign . $close;
}
