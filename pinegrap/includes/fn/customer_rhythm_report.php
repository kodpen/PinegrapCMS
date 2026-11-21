<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Customer rhythm as it is read: the Rhythm part of the Customers Report
 * (customers and expected value per stage, the list by stage and its CSV,
 * products with their rhythm, the reminders and who came back after them),
 * the contact card's part and the settings. Who came back after a reminder
 * is worked out by the nightly pass (customer_rhythm_pass.php) with the
 * pure rules here: an order of the same customer within 14 days of the
 * reminder, counted in the reminder's month, apart for the held-back group.
 *
 * Names are read when shown, from the contact or the last order, and only
 * for a reader who may read contacts; the table keeps none.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Days after a reminder within which an order counts as coming back.
define('PG_CUSTOMER_RHYTHM_CAME_BACK_DAYS', 14);

// Products listed with their rhythm, most repeat purchases first.
define('PG_CUSTOMER_RHYTHM_PRODUCTS_SHOWN', 50);

// Customers read per query by the list's CSV.
define('PG_CUSTOMER_RHYTHM_CSV_BATCH', 1000);

/**
 * The order that counts as the customer coming back after their last
 * reminder (H13): the first of their own orders (not a marketplace order)
 * placed after the reminder and at most 14 days after it. Null when there
 * was no reminder, one already counted, or no such order. Pure.
 *
 * @param array $orders              pg_customer_rhythm_pass_orders()
 * @param int   $reminded_at
 * @param int   $came_back_order_id  the order already counted, 0 for none
 * @return array|null  id, amount (minor units), at
 */
function pg_customer_rhythm_came_back($orders, $reminded_at, $came_back_order_id)
{
    $reminded_at = (int) $reminded_at;

    if (($reminded_at <= 0) || ((int) $came_back_order_id > 0)) {
        return null;
    }

    $end = $reminded_at + (PG_CUSTOMER_RHYTHM_CAME_BACK_DAYS * 86400);
    $found = null;

    foreach ((array) $orders as $order) {
        $time = (int) $order['order_date'];

        if ((isset($order['type']) && ((string) $order['type'] === 'marketplace')) || ($time <= $reminded_at) || ($time > $end)) {
            continue;
        }

        if (($found === null) || ($time < $found['at']) || (($time === $found['at']) && ((int) $order['id'] < $found['id']))) {
            $found = array('id' => (int) $order['id'], 'amount' => (int) pg_customer_value_order_amount($order), 'at' => $time);
        }
    }

    return $found;
}

/**
 * Counts a customer who came back into the month of the reminder: came
 * back and their order's value, told and held back apart. Pure.
 *
 * @param array $months  'YYYY-MM' => array(back told, value told, back held, value held)
 * @param int   $reminded_at
 * @param bool  $held
 * @param int   $amount
 * @return void
 */
function pg_customer_rhythm_return_count(&$months, $reminded_at, $held, $amount)
{
    $month = gmdate('Y-m', (int) $reminded_at);

    if (!isset($months[$month])) {
        $months[$month] = array(0, 0, 0, 0);
    }

    $at = $held ? 2 : 0;
    $months[$month][$at] += 1;
    $months[$month][$at + 1] += max(0, (int) $amount);
}

/**
 * The return counts kept by the summary: the earlier ones and this pass's
 * added up, the last 24 months. Pure.
 *
 * @param array|null $old
 * @param array      $new
 * @return array  'YYYY-MM' => array(back told, value told, back held, value held), oldest first
 */
function pg_customer_rhythm_returns_merge($old, $new)
{
    $months = array();

    foreach (array((array) $old, (array) $new) as $part) {
        foreach ($part as $month => $row) {
            if (!preg_match('/^\d{4}-\d{2}$/', (string) $month) || !is_array($row)) {
                continue;
            }

            if (!isset($months[$month])) {
                $months[$month] = array(0, 0, 0, 0);
            }

            for ($i = 0; $i < 4; $i++) {
                $months[$month][$i] += (int) (isset($row[$i]) ? $row[$i] : 0);
            }
        }
    }

    ksort($months);

    return array_slice($months, -PG_CUSTOMER_RHYTHM_MONTHS_KEPT, null, true);
}

/**
 * The statement marking the orders counted as coming back, only on rows
 * with none counted yet. Pure.
 *
 * @param array $back  customer key => pg_customer_rhythm_came_back()
 * @return array  array(sql, params); sql '' for none
 */
function pg_customer_rhythm_came_back_query($back)
{
    if (!$back) {
        return array('', array());
    }

    $cases = array();
    $params = array();

    foreach ($back as $key => $came) {
        $cases[] = 'WHEN ? THEN ?';
        $params[] = (string) $key;
        $params[] = (int) $came['id'];
    }

    $params = array_merge($params, array_map('strval', array_keys($back)));

    return array(
        'UPDATE customer_rhythm SET came_back_order_id = CASE customer_key ' . implode(' ', $cases) . ' ELSE came_back_order_id END'
            . ' WHERE (came_back_order_id = 0) AND customer_key IN (' . implode(', ', array_fill(0, count($back), '?')) . ')',
        $params,
    );
}

/**
 * A share as a whole per cent, or null when nothing was sent. Pure.
 *
 * @param int $part
 * @param int $whole
 * @return int|null
 */
function pg_customer_rhythm_rate($part, $whole)
{
    return ((int) $whole > 0) ? (int) round(100 * (int) $part / (int) $whole) : null;
}

/**
 * The reminders table's rows (H13), newest month first: reminders told and
 * how many came back with their value; the held-back group beside them.
 * Pure.
 *
 * @param array|null $summary  pg_customer_rhythm_summary()
 * @param int        $limit    months
 * @return array  month, told, back, value, rate, held, held_back, held_value, held_rate
 */
function pg_customer_rhythm_return_rows($summary, $limit = 12)
{
    $sent = (is_array($summary) && isset($summary['reminders'])) ? (array) $summary['reminders'] : array();
    $returns = (is_array($summary) && isset($summary['returns'])) ? (array) $summary['returns'] : array();
    $months = array_unique(array_merge(array_keys($sent), array_keys($returns)));
    rsort($months, SORT_STRING);
    $rows = array();

    foreach (array_slice($months, 0, max(1, (int) $limit)) as $month) {
        $s = isset($sent[$month]) ? (array) $sent[$month] : array();
        $r = isset($returns[$month]) ? (array) $returns[$month] : array();
        $told = (int) (isset($s[0]) ? $s[0] : 0);
        $held = (int) (isset($s[1]) ? $s[1] : 0);
        $back = (int) (isset($r[0]) ? $r[0] : 0);
        $held_back = (int) (isset($r[2]) ? $r[2] : 0);

        $rows[] = array(
            'month' => (string) $month,
            'told' => $told,
            'back' => $back,
            'value' => (int) (isset($r[1]) ? $r[1] : 0),
            'rate' => pg_customer_rhythm_rate($back, $told),
            'held' => $held,
            'held_back' => $held_back,
            'held_value' => (int) (isset($r[3]) ? $r[3] : 0),
            'held_rate' => pg_customer_rhythm_rate($held_back, $held),
        );
    }

    return $rows;
}

/**
 * The stages as the screens name them, in their order.
 *
 * @return array  stage name => label
 */
function pg_customer_rhythm_stage_labels()
{
    return array(
        'regular' => lang('Regular'),
        'due' => lang('Due to reorder'),
        'late' => lang('Late'),
        'slipping' => lang('Slipping away'),
        'lost' => lang('Lost'),
        'won_back' => lang('Won back'),
    );
}

/**
 * What each stage means, in the words of its bounds.
 *
 * @return array  stage name => text
 */
function pg_customer_rhythm_stage_bounds()
{
    return array(
        'regular' => lang('Less than their own interval since the last order'),
        'due' => lang('One to two intervals since the last order'),
        'late' => lang('Two to three intervals since the last order'),
        'slipping' => lang('Three intervals or more, still 10% or more likely to order'),
        'lost' => lang('Three intervals or more, under 10% likely to order'),
        'won_back' => lang('Ordered again after Late, Slipping away or Lost'),
    );
}

/**
 * The tone of a stage's pill. Pure.
 *
 * @param string $stage
 * @return string
 */
function pg_customer_rhythm_stage_tone($stage)
{
    $tones = array('regular' => 'teal', 'due' => 'blue', 'late' => 'amber', 'slipping' => 'rose', 'lost' => 'gray', 'won_back' => 'violet');

    return isset($tones[(string) $stage]) ? $tones[(string) $stage] : 'gray';
}

/**
 * A stage asked for in the address: one of the stage names, else ''. Pure.
 *
 * @param mixed $value
 * @return string
 */
function pg_customer_rhythm_stage_pick($value)
{
    $value = is_scalar($value) ? (string) $value : '';

    return (($value !== '') && (pg_customer_rhythm_stage_code($value) > 0)) ? $value : '';
}

/**
 * The stages table's rows from the counts per stage code: every stage in
 * its order with its customers and expected 12-month value; customers
 * with no stage yet (the store has no interval to judge them by) last,
 * only when there are any. Pure.
 *
 * @param array $totals  stage code => array(customers, expected value)
 * @return array  stage, customers, value
 */
function pg_customer_rhythm_stage_rows($totals)
{
    $rows = array();

    foreach (array_keys(pg_customer_rhythm_stage_codes()) as $stage) {
        $code = pg_customer_rhythm_stage_code($stage);
        $row = isset($totals[$code]) ? $totals[$code] : array(0, 0);

        if (($stage === '') && ((int) $row[0] === 0)) {
            continue;
        }

        $rows[] = array('stage' => (string) $stage, 'customers' => (int) $row[0], 'value' => (int) $row[1]);
    }

    // The customers with no stage come after the six stages.
    usort($rows, function ($a, $b) {
        return (($a['stage'] === '') ? 1 : 0) - (($b['stage'] === '') ? 1 : 0);
    });

    return $rows;
}

/**
 * The customers and their expected 12-month value per stage code, read
 * from the table.
 *
 * @return array  stage code => array(customers, expected value)
 */
function pg_customer_rhythm_stage_totals()
{
    $totals = array();

    foreach ((array) db_items("SELECT stage, COUNT(*) AS customers, SUM(expected_value) AS value FROM customer_rhythm GROUP BY stage") as $row) {
        $totals[(int) $row['stage']] = array((int) $row['customers'], (int) $row['value']);
    }

    return $totals;
}

/**
 * The products with their rhythm, most repeat purchases first. Pure.
 *
 * @param array|null $summary  pg_customer_rhythm_summary()
 * @param array      $names    product_id => array(name, short_description)
 * @param int        $limit
 * @return array  id, name, days_per_unit, pairs, customers, from_group
 */
function pg_customer_rhythm_product_rows($summary, $names, $limit = PG_CUSTOMER_RHYTHM_PRODUCTS_SHOWN)
{
    $products = (is_array($summary) && isset($summary['products'])) ? (array) $summary['products'] : array();
    $rows = array();

    foreach ($products as $id => $rhythm) {
        $id = (int) $id;
        $name = isset($names[$id]) ? (((string) $names[$id][1] !== '') ? (string) $names[$id][1] : (string) $names[$id][0]) : '';
        $rows[] = array('id' => $id, 'name' => $name, 'days_per_unit' => (float) $rhythm[0], 'pairs' => (int) $rhythm[1], 'customers' => (int) $rhythm[2], 'from_group' => !empty($rhythm[3]));
    }

    usort($rows, function ($a, $b) {
        return ($b['pairs'] - $a['pairs']) ?: ($a['id'] - $b['id']);
    });

    return array_slice($rows, 0, max(0, (int) $limit));
}

/**
 * Whether a user may read the Rhythm part: as the Customers Report, the
 * commerce reports right. Pure.
 *
 * @param array $user
 * @return bool
 */
function pg_customer_rhythm_may_read($user)
{
    return is_array($user) && isset($user['role']) && (((int) $user['role'] < 3) || !empty($user['manage_ecommerce_reports']));
}

/**
 * A customer's name as shown: the contact's, else the billing name of the
 * last order, else its company. Pure.
 *
 * @param array $record  contact_first_name, contact_last_name,
 *                       billing_first_name, billing_last_name, billing_company
 * @return string
 */
function pg_customer_rhythm_name($record)
{
    $contact = trim((string) ($record['contact_first_name'] ?? '') . ' ' . (string) ($record['contact_last_name'] ?? ''));

    if ($contact !== '') {
        return $contact;
    }

    $billing = trim((string) ($record['billing_first_name'] ?? '') . ' ' . (string) ($record['billing_last_name'] ?? ''));

    return ($billing !== '') ? $billing : trim((string) ($record['billing_company'] ?? ''));
}

/**
 * Whole days since the last order at $now. Pure.
 *
 * @param array $row  last_order_at
 * @param int   $now
 * @return int
 */
function pg_customer_rhythm_days_since($row, $now)
{
    $last = (int) $row['last_order_at'];

    return ($last > 0) ? (int) max(0, floor(((int) $now - $last) / 86400)) : 0;
}

/**
 * The list's columns; the keys are its sort codes.
 *
 * @param bool $names  whether the reader may see names
 * @return array  key => spec, as pg_list_table_columns() takes them
 */
function pg_customer_rhythm_list_columns($names)
{
    $columns = array();

    if ($names) {
        $columns['customer'] = array('heading' => lang('Customer'), 'width' => '14rem', 'kind' => 'link', 'sortable' => false);
    }

    return $columns + array(
        'stage' => array('heading' => lang('Stage'), 'width' => '9rem', 'kind' => 'badge', 'sort' => 'customer_rhythm.stage'),
        'expected' => array('heading' => lang('Expected 12 months'), 'width' => '9rem', 'kind' => 'money', 'sort' => 'customer_rhythm.expected_value'),
        'chance' => array('heading' => lang('Chance'), 'width' => '6rem', 'kind' => 'number', 'sort' => 'customer_rhythm.chance'),
        'orders' => array('heading' => lang('Orders'), 'width' => '6rem', 'kind' => 'number', 'sort' => 'customer_rhythm.orders'),
        'last' => array('heading' => lang('Last order'), 'width' => '9rem', 'kind' => 'date', 'sort' => 'customer_rhythm.last_order_at'),
        'interval' => array('heading' => lang('Own interval'), 'width' => '8rem', 'kind' => 'number', 'sort' => 'customer_rhythm.interval_days'),
        'next_due' => array('heading' => lang('Next due day'), 'width' => '9rem', 'kind' => 'date', 'sort' => 'customer_rhythm.next_due_at'),
    );
}

/**
 * The list's sort as kept for the screen.
 *
 * @return array  pg_list_state()'s levels
 */
function pg_customer_rhythm_list_levels()
{
    $columns = array(
        'expected' => lang('Expected 12 months'),
        'chance' => lang('Chance'),
        'orders' => lang('Orders'),
        'last' => lang('Last order'),
        'interval' => lang('Own interval'),
        'next_due' => lang('Next due day'),
        'stage' => lang('Stage'),
    );

    return pg_list_state('customer_rhythm', $columns, array('sort' => 'expected', 'order' => 'desc'))['sorts'];
}

/**
 * One customer of the list. Pure.
 *
 * @param array $record  a customer_rhythm row with the names read beside it
 * @param bool  $names
 * @param int   $now
 * @return array  id, class, cells
 */
function pg_customer_rhythm_list_row($record, $names, $now)
{
    $labels = pg_customer_rhythm_stage_labels();
    $stage = pg_customer_rhythm_stage_name($record['stage']);
    $symbol = defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : '';
    $date = function ($time) {
        return ((int) $time > 0) ? h(get_absolute_time(array('timestamp' => (int) $time, 'type' => 'date', 'format' => 'plain_text'))) : '<span class="fg-secondary">—</span>';
    };
    $cells = array();

    if ($names) {
        $name = pg_customer_rhythm_name($record);
        $name = ($name !== '') ? h($name) : '<span class="fg-secondary">' . h(lang('Name not shown')) . '</span>';
        $cells['customer'] = pg_list_cell(((int) $record['contact_id'] > 0) ? '<a href="edit_contact.php?id=' . (int) $record['contact_id'] . '">' . $name . '</a>' : $name);
    }

    $interval = (int) $record['interval_days'];

    return array('id' => (string) (int) $record['id'], 'class' => 'unselectable', 'cells' => $cells + array(
        'stage' => pg_list_cell(($stage !== '') ? '<span class="pg-pill pg-tone-' . pg_customer_rhythm_stage_tone($stage) . '">' . h($labels[$stage]) . '</span>' : '<span class="fg-secondary">' . h(lang('Not yet known')) . '</span>'),
        'expected' => pg_list_cell(h(pg_report_view_money((int) $record['expected_value'], $symbol)), 'text-end'),
        'chance' => pg_list_cell(h(lang(array('string' => '{var:1}%', 'vars' => array((int) $record['chance'])))), 'text-end'),
        'orders' => pg_list_cell(pg_format_number((int) $record['orders'], 0), 'text-end'),
        'last' => pg_list_cell($date($record['last_order_at']) . '<div class="fg-secondary small">' . h(lang(array('string' => '{var:1} days ago', 'vars' => array(pg_customer_rhythm_days_since($record, $now))))) . '</div>', 'text-nowrap', array('data-pg-sort-value' => (string) (int) $record['last_order_at'])),
        'interval' => pg_list_cell(($interval > 0) ? h(lang(array('string' => '{var:1} days', 'vars' => array($interval)))) : '<span class="fg-secondary">—</span>', 'text-end'),
        'next_due' => pg_list_cell($date($record['next_due_at']), 'text-nowrap', array('data-pg-sort-value' => (string) (int) $record['next_due_at'])),
    ));
}

/**
 * The FROM part and the columns the list and its CSV read: names only for
 * a reader who may see them. Pure.
 *
 * @param bool $names
 * @return array  array(from, select)
 */
function pg_customer_rhythm_list_from($names)
{
    $select = 'customer_rhythm.id, customer_rhythm.contact_id, customer_rhythm.orders, customer_rhythm.first_order_at, customer_rhythm.last_order_at, customer_rhythm.last_order_id, customer_rhythm.interval_days, customer_rhythm.stage, customer_rhythm.chance, customer_rhythm.expected_value, customer_rhythm.next_due_at';

    if (!$names) {
        return array('customer_rhythm', $select);
    }

    return array(
        'customer_rhythm LEFT JOIN contacts ON contacts.id = customer_rhythm.contact_id LEFT JOIN orders ON orders.id = customer_rhythm.last_order_id',
        $select . ', contacts.first_name AS contact_first_name, contacts.last_name AS contact_last_name, orders.order_number, orders.billing_first_name, orders.billing_last_name, orders.billing_company',
    );
}

/**
 * The customers of a stage as the list table driver reads them; the
 * screen's gate is the source's: commerce on, the table there and the
 * commerce reports right, else null.
 *
 * @param array $user
 * @param array $params  stage
 * @return array|null
 */
function pg_customer_rhythm_list_source($user, $params)
{
    if (!defined('ECOMMERCE') || !ECOMMERCE || !pg_customer_rhythm_ready() || !pg_customer_rhythm_may_read($user)) {
        return null;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

    $stage = pg_customer_rhythm_stage_pick(is_array($params) && isset($params['stage']) ? $params['stage'] : '');
    $names = (bool) validate_contacts_access($user, true);
    list($from, $select) = pg_customer_rhythm_list_from($names);
    $now = time();

    return pg_list_table_source(array(
        'list' => 'customer_rhythm',
        'columns' => pg_customer_rhythm_list_columns($names),
        'from' => $from,
        'where' => ($stage !== '') ? 'customer_rhythm.stage = ?' : '',
        'params' => ($stage !== '') ? array(pg_customer_rhythm_stage_code($stage)) : array(),
        'id' => 'customer_rhythm.id',
        'select' => $select,
        'search' => $names ? array('columns' => array('contacts.first_name', 'contacts.last_name', 'orders.billing_first_name', 'orders.billing_last_name', 'orders.billing_company', 'orders.order_number')) : array(),
        'levels' => pg_customer_rhythm_list_levels(),
        'rows' => function ($records) use ($names, $now) {
            $rows = array();

            foreach ($records as $record) {
                $rows[] = pg_customer_rhythm_list_row($record, $names, $now);
            }

            return $rows;
        },
        'params_out' => array('stage' => $stage),
    ));
}

/**
 * The CSV's header line. Pure.
 *
 * @return string
 */
function pg_customer_rhythm_csv_head()
{
    return 'customer,stage,orders,first_order,last_order,last_order_number,interval_days,days_since_last_order,chance,expected_value,next_due,currency';
}

/**
 * One customer as a CSV line: dates as Y-m-d in the site's time, the
 * expected value in major units with two decimals. Pure.
 *
 * @param array  $record  pg_customer_rhythm_list_from()'s columns, names included
 * @param int    $now
 * @param string $currency
 * @return string
 */
function pg_customer_rhythm_csv_line($record, $now, $currency)
{
    $day = function ($time) {
        return ((int) $time > 0) ? date('Y-m-d', (int) $time) : '';
    };

    return '"' . csv_cell(pg_customer_rhythm_name($record)) . '",'
        . pg_customer_rhythm_stage_name($record['stage']) . ','
        . (int) $record['orders'] . ','
        . $day($record['first_order_at']) . ','
        . $day($record['last_order_at']) . ','
        . '"' . csv_cell((string) ($record['order_number'] ?? '')) . '",'
        . (int) $record['interval_days'] . ','
        . pg_customer_rhythm_days_since($record, $now) . ','
        . (int) $record['chance'] . ','
        . number_format((int) $record['expected_value'] / 100, 2, '.', '') . ','
        . $day($record['next_due_at']) . ','
        . $currency;
}

/**
 * Prints the CSV of a stage's customers (every customer for ''), read in
 * batches by id. Only for a reader who may see names.
 *
 * @param string $stage
 * @return void
 */
function pg_customer_rhythm_csv_print($stage)
{
    list($from, $select) = pg_customer_rhythm_list_from(true);
    $code = pg_customer_rhythm_stage_code($stage);
    $now = time();
    $currency = defined('BASE_CURRENCY_CODE') ? (string) BASE_CURRENCY_CODE : '';
    $after = 0;

    print pg_customer_rhythm_csv_head() . "\n";

    while (true) {
        $params = array($after);
        $where = 'customer_rhythm.id > ?';

        if ($code > 0) {
            $where .= ' AND customer_rhythm.stage = ?';
            $params[] = $code;
        }

        $result = db_q('SELECT ' . $select . ' FROM ' . $from . ' WHERE ' . $where . ' ORDER BY customer_rhythm.id LIMIT ' . PG_CUSTOMER_RHYTHM_CSV_BATCH, $params);

        if ($result === false) {
            return;
        }

        $count = 0;

        while ($record = mysqli_fetch_assoc($result)) {
            print pg_customer_rhythm_csv_line($record, $now, $currency) . "\n";
            $after = (int) $record['id'];
            $count++;
        }

        mysqli_free_result($result);

        if ($count < PG_CUSTOMER_RHYTHM_CSV_BATCH) {
            return;
        }
    }
}

/**
 * The settings from the posted form: the switches unchecked are off, the
 * days held to their bounds. Pure.
 *
 * @param array $post
 * @return array  pg_customer_rhythm_settings_normalize()
 */
function pg_customer_rhythm_settings_from_post($post)
{
    return pg_customer_rhythm_settings_normalize(array(
        'on' => !empty($post['rhythm_on']),
        'brake_days' => isset($post['rhythm_brake_days']) && is_scalar($post['rhythm_brake_days']) ? trim((string) $post['rhythm_brake_days']) : '',
        'lead_days' => isset($post['rhythm_lead_days']) && is_scalar($post['rhythm_lead_days']) ? trim((string) $post['rhythm_lead_days']) : '',
        'share' => !empty($post['rhythm_share']),
    ));
}

/**
 * The settings as stored. Pure.
 *
 * @param array $settings  pg_customer_rhythm_settings_normalize()
 * @return string  JSON
 */
function pg_customer_rhythm_settings_encode($settings)
{
    $settings = pg_customer_rhythm_settings_normalize($settings);

    return (string) json_encode(array(
        'on' => $settings['on'] ? 1 : 0,
        'brake_days' => (int) $settings['brake_days'],
        'lead_days' => (int) $settings['lead_days'],
        'share' => $settings['share'] ? 1 : 0,
    ));
}

/**
 * Whether a user may change the settings: Manager or above. Pure.
 *
 * @param array $user
 * @return bool
 */
function pg_customer_rhythm_may_change($user)
{
    return is_array($user) && isset($user['role']) && ((int) $user['role'] < 3);
}

/**
 * The settings card of the Rhythm part.
 *
 * @param array $settings
 * @return string HTML
 */
function pg_customer_rhythm_settings_html($settings)
{
    $switch = function ($name, $title, $text, $on) {
        return '<div class="pg-card-inner-row"><div class="pg-look-grow"><label class="pg-list-row-title" for="' . $name . '">' . h($title) . '</label><div class="pg-text-meta" id="' . $name . '_help">' . h($text) . '</div></div>'
            . '<input type="checkbox" class="pg-switch" id="' . $name . '" name="' . $name . '" value="1" aria-describedby="' . $name . '_help"' . ($on ? ' checked' : '') . '></div>';
    };
    $number = function ($name, $title, $text, $value, $low, $high) {
        return '<div class="col-12 sm:col-6"><label class="pg-field-label" for="' . $name . '">' . h($title) . '</label>'
            . '<span class="pg-input"><input type="number" id="' . $name . '" name="' . $name . '" min="' . $low . '" max="' . $high . '" value="' . (int) $value . '" aria-describedby="' . $name . '_help"><span class="pg-input-end">' . h(lang('days')) . '</span></span>'
            . '<div class="pg-field-help" id="' . $name . '_help">' . h($text) . '</div></div>';
    };

    return '
        <section class="pg-card pg-report-frame" aria-labelledby="pg_rhythm_settings_title">
            <div class="pg-report-frame-head"><h3 class="pg-report-frame-title" id="pg_rhythm_settings_title">' . h(lang('Rhythm settings')) . '</h3></div>
            <form class="pg-report-frame-body p-5" action="view_customer_report.php?part=rhythm" method="post">
                ' . get_token_field() . '
                <input type="hidden" name="rhythm_settings" value="1">
                ' . $switch('rhythm_on', lang('Tell flows and webhooks'), lang('Announces stage changes and due reorders to flows and webhooks. Off, nothing is announced and no reminder is counted.'), $settings['on']) . '
                <div class="row mt-5">
                    ' . $number('rhythm_brake_days', lang('Days between two reorder reminders'), lang('At most one reorder reminder per customer in these days.'), $settings['brake_days'], 1, 90)
                    . $number('rhythm_lead_days', lang('Days before the due day'), lang('A product is due this many days before its due day.'), $settings['lead_days'], 0, 30) . '
                </div>
                <div class="mt-5">' . $switch('rhythm_share', lang('Hold back one customer in ten'), lang('One due customer in ten gets no reminder, so the return rate of those reminded can be compared with theirs.'), $settings['share']) . '</div>
                <div class="mt-5"><button type="submit" class="pg-button pg-button-fill">' . h(lang('Save')) . '</button></div>
            </form>
        </section>';
}

/**
 * The Rhythm part of the Customers Report: before the first pass, how far
 * it is; then the stages, who came back after a reminder, the list of the
 * chosen stage, the products with their rhythm and, for a Manager, the
 * settings.
 *
 * @param array  $user
 * @param string $stage   pg_customer_rhythm_stage_pick()
 * @param bool   $names   whether the reader may see names
 * @return string HTML
 */
function pg_customer_rhythm_report_html($user, $stage, $names)
{
    $summary = pg_customer_rhythm_summary();
    $symbol = defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : '';
    $settings = pg_customer_rhythm_settings();
    $settings_html = pg_customer_rhythm_may_change($user) ? pg_customer_rhythm_settings_html($settings) : '';

    if ($summary === null) {
        list(, $pass) = pg_customer_rhythm_pass_read();
        $text = ((int) $pass['started'] > 0)
            ? lang(array('string' => 'The rhythm is being worked out from your orders: {var:1} of {var:2} orders read. It appears here when every order has been read.', 'vars' => array(pg_format_number((int) $pass['done'], 0), pg_format_number((int) $pass['total'], 0))))
            : lang('The rhythm is worked out from your orders by the scheduled jobs. It appears here after the next run.');

        return '<div class="alert theme-info">' . h($text) . '</div>' . $settings_html;
    }

    $labels = pg_customer_rhythm_stage_labels();
    $bounds = pg_customer_rhythm_stage_bounds();
    $output = '';

    if (!$settings['on']) {
        $output .= '<div class="alert theme-warning">' . h(lang('Rhythm announcements are off: flows and webhooks hear nothing, and no reminder is counted.')) . '</div>';
    }

    // Customers and expected value per stage; a stage's name filters the list.
    $rows = '';

    foreach (pg_customer_rhythm_stage_rows(pg_customer_rhythm_stage_totals()) as $row) {
        $name = ($row['stage'] === '') ? h(lang('Not yet known')) : '<a href="' . h('view_customer_report.php?part=rhythm&stage=' . $row['stage']) . '#pg_rhythm_list_title"' . (($row['stage'] === $stage) ? ' aria-current="true"' : '') . '><span class="pg-pill pg-tone-' . pg_customer_rhythm_stage_tone($row['stage']) . '">' . h($labels[$row['stage']]) . '</span></a>';
        $rows .= '<tr>
            <th scope="row" class="fw-normal">' . $name . '</th>
            <td class="fg-secondary small">' . h(($row['stage'] === '') ? lang('The store has no second orders yet to judge them by') : $bounds[$row['stage']]) . '</td>
            <td class="text-end">' . pg_format_number($row['customers'], 0) . '</td>
            <td class="text-end">' . h(pg_report_view_money($row['value'], $symbol)) . '</td>
        </tr>';
    }

    $output .= '
        <section class="pg-card pg-report-frame" aria-labelledby="pg_rhythm_stages_title">
            <div class="pg-report-frame-head"><h3 class="pg-report-frame-title" id="pg_rhythm_stages_title">' . h(lang('Stages')) . '</h3><span class="pg-report-frame-note">' . h(lang('Each customer judged by their own interval between orders. The expected value is the next 12 months, weighed by the chance they still order.')) . '</span></div>
            <div class="pg-report-frame-body">
                <table class="pg-report-table">
                    <thead><tr><th>' . h(lang('Stage')) . '</th><th>' . h(lang('Bounds')) . '</th><th class="text-end">' . h(lang('Customers')) . '</th><th class="text-end">' . h(lang('Expected 12 months')) . '</th></tr></thead>
                    <tbody>' . $rows . '</tbody>
                </table>
            </div>
        </section>';

    // Reminders and who came back after them (H13).
    $return_rows = pg_customer_rhythm_return_rows($summary);
    $held = $settings['share'];

    foreach ($return_rows as $row) {
        $held = $held || ($row['held'] > 0);
    }

    $rate = function ($value) {
        return ($value === null) ? '<span class="fg-secondary">—</span>' : h(lang(array('string' => '{var:1}%', 'vars' => array($value))));
    };
    $rows = '';

    foreach ($return_rows as $row) {
        $rows .= '<tr>
            <th scope="row" class="fw-normal">' . h(pg_customer_value_month_name($row['month'])) . '</th>
            <td class="text-end">' . pg_format_number($row['told'], 0) . '</td>
            <td class="text-end">' . pg_format_number($row['back'], 0) . '</td>
            <td class="text-end">' . $rate($row['rate']) . '</td>
            <td class="text-end">' . h(pg_report_view_money($row['value'], $symbol)) . '</td>'
            . ($held ? '<td class="text-end">' . pg_format_number($row['held'], 0) . '</td><td class="text-end">' . pg_format_number($row['held_back'], 0) . '</td><td class="text-end">' . $rate($row['held_rate']) . '</td>' : '') . '
        </tr>';
    }

    if ($rows === '') {
        $rows = '<tr><td colspan="' . ($held ? 8 : 5) . '" class="fg-secondary">' . h(lang('No reorder reminder yet.')) . '</td></tr>';
    }

    $output .= '
        <section class="pg-card pg-report-frame" aria-labelledby="pg_rhythm_returns_title">
            <div class="pg-report-frame-head"><h3 class="pg-report-frame-title" id="pg_rhythm_returns_title">' . h(lang('Came back after a reminder')) . '</h3><span class="pg-report-frame-note">' . h(lang('A customer came back when they ordered within 14 days of a reorder reminder; counted in the reminder\'s month, so the last two weeks are still open.')) . '</span></div>
            <div class="pg-report-frame-body">
                <table class="pg-report-table">
                    <thead><tr><th>' . h(lang('Month')) . '</th><th class="text-end">' . h(lang('Reminders')) . '</th><th class="text-end">' . h(lang('Came back')) . '</th><th class="text-end">' . h(lang('Return rate')) . '</th><th class="text-end">' . h(lang('Value')) . '</th>'
                    . ($held ? '<th class="text-end">' . h(lang('Held back')) . '</th><th class="text-end">' . h(lang('Came back without a reminder')) . '</th><th class="text-end">' . h(lang('Their return rate')) . '</th>' : '') . '</tr></thead>
                    <tbody>' . $rows . '</tbody>
                </table>
            </div>
        </section>';

    // The customers of the chosen stage.
    $source = pg_customer_rhythm_list_source($user, array('stage' => $stage));

    if ($source !== null) {
        $page = pg_list_table_page($source, array(), null);
        $filter = '<a class="pg-seg-item' . (($stage === '') ? ' is-active" aria-current="true"' : '"') . ' href="view_customer_report.php?part=rhythm#pg_rhythm_list_title">' . h(lang('All')) . '</a>';

        foreach ($labels as $key => $label) {
            $filter .= '<a class="pg-seg-item' . (($stage === $key) ? ' is-active" aria-current="true"' : '"') . ' href="' . h('view_customer_report.php?part=rhythm&stage=' . $key) . '#pg_rhythm_list_title">' . h($label) . '</a>';
        }

        $output .= '
        <section class="pg-report-frame-list" aria-labelledby="pg_rhythm_list_title">
            <h3 class="pg-title-section mt-7 mb-3" id="pg_rhythm_list_title">' . h(($stage === '') ? lang('Customers') : $labels[$stage]) . '</h3>
            <div class="pg-seg mb-3" role="group" aria-label="' . h(lang('Stage')) . '">' . $filter . '</div>
            ' . (!$names ? '<p class="fg-secondary small">' . h(lang('Names are shown to users who may manage contacts.')) . '</p>' : '') . '
            ' . (is_array($page) ? pg_list_table_html($source, $page, lang('Customers')) . pg_js_bundle_tags('assets/js/pg_list_table.min.js') : '') . '
        </section>';
    }

    // Products with their rhythm.
    $names_by_id = array();
    $ids = array_map('intval', array_keys((array) $summary['products']));

    if ($ids) {
        foreach ((array) db_items("SELECT id, name, short_description FROM products WHERE id IN (" . implode(',', array_slice($ids, 0, 5000)) . ")") as $product) {
            $names_by_id[(int) $product['id']] = array((string) $product['name'], (string) $product['short_description']);
        }
    }

    $rows = '';

    foreach (pg_customer_rhythm_product_rows($summary, $names_by_id) as $row) {
        $rows .= '<tr>
            <th scope="row" class="fw-normal"><a href="' . h('edit_product.php?id=' . $row['id']) . '">' . h(($row['name'] !== '') ? $row['name'] : '#' . $row['id']) . '</a></th>
            <td class="text-end">' . h(lang(array('string' => '{var:1} days', 'vars' => array(pg_format_number($row['days_per_unit'], 1))))) . '</td>
            <td class="text-end">' . pg_format_number($row['pairs'], 0) . '</td>
            <td class="text-end">' . pg_format_number($row['customers'], 0) . '</td>
            <td>' . h($row['from_group'] ? lang('Its product group') : lang('Its own purchases')) . '</td>
        </tr>';
    }

    if ($rows === '') {
        $rows = '<tr><td colspan="5" class="fg-secondary">' . h(lang('No product has a rhythm yet: it needs 10 repeat purchases from 5 customers, or its group does.')) . '</td></tr>';
    }

    $output .= '
        <section class="pg-card pg-report-frame mt-7" aria-labelledby="pg_rhythm_products_title">
            <div class="pg-report-frame-head"><h3 class="pg-report-frame-title" id="pg_rhythm_products_title">' . h(lang('Products with a rhythm')) . '</h3><span class="pg-report-frame-note">' . h(lang(array('string' => 'Days per unit between two purchases by the same customer, over 24 months. {var:1} products have a rhythm; those with the most repeat purchases are shown.', 'vars' => array(pg_format_number((int) ($summary['products_all'] ?? 0), 0))))) . '</span></div>
            <div class="pg-report-frame-body">
                <table class="pg-report-table">
                    <thead><tr><th>' . h(lang('Product')) . '</th><th class="text-end">' . h(lang('Days per unit')) . '</th><th class="text-end">' . h(lang('Repeat purchases')) . '</th><th class="text-end">' . h(lang('Customers')) . '</th><th>' . h(lang('Learnt from')) . '</th></tr></thead>
                    <tbody>' . $rows . '</tbody>
                </table>
            </div>
        </section>';

    return $output . $settings_html;
}

/**
 * The rows a bound query reads; none when it fails.
 *
 * @param string $sql
 * @param array  $params
 * @return array
 */
function pg_customer_rhythm_select($sql, $params = array())
{
    $result = db_q($sql, $params);
    $rows = array();

    if ($result === false) {
        return $rows;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_free_result($result);

    return $rows;
}

/**
 * The customer_rhythm row of a contact: the one keyed by their e-mail
 * address, else the one keyed by the contact, else the one whose orders
 * last named the contact.
 *
 * @param int    $contact_id
 * @param string $email
 * @return array|null
 */
function pg_customer_rhythm_contact_row($contact_id, $email)
{
    $keys = pg_privacy_customer_keys(((string) $email !== '') ? array((string) $email) : array(), array((int) $contact_id));

    foreach ($keys as $key) {
        $rows = pg_customer_rhythm_select("SELECT * FROM customer_rhythm WHERE customer_key = ?", array($key));

        if ($rows) {
            return $rows[0];
        }
    }

    $rows = pg_customer_rhythm_select("SELECT * FROM customer_rhythm WHERE contact_id = ? ORDER BY last_order_at DESC LIMIT 1", array((int) $contact_id));

    return $rows ? $rows[0] : null;
}

/**
 * The products due for a customer now, worked out as the pass does from
 * their orders (the same key and counting rule), the product rhythms of
 * the summary and today's stock.
 *
 * @param array $row       a customer_rhythm row
 * @param array $summary   pg_customer_rhythm_summary()
 * @param array $settings  pg_customer_rhythm_settings()
 * @param int   $now
 * @return array  pg_customer_rhythm_due_products()
 */
function pg_customer_rhythm_contact_due($row, $summary, $settings, $now)
{
    $key = (string) $row['customer_key'];
    $columns = array('id', 'order_date', 'total', 'status', 'billing_email_address', 'contact_id');

    foreach (array('refunded_amount', 'type', 'cancelled_at') as $column) {
        if (pg_schema_has('orders', $column)) {
            $columns[] = $column;
        }
    }

    if (substr($key, 0, 1) === 'c') {
        $where = "contact_id = ? AND ((billing_email_address IS NULL) OR (billing_email_address = ''))";
        $param = (int) substr($key, 1);
    } else {
        $last = pg_customer_rhythm_select("SELECT billing_email_address FROM orders WHERE id = ?", array((int) $row['last_order_id']));
        $param = $last ? (string) $last[0]['billing_email_address'] : '';
        $where = 'billing_email_address = ?';

        if ($param === '') {
            return array();
        }
    }

    $times = array();

    foreach (pg_customer_rhythm_select("SELECT " . implode(', ', $columns) . " FROM orders WHERE " . $where . " AND (status IN ('complete', 'exported')) AND (order_date > 0) ORDER BY order_date DESC LIMIT 1000", array($param)) as $order) {
        if (pg_customer_value_order_counts($order) && (pg_customer_value_key($order) === $key)) {
            $times[(int) $order['id']] = array((int) $order['order_date'], (isset($order['type']) && ((string) $order['type'] === 'marketplace')) ? 'marketplace' : 'online');
        }
    }

    $rhythms = array();

    foreach ((array) $summary['products'] as $id => $rhythm) {
        $rhythms[(int) $id] = array('days_per_unit' => (float) $rhythm[0]);
    }

    if (!$times || !$rhythms) {
        return array();
    }

    $lines = array();

    foreach ((array) db_items("SELECT order_id, product_id, quantity FROM order_items WHERE order_id IN (" . implode(',', array_keys($times)) . ") AND (product_id > 0)" . pg_order_item_active_sql('order_items')) as $item) {
        $time = $times[(int) $item['order_id']];
        $lines[] = array('customer' => $key, 'product_id' => (int) $item['product_id'], 'quantity' => (int) $item['quantity'], 'time' => $time[0], 'type' => $time[1]);
    }

    $ids = array();

    foreach ($lines as $line) {
        if (isset($rhythms[$line['product_id']])) {
            $ids[$line['product_id']] = $line['product_id'];
        }
    }

    return pg_customer_rhythm_due_products($lines, $rhythms, $now, $ids ? pg_customer_rhythm_pass_available(array_values($ids)) : array(), (int) $settings['lead_days']);
}

/**
 * The contact card's figures from the customer's row. Pure.
 *
 * @param array $row  a customer_rhythm row
 * @param int   $now
 * @return array  stage, interval, own (false when the store's median stands
 *                for it), next_due, chance, expected, days_since, as_of
 */
function pg_customer_rhythm_card_view($row, $now)
{
    return array(
        'stage' => pg_customer_rhythm_stage_name($row['stage']),
        'interval' => (int) $row['interval_days'],
        'own' => ((int) $row['orders'] > 1),
        'next_due' => (int) $row['next_due_at'],
        'chance' => (int) $row['chance'],
        'expected' => (int) $row['expected_value'],
        'days_since' => pg_customer_rhythm_days_since($row, $now),
        'as_of' => (int) $row['updated_at'],
    );
}

/**
 * The contact screen's "Order rhythm" card, or its grey line when the
 * contact has no counted order; '' with commerce off, before the first
 * pass, or for a user without a commerce right.
 *
 * @param int    $contact_id
 * @param string $email
 * @param array  $user
 * @return string HTML
 */
function pg_customer_rhythm_contact_card($contact_id, $email, array $user)
{
    if (!defined('ECOMMERCE') || !ECOMMERCE || !pg_customer_rhythm_ready()
        || !(((int) $user['role'] < 3) || !empty($user['manage_ecommerce']) || !empty($user['manage_ecommerce_reports']))) {
        return '';
    }

    $summary = pg_customer_rhythm_summary();

    if ($summary === null) {
        return '';
    }

    $row = pg_customer_rhythm_contact_row($contact_id, $email);
    $head = '<h2 class="pg-title-section mb-1" id="customer_rhythm_title">' . h(lang('Order rhythm')) . '</h2>';

    if ($row === null) {
        return '<section class="pg-card p-5 mb-7" aria-labelledby="customer_rhythm_title">' . $head . '<p class="pg-text-meta mb-0">' . h(lang('No counted order yet, so no rhythm.')) . '</p></section>';
    }

    $now = time();
    $view = pg_customer_rhythm_card_view($row, $now);
    $labels = pg_customer_rhythm_stage_labels();
    $symbol = defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : '';
    $date = function ($time) {
        return h(get_absolute_time(array('timestamp' => (int) $time, 'type' => 'date', 'format' => 'plain_text')));
    };
    $figure = function ($label, $value, $note = '') {
        return '<div class="pg-card pg-figure"><div class="pg-figure-label">' . h($label) . '</div><div class="pg-figure-value">' . $value . '</div>' . (($note !== '') ? '<div class="pg-figure-note">' . h($note) . '</div>' : '') . '</div>';
    };

    $stage = ($view['stage'] !== '') ? '<span class="pg-pill pg-tone-' . pg_customer_rhythm_stage_tone($view['stage']) . '">' . h($labels[$view['stage']]) . '</span>' : h(lang('Not yet known'));
    $figures = $figure(lang('Stage'), $stage, lang(array('string' => '{var:1} days since the last order', 'vars' => array($view['days_since']))))
        . $figure(lang('Own interval'), ($view['interval'] > 0) ? h(lang(array('string' => '{var:1} days', 'vars' => array($view['interval'])))) : '—', $view['own'] ? '' : lang('The store\'s median until a second order'))
        . $figure(lang('Next due day'), ($view['next_due'] > 0) ? $date($view['next_due']) : '—')
        . $figure(lang('Chance still a customer'), h(lang(array('string' => '{var:1}%', 'vars' => array($view['chance'])))))
        . $figure(lang('Expected 12 months'), h(pg_report_view_money($view['expected'], $symbol)));

    $due = '';
    $due_products = pg_customer_rhythm_contact_due($row, $summary, pg_customer_rhythm_settings(), $now);

    if ($due_products) {
        $products = array();

        foreach ((array) db_items("SELECT id, name, short_description FROM products WHERE id IN (" . implode(',', array_map('intval', array_column($due_products, 'product_id'))) . ")") as $item) {
            $products[(int) $item['id']] = ((string) $item['short_description'] !== '') ? (string) $item['short_description'] : (string) $item['name'];
        }

        foreach ($due_products as $product) {
            $id = (int) $product['product_id'];
            $due .= '<div class="pg-gather-line"><span class="text-break">' . h((int) $product['quantity'] . ' × ' . (isset($products[$id]) ? $products[$id] : '#' . $id)) . '</span><span class="pg-gather-line-end">' . $date($product['due']) . '</span></div>';
        }
    }

    return '
                            <section class="pg-card p-5 mb-7" aria-labelledby="customer_rhythm_title">
                                ' . $head . '
                                <p class="pg-card-sub mb-5">' . h(lang(array('string' => 'As of {var:1}', 'vars' => array(strip_tags(get_absolute_time(array('timestamp' => $view['as_of']))))))) . '</p>
                                <div class="pg-figures">' . $figures . '</div>
                                <h3 class="pg-title-status mt-5 mb-2">' . h(lang('Products due')) . '</h3>
                                ' . (($due !== '') ? '<div class="pg-gather">' . $due . '</div>' : '<p class="pg-text-meta mb-0">' . h(lang('No product is due for a reorder.')) . '</p>') . '
                            </section>';
}
