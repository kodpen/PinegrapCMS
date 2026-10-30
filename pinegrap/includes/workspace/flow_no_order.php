<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the start box "a customer has not ordered for N days". Its
 * anchor (flow_anchors.php) runs every hour; each run looks for customers
 * whose last order is N days old, and starts one run of the flow for each,
 * within the starts the flow has left in the hour. The run's start key names
 * the customer and their last order, so a customer starts the flow once per
 * last order: a new order makes a new key.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The days a new box waits for, and the most it may wait.
define('WS_FLOW_NO_ORDER_DAYS', 30);
define('WS_FLOW_NO_ORDER_DAYS_MAX', 3650);

// How long after the threshold a customer is still started: under the time
// ended runs are kept (WS_FLOW_RUNS_KEEP, 30 days), so the run a customer
// started is still there to meet its start key while they are looked at.
define('WS_FLOW_NO_ORDER_WINDOW', 2160000);

// Customers read a page at a time, and at most this many pages an hour.
define('WS_FLOW_NO_ORDER_PAGE', 500);
define('WS_FLOW_NO_ORDER_PAGES', 10);

/**
 * The hourly rule of the box's anchor: every hour from the moment the flow
 * is published. Pure.
 *
 * @param int $now
 * @return array a scheduled action's at rule
 */
function ws_flow_no_order_rule($now)
{
    return array('type' => 'at', 'date' => date('Y-m-d', (int) $now), 'time' => date('H:i', (int) $now), 'repeat' => 'hourly', 'every' => 1);
}

/**
 * Why somebody may not have a flow started by customers who stopped
 * ordering, '' when they may: it reads orders and contacts, so both rights,
 * as the order and contact events ask (ws_events_catalog()). Pure.
 *
 * @param array $viewer ws_viewer()
 * @return string
 */
function ws_flow_no_order_refusal($viewer)
{
    if (empty($viewer['ecommerce']) || empty($viewer['contacts'])) {
        return lang('Only those who may see orders and contacts can start a flow on customers who stopped ordering.');
    }

    return '';
}

/**
 * The start key of a run about a customer and their last order: the same
 * customer and order start the flow once, a new order makes a new key. Pure.
 *
 * @param int $contact_id
 * @param int $order_id
 * @return string
 */
function ws_flow_no_order_start_key($contact_id, $order_id)
{
    return 'noorder:' . (int) $contact_id . ':' . (int) $order_id;
}

/**
 * Has a customer whose last order was placed then crossed the threshold
 * now, within the window it is looked at? Never for one who has not
 * ordered. Pure.
 *
 * @param int $order_date the last order's order_date
 * @param int $days
 * @param int $now
 * @return bool
 */
function ws_flow_no_order_crossed($order_date, $days, $now)
{
    $at = (int) $order_date + max(1, (int) $days) * 86400;

    return ((int) $order_date > 0) && ($at <= (int) $now) && ($at > (int) $now - WS_FLOW_NO_ORDER_WINDOW);
}

/**
 * The last order of each customer among some orders: the latest placed, of
 * two at the same moment the higher id. Pure.
 *
 * @param array[] $orders contact_id, id, order_number, order_date
 * @return array contact id => order
 */
function ws_flow_no_order_last($orders)
{
    $last = array();

    foreach ((array) $orders as $order) {
        $contact_id = (int) ($order['contact_id'] ?? 0);

        if ($contact_id <= 0) {
            continue;
        }

        $kept = $last[$contact_id] ?? null;

        if (!$kept || ((int) $order['order_date'] > (int) $kept['order_date'])
            || (((int) $order['order_date'] === (int) $kept['order_date']) && ((int) $order['id'] > (int) $kept['id']))) {
            $last[$contact_id] = $order;
        }
    }

    return $last;
}

/**
 * The customers to start runs for: their last order crossed the threshold
 * (ws_flow_no_order_crossed()), they placed none since ($later), no run
 * has their key yet ($started); the longest waiting first, at most $budget.
 * Pure.
 *
 * @param array    $last    ws_flow_no_order_last()
 * @param int[]    $later   customers with an order after the threshold
 * @param string[] $started start keys the flow's runs already have
 * @param int      $days
 * @param int      $now
 * @param int      $budget
 * @return array[] contact_id, order_id, order_number, order_date, days_since, start_key
 */
function ws_flow_no_order_pick($last, $later, $started, $days, $now, $budget)
{
    $later = array_flip(array_map('intval', (array) $later));
    $started = array_flip(array_map('strval', (array) $started));
    $out = array();

    foreach ((array) $last as $contact_id => $order) {
        $key = ws_flow_no_order_start_key($contact_id, $order['id']);

        if (!isset($later[(int) $contact_id]) && !isset($started[$key]) && ws_flow_no_order_crossed($order['order_date'], $days, $now)) {
            $out[] = array(
                'contact_id'   => (int) $contact_id,
                'order_id'     => (int) $order['id'],
                'order_number' => (string) ($order['order_number'] ?? ''),
                'order_date'   => (int) $order['order_date'],
                'days_since'   => (int) floor(((int) $now - (int) $order['order_date']) / 86400),
                'start_key'    => $key,
            );
        }
    }

    usort($out, function ($a, $b) {
        return ($a['order_date'] === $b['order_date']) ? ($a['contact_id'] - $b['contact_id']) : ($a['order_date'] - $b['order_date']);
    });

    return array_slice($out, 0, max(0, (int) $budget));
}

/**
 * How many runs a scan may still start: what the flow has left of its open
 * runs and of its starts in the hour (ws_flow_start_refusal()), and for a
 * role-3 owner what is left of the starts of all their flows. Pure.
 *
 * @param int      $open          the flow's open runs
 * @param int      $recent        the flow's starts in the last hour
 * @param int|null $member_recent a role-3 owner's starts in the last hour, null for staff
 * @return int
 */
function ws_flow_no_order_budget($open, $recent, $member_recent)
{
    $left = min(WS_FLOW_MAX_OPEN_RUNS - (int) $open, WS_FLOW_STARTS_HOURLY - (int) $recent);

    if ($member_recent !== null) {
        $left = min($left, WS_FLOW_MEMBER_STARTS_HOURLY - (int) $member_recent);
    }

    return max(0, $left);
}

/**
 * The values the box gives {{trigger.<field>}}. Pure.
 *
 * @return array[] key, type, label
 */
function ws_flow_no_order_outputs()
{
    return array(
        array('key' => 'contact_id', 'type' => 'number', 'label' => lang('Customer')),
        array('key' => 'name', 'type' => 'text', 'label' => lang('The name of the customer')),
        array('key' => 'email', 'type' => 'text', 'label' => lang('E-mail')),
        array('key' => 'last_order_id', 'type' => 'number', 'label' => lang('Last order')),
        array('key' => 'last_order_number', 'type' => 'text', 'label' => lang('Number of the last order')),
        array('key' => 'last_order_date', 'type' => 'text', 'label' => lang('Date of the last order')),
        array('key' => 'days_since', 'type' => 'number', 'label' => lang('Days since the last order')),
    );
}

/**
 * How the box's start reads. Pure.
 *
 * @param array $config ws_flow_anchor_normalize('no_order')
 * @return string
 */
function ws_flow_no_order_said($config)
{
    return lang(array('string' => 'A customer has not ordered for {var:1} days', 'vars' => (int) ($config['days'] ?? WS_FLOW_NO_ORDER_DAYS)));
}

/**
 * The hourly run of the box's anchor: the customers whose last order crossed
 * the threshold, a page at a time, each started once (its start key) as a
 * run about them, with the owner's rights asked again (both rights, the
 * contacts they may open), within the starts the flow has left.
 *
 * @param array $viewer  the flow's owner, ws_viewer()
 * @param array $flow
 * @param array $version the live version
 * @param array $trigger the start box
 * @param array $fill    what ws_scheduled_execute() hands its step
 * @return array ok, text, message_id, run_id (0: the runs are many)
 */
function ws_flow_no_order_scan($viewer, $flow, $version, $trigger, $fill)
{
    $refused = ws_flow_no_order_refusal($viewer);

    if ($refused !== '') {
        return array('ok' => false, 'text' => $refused, 'message_id' => 0, 'run_id' => 0);
    }

    $flow_id = (int) $flow['id'];
    $days = (int) ws_flow_anchor_normalize('no_order', $trigger['config'] ?? array())['days'];
    $now = time();
    $cut = $now - $days * 86400;
    $from = $cut - WS_FLOW_NO_ORDER_WINDOW;
    $placed = "contact_id > 0 AND status IN ('complete', 'exported')";

    $open = (int) db_value("SELECT COUNT(*) FROM ws_flow_runs WHERE flow_id = '" . $flow_id . "' AND status IN ('running', 'waiting')");
    $recent = (int) db_value("SELECT COUNT(*) FROM ws_flow_runs WHERE flow_id = '" . $flow_id . "' AND started_at > '" . ($now - 3600) . "'");
    $member = (ws_flow_builder_kind($viewer) === 'member')
        ? (int) db_value("SELECT COUNT(*) FROM ws_flow_runs WHERE actor_id = '" . (int) $viewer['id'] . "' AND started_at > '" . ($now - 3600) . "'")
        : null;
    $budget = ws_flow_no_order_budget($open, $recent, $member);
    $started = 0;

    for ($page = 0; ($page < WS_FLOW_NO_ORDER_PAGES) && ($budget > $started); $page++) {
        // The customers whose latest order in the window is the oldest first.
        $ids = array_map('intval', (array) db_values("SELECT contact_id FROM orders
            WHERE " . $placed . " AND order_date > '" . $from . "' AND order_date <= '" . $cut . "'
            GROUP BY contact_id ORDER BY MAX(order_date), contact_id
            LIMIT " . WS_FLOW_NO_ORDER_PAGE . " OFFSET " . ($page * WS_FLOW_NO_ORDER_PAGE)));

        if (empty($ids)) {
            break;
        }

        $read = count($ids);
        $ids = ws_viewer_contacts_open($viewer, $ids);
        $in = "'" . implode("','", $ids) . "'";

        if (!empty($ids)) {
            $later = db_values("SELECT DISTINCT contact_id FROM orders WHERE " . $placed . " AND contact_id IN (" . $in . ") AND order_date > '" . $cut . "'");
            $last = ws_flow_no_order_last(db_items("SELECT contact_id, id, order_number, order_date FROM orders
                WHERE " . $placed . " AND contact_id IN (" . $in . ") AND order_date > '" . $from . "' AND order_date <= '" . $cut . "'"));
            $keys = array();

            foreach ($last as $contact_id => $order) {
                $keys[] = e(ws_flow_no_order_start_key($contact_id, $order['id']));
            }

            $known = empty($keys) ? array() : db_values("SELECT start_key FROM ws_flow_runs WHERE flow_id = '" . $flow_id . "' AND start_key IN ('" . implode("','", $keys) . "')");

            foreach (ws_flow_no_order_pick($last, (array) $later, (array) $known, $days, $now, $budget - $started) as $pick) {
                if (ws_flow_no_order_start($flow, $version, $trigger, $fill, $pick) > 0) {
                    $started++;
                }
            }
        }

        if ($read < WS_FLOW_NO_ORDER_PAGE) {
            break;
        }
    }

    return array('ok' => true, 'text' => lang(array('string' => 'flow “{var:1}” started for {var:2} customers', 'vars' => array($flow['name'], $started))), 'message_id' => 0, 'run_id' => 0);
}

/**
 * Starts one run about a customer: as the flow's owner, about the contact
 * (subject contact), its start key the customer's and their last order's,
 * the customer in its context for {{trigger.<field>}}. A key already taken
 * (two scans at the same moment) starts nothing.
 *
 * @param array $flow
 * @param array $version
 * @param array $trigger
 * @param array $fill
 * @param array $pick    ws_flow_no_order_pick()
 * @return int the run, 0 for none
 */
function ws_flow_no_order_start($flow, $version, $trigger, $fill, $pick)
{
    $row = db_item("SELECT id, first_name, last_name, company, email_address FROM contacts WHERE id = '" . (int) $pick['contact_id'] . "'");

    if (!$row) {
        return 0;
    }

    $name = pg_contact_display_name($row['first_name'], $row['last_name']);

    foreach (array('company', 'email_address') as $column) {
        $name = ($name !== '') ? $name : trim((string) $row[$column]);
    }

    $context = ws_flow_anchor_context($flow, $trigger, $fill);
    $context['subject_name'] = $name;
    $context['customer'] = array(
        'contact_id'        => (int) $pick['contact_id'],
        'name'              => $name,
        'email'             => (string) $row['email_address'],
        'last_order_id'     => (int) $pick['order_id'],
        'last_order_number' => (string) $pick['order_number'],
        'last_order_date'   => date('Y-m-d', (int) $pick['order_date']),
        'days_since'        => (int) $pick['days_since'],
    );
    $now = time();
    $flow_id = (int) $flow['id'];

    // Not db(): a key taken meanwhile is an answer here.
    $written = db_q("INSERT INTO ws_flow_runs (flow_id, version_id, status, started_by, actor_id, start_key,
            subject_type, subject_id, context, depth, boxes_run, started_at, updated_at, finished_at)
        SELECT ?, ?, 'running', ?, ?, ?, 'contact', ?, ?, ?, 0, ?, ?, 0
        FROM ws_flows WHERE id = ? AND status = 'live'",
        array($flow_id, (int) $version['id'], (int) $flow['owner_id'], (int) $flow['owner_id'], (string) $pick['start_key'], (int) $pick['contact_id'],
            (string) json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int) ($fill['depth'] ?? 0), $now, $now, $flow_id));

    if (!$written || ((int) mysqli_affected_rows(db::$con) !== 1)) {
        return 0;
    }

    $run_id = (int) mysqli_insert_id(db::$con);

    db("INSERT IGNORE INTO ws_flow_run_steps (run_id, node_id, pass, status, outcome, attempt, ready_at, claimed_at, finished_at, result)
        VALUES ('" . $run_id . "', '" . e((string) $trigger['id']) . "', 0, 'done', 'next', 1, '" . $now . "', '" . $now . "', '" . $now . "',
            '" . e(ws_flow_result_pack(lang(array('string' => 'Started on its own for {var:1}.', 'vars' => $name)))) . "')");

    ws_flow_run_refresh($run_id);

    return $run_id;
}
