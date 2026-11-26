<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * First and last touch: when a contact becomes a lead (its first form or
 * booking) or a customer (its first order or paid sales invoice), the
 * journey keeps one row with the first and the last of its visits tied
 * before that act. The Lead sources report counts those rows by the
 * touch's channel, campaign or landing page over a period.
 *
 * The touches, the verdict, the groups and the shares are pure; the
 * pg_lead_became*() writers and pg_lead_sources_read() query.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/lead_journey_view.php');

/** contact_journey.kind of a contact that became a lead. */
const PG_LEAD_JOURNEY_LEAD = 3;

/** contact_journey.kind of a contact that became a customer. */
const PG_LEAD_JOURNEY_CUSTOMER = 4;

/**
 * The journey kind an act makes: a form or a booking a lead, an order or a
 * paid invoice a customer; 0 for any other act. Pure.
 *
 * @param string $act
 * @return int
 */
function pg_lead_touch_act_kind($act)
{
    $kinds = array('form' => PG_LEAD_JOURNEY_LEAD, 'booking' => PG_LEAD_JOURNEY_LEAD, 'order' => PG_LEAD_JOURNEY_CUSTOMER, 'invoice' => PG_LEAD_JOURNEY_CUSTOMER);

    return $kinds[(string) $act] ?? 0;
}

/**
 * The first and the last visit started at or before the act; at the same
 * second the higher id is the later. Pure.
 *
 * @param array[] $visits id, started_at
 * @param int     $at     the act's time
 * @return array|null first, last (visit ids); null without such a visit
 */
function pg_lead_touch_pick(array $visits, $at)
{
    $first = null;
    $last = null;

    foreach ($visits as $visit) {
        $started = (int) ($visit['started_at'] ?? 0);

        if (($started <= 0) || ($started > (int) $at)) {
            continue;
        }

        $visit = array('id' => (int) ($visit['id'] ?? 0), 'started_at' => $started);

        if (($first === null) || ($started < $first['started_at']) || (($started === $first['started_at']) && ($visit['id'] < $first['id']))) {
            $first = $visit;
        }

        if (($last === null) || ($started > $last['started_at']) || (($started === $last['started_at']) && ($visit['id'] > $last['id']))) {
            $last = $visit;
        }
    }

    return ($first === null) ? null : array('first' => $first['id'], 'last' => $last['id']);
}

/**
 * Whether an act makes the contact a lead or a customer now, in this order:
 * `not_an_act`, `no_contact`, `panel_user` (staff are never counted),
 * `already` (a contact becomes each once), `not_first` (an earlier form,
 * booking, order or paid invoice made it one before), `no_visit` (no visit
 * tied before the act), else `mark`. Pure.
 *
 * @param array $facts kind, contact_id, staff, marked, earlier, touches (pg_lead_touch_pick())
 * @return string
 */
function pg_lead_touch_verdict(array $facts)
{
    if ((int) ($facts['kind'] ?? 0) <= 0) {
        return 'not_an_act';
    }

    if ((int) ($facts['contact_id'] ?? 0) <= 0) {
        return 'no_contact';
    }

    if (!empty($facts['staff'])) {
        return 'panel_user';
    }

    if (!empty($facts['marked'])) {
        return 'already';
    }

    if (!empty($facts['earlier'])) {
        return 'not_first';
    }

    return empty($facts['touches']) ? 'no_visit' : 'mark';
}

/**
 * The booking statuses that count as a booking made: not a hold, which is
 * none yet, and not a cancelled one, as a cancelled order counts for
 * nothing either (an abandoned deposit checkout ends as a cancelled hold
 * that carries the customer). Pure.
 *
 * @return string[]
 */
function pg_lead_touch_booking_statuses()
{
    return array('requested', 'confirmed', 'no_show', 'done');
}

/**
 * Whether the contact had an act of the same kind before this one: a lead
 * act a complete form or a booking made (pg_lead_touch_booking_statuses()),
 * a customer act a complete order or a paid sales invoice.
 *
 * @param int    $kind PG_LEAD_JOURNEY_LEAD or PG_LEAD_JOURNEY_CUSTOMER
 * @param int    $contact_id
 * @param string $act
 * @param int    $ref_id the act's own record, left out
 * @return bool
 */
function pg_lead_touch_earlier($kind, $contact_id, $act, $ref_id)
{
    $contact_id = (int) $contact_id;
    $not = function ($own) use ($act, $ref_id) {
        return ($act === $own) ? " AND id <> '" . (int) $ref_id . "'" : '';
    };

    if ($kind === PG_LEAD_JOURNEY_LEAD) {
        if ((int) db_value("SELECT COUNT(*) FROM forms WHERE contact_id = '" . $contact_id . "' AND complete = 1" . $not('form')) > 0) {
            return true;
        }

        $user_ids = array_map('intval', (array) db_values("SELECT user_id FROM user WHERE user_contact = '" . $contact_id . "'"));

        return $user_ids && function_exists('pg_booking_ready') && pg_booking_ready()
            && ((int) db_value("SELECT COUNT(*) FROM bookings WHERE user_id IN (" . implode(',', $user_ids) . ") AND status IN ('" . implode("', '", pg_lead_touch_booking_statuses()) . "')" . $not('booking')) > 0);
    }

    if ((int) db_value("SELECT COUNT(*) FROM orders WHERE contact_id = '" . $contact_id . "' AND status IN ('complete', 'exported')" . $not('order')) > 0) {
        return true;
    }

    return pg_schema_has('erp_accounts', 'contact_id')
        && ((int) db_value("SELECT COUNT(*) FROM erp_invoices WHERE account_id IN (SELECT id FROM erp_accounts WHERE contact_id = '" . $contact_id . "')
            AND direction = 'sales' AND doc_type = 'invoice' AND status = 'paid'" . $not('invoice')) > 0);
}

/**
 * The name of the lock a contact's lead or customer mark is checked and
 * written under. GET_LOCK names are server-wide, so the database name keeps
 * two sites on one server apart; at most 64 characters. Pure.
 *
 * @param int    $contact_id
 * @param int    $kind
 * @param string $database
 * @return string
 */
function pg_lead_became_lock_name($contact_id, $kind, $database)
{
    return 'pg_lead_became_' . substr(md5((string) $database), 0, 8) . '_' . (int) $kind . '_' . (int) $contact_id;
}

/**
 * A contact's act at its own save path: when it is the contact's first lead
 * or customer act and visits were tied to the contact before it, one
 * journey row keeps its first and last touch (pg_lead_touch_verdict()).
 *
 * @param string $act        form, booking, order or invoice
 * @param int    $contact_id
 * @param int    $ref_id     the act's record
 * @return string the verdict, 'not_ready' before the journey's tables, 'busy'
 *                when another request held the contact's lock too long
 */
function pg_lead_became($act, $contact_id, $ref_id)
{
    if (!function_exists('pg_lead_ready') || !pg_lead_ready()) {
        return 'not_ready';
    }

    $now = time();
    $contact_id = (int) $contact_id;
    $kind = pg_lead_touch_act_kind($act);
    $facts = array('kind' => $kind, 'contact_id' => $contact_id, 'staff' => false, 'marked' => false, 'earlier' => false, 'touches' => null);

    // Two acts of one contact at the same moment (a form sent twice, an order
    // and a paid invoice) would both find no row and write two: the check
    // and the write run under the contact's lock for the kind.
    $lock = '';

    if (($kind > 0) && ($contact_id > 0)) {
        $lock = pg_lead_became_lock_name($contact_id, $kind, defined('DB_DATABASE') ? (string) DB_DATABASE : '');

        if ((int) db_value("SELECT GET_LOCK('" . e($lock) . "', 5)") !== 1) {
            return 'busy';
        }
    }

    // Each fact is read only while the verdict still needs it.
    if (($kind > 0) && ($contact_id > 0)) {
        $facts['staff'] = pg_lead_journey_contact_is_staff($contact_id);
        $facts['marked'] = !$facts['staff']
            && ((int) db_value("SELECT COUNT(*) FROM contact_journey WHERE contact_id = '" . $contact_id . "' AND kind = '" . $kind . "'") > 0);
        $facts['earlier'] = !$facts['staff'] && !$facts['marked'] && pg_lead_touch_earlier($kind, $contact_id, (string) $act, (int) $ref_id);
    }

    if (pg_lead_touch_verdict(array('touches' => array(1)) + $facts) === 'mark') {
        $where = "WHERE contact_id = '" . $contact_id . "' AND started_at > 0 AND started_at <= '" . $now . "'";
        $facts['touches'] = pg_lead_touch_pick(array_merge(
            (array) db_items("SELECT id, started_at FROM lead_visits " . $where . " ORDER BY started_at ASC, id ASC LIMIT 1"),
            (array) db_items("SELECT id, started_at FROM lead_visits " . $where . " ORDER BY started_at DESC, id DESC LIMIT 1")
        ), $now);
    }

    $verdict = pg_lead_touch_verdict($facts);

    if ($verdict === 'mark') {
        db_q(
            "INSERT INTO contact_journey (contact_id, kind, at, visit_id, ref_id, detail, first_visit_id, last_visit_id) VALUES (?, ?, ?, 0, ?, ?, ?, ?)",
            array((string) $contact_id, (string) $kind, (string) $now, (string) max(0, (int) $ref_id), (string) $act, (string) $facts['touches']['first'], (string) $facts['touches']['last'])
        );
    }

    if ($lock !== '') {
        db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");
    }

    return $verdict;
}

/**
 * A booking made on the site by a signed-in person: the contact of the
 * account becomes a lead (pg_lead_became()).
 *
 * @param int $user_id
 * @param int $booking_id
 * @return string
 */
function pg_lead_became_booking($user_id, $booking_id)
{
    if (((int) $user_id <= 0) || !function_exists('pg_lead_ready') || !pg_lead_ready()) {
        return 'no_contact';
    }

    return pg_lead_became('booking', (int) db_value("SELECT user_contact FROM user WHERE user_id = '" . (int) $user_id . "'"), (int) $booking_id);
}

/**
 * A sales invoice paid in full: the contact of its account becomes a
 * customer (pg_lead_became()).
 *
 * @param int $invoice_id
 * @return string
 */
function pg_lead_became_invoice($invoice_id)
{
    if (!function_exists('pg_lead_ready') || !pg_lead_ready() || !pg_schema_has('erp_accounts', 'contact_id')) {
        return 'not_ready';
    }

    $contact_id = (int) db_value("SELECT a.contact_id FROM erp_invoices i JOIN erp_accounts a ON a.id = i.account_id
        WHERE i.id = '" . (int) $invoice_id . "' AND i.direction = 'sales' AND i.doc_type = 'invoice'");

    return pg_lead_became('invoice', $contact_id, (int) $invoice_id);
}

/**
 * A choice of the report's address, the first allowed one when it is not
 * one of them. Pure.
 *
 * @param mixed    $value
 * @param string[] $allowed
 * @return string
 */
function pg_lead_sources_choice($value, array $allowed)
{
    return (is_string($value) && in_array($value, $allowed, true)) ? $value : (string) $allowed[0];
}

/**
 * The share of leads that became customers, a whole percent; null with no
 * lead. Pure.
 *
 * @param int $converted
 * @param int $leads
 * @return int|null
 */
function pg_lead_sources_share($converted, $leads)
{
    return ((int) $leads > 0) ? (int) round(100 * (int) $converted / (int) $leads) : null;
}

/**
 * The report's rows grouped by the touch's channel, campaign or landing
 * page ('' when the visit had none): leads, those of them that became
 * customers, customers and their first orders' revenue. A touch whose visit
 * is gone (trimmed by the keep period) is a group of its own with key '' and
 * gone true, so it never mixes with the visits that had no campaign. Most
 * leads first, then most customers, then by name. Pure.
 *
 * @param array[] $rows kind, channel, campaign, landing_page, converted, revenue, gone
 * @param string  $by   channel, campaign or landing_page
 * @return array[] key, gone, leads, converted, customers, revenue, share
 */
function pg_lead_sources_group(array $rows, $by)
{
    $groups = array();

    foreach ($rows as $row) {
        $gone = !empty($row['gone']);
        $key = $gone ? '' : (string) ($row[$by] ?? '');
        $slot = ($gone ? 'gone:' : 'key:') . $key;

        if (!isset($groups[$slot])) {
            $groups[$slot] = array('key' => $key, 'gone' => $gone, 'leads' => 0, 'converted' => 0, 'customers' => 0, 'revenue' => 0, 'share' => null);
        }

        if ((int) $row['kind'] === PG_LEAD_JOURNEY_LEAD) {
            $groups[$slot]['leads']++;
            $groups[$slot]['converted'] += empty($row['converted']) ? 0 : 1;
        } elseif ((int) $row['kind'] === PG_LEAD_JOURNEY_CUSTOMER) {
            $groups[$slot]['customers']++;
            $groups[$slot]['revenue'] += (int) ($row['revenue'] ?? 0);
        }
    }

    foreach ($groups as $key => $group) {
        $groups[$key]['share'] = pg_lead_sources_share($group['converted'], $group['leads']);
    }

    $groups = array_values($groups);

    usort($groups, function ($a, $b) {
        if ($a['leads'] !== $b['leads']) {
            return $b['leads'] - $a['leads'];
        }

        if ($a['customers'] !== $b['customers']) {
            return $b['customers'] - $a['customers'];
        }

        if ($a['key'] !== $b['key']) {
            return strcmp($a['key'], $b['key']);
        }

        return (int) $a['gone'] - (int) $b['gone'];
    });

    return $groups;
}

/**
 * The groups added up. Pure.
 *
 * @param array[] $groups pg_lead_sources_group()
 * @return array leads, converted, customers, revenue, share
 */
function pg_lead_sources_totals(array $groups)
{
    $totals = array('leads' => 0, 'converted' => 0, 'customers' => 0, 'revenue' => 0);

    foreach ($groups as $group) {
        foreach ($totals as $name => $value) {
            $totals[$name] = $value + (int) $group[$name];
        }
    }

    return $totals + array('share' => pg_lead_sources_share($totals['converted'], $totals['leads']));
}

/**
 * A customer row as the report reads it, judged at read (the row is never
 * changed): null when it makes no customer, else when the contact became one
 * and the revenue it brings in minor units. Pure.
 * - A paid-invoice row, or an order row whose order is gone (deleted,
 *   purged) or was not read, is read as written with no revenue.
 * - An order the Customers report counts (pg_customer_value_order_counts())
 *   is the customer, worth its total less any refund
 *   (pg_customer_value_order_amount()).
 * - An order it does not count (cancelled, refunded in full) is no
 *   customer: the contact's next counting order after it takes the row over,
 *   with that order's time and amount; with none, no customer.
 *
 * @param array      $row    at, detail, ref_id
 * @param array|null $order  the row's own order: id, status, cancelled_at, total, refunded_amount, order_date
 * @param array[]    $others the contact's other orders, the same columns, in any order
 * @return array|null at, revenue
 */
function pg_lead_customer_read(array $row, $order, array $others)
{
    if (((string) ($row['detail'] ?? '') !== 'order') || !is_array($order)) {
        return array('at' => (int) $row['at'], 'revenue' => 0);
    }

    if (pg_customer_value_order_counts($order)) {
        return array('at' => (int) $row['at'], 'revenue' => pg_customer_value_order_amount($order));
    }

    $own = array((int) $order['order_date'], (int) $order['id']);
    $next = null;

    foreach ($others as $other) {
        $place = array((int) $other['order_date'], (int) $other['id']);

        if (($place <= $own) || !pg_customer_value_order_counts($other)) {
            continue;
        }

        if (($next === null) || ($place < array((int) $next['order_date'], (int) $next['id']))) {
            $next = $other;
        }
    }

    return ($next === null) ? null : array('at' => (int) $next['order_date'], 'revenue' => pg_customer_value_order_amount($next));
}

/**
 * The orders the customer rows name, and for a row whose own order does not
 * count, the contact's complete or exported orders a later one may take it
 * over from: own (by order id), others (by contact id).
 *
 * @param array[] $rows contact_id, detail, ref_id
 * @return array own, others
 */
function pg_lead_customer_orders(array $rows)
{
    $out = array('own' => array(), 'others' => array());
    $ids = array();

    foreach ($rows as $row) {
        if ((string) $row['detail'] === 'order') {
            $ids[(int) $row['ref_id']] = (int) $row['contact_id'];
        }
    }

    if (!$ids) {
        return $out;
    }

    $columns = 'id, contact_id, status, total, order_date, '
        . (pg_schema_has('orders', 'cancelled_at') ? 'cancelled_at' : '0 AS cancelled_at') . ', '
        . (pg_schema_has('orders', 'refunded_amount') ? 'refunded_amount' : '0 AS refunded_amount');
    $contacts = array();

    foreach (array_chunk(array_keys($ids), 500) as $chunk) {
        foreach ((array) db_items("SELECT " . $columns . " FROM orders WHERE id IN (" . implode(',', $chunk) . ")") as $order) {
            $out['own'][(int) $order['id']] = $order;

            if (!pg_customer_value_order_counts($order)) {
                $contacts[$ids[(int) $order['id']]] = true;
            }
        }
    }

    foreach (array_chunk(array_keys($contacts), 500) as $chunk) {
        foreach ((array) db_items("SELECT " . $columns . " FROM orders WHERE contact_id IN (" . implode(',', $chunk) . ") AND status IN ('complete', 'exported')") as $order) {
            $out['others'][(int) $order['contact_id']][] = $order;
        }
    }

    return $out;
}

/**
 * The lead and customer rows of a period with the touch's visit, each
 * contact's earliest row of a kind only (a merge moves both contacts' rows
 * to the one kept, and a contact becomes a lead or a customer once): its
 * channel, campaign and landing page; whether a lead's contact became a
 * customer at or after the lead (one who bought first is no conversion); a
 * customer's revenue. With commerce on, each customer row is judged by its
 * order (pg_lead_customer_read()): a row taken over by a later order counts
 * in that order's period, so a row before the period is read when a counting
 * order of the contact falls in it.
 *
 * @param int    $from
 * @param int    $to    the first second after the period
 * @param string $touch first or last
 * @return array[] kind, channel, campaign, landing_page, converted, revenue, gone (the visit was trimmed)
 */
function pg_lead_sources_read($from, $to, $touch)
{
    if (!function_exists('pg_lead_ready') || !pg_lead_ready()) {
        return array();
    }

    $from = (int) $from;
    $to = (int) $to;
    $commerce = defined('ECOMMERCE') && (ECOMMERCE === true);
    $visit = ($touch === 'last') ? 'j.last_visit_id' : 'j.first_visit_id';
    $customer = "'" . PG_LEAD_JOURNEY_CUSTOMER . "'";
    $earlier = '';

    if ($commerce) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/product_insights.php');
        $counted = pg_product_insights_counted_sql(pg_schema_has('orders', 'cancelled_at'), pg_schema_has('orders', 'refunded_amount'));
        $earlier = " OR (j.kind = " . $customer . " AND j.detail = 'order' AND j.at < '" . $from . "'
                AND EXISTS (SELECT 1 FROM orders WHERE orders.id = j.ref_id AND NOT (" . $counted . "))
                AND EXISTS (SELECT 1 FROM orders WHERE orders.contact_id = j.contact_id AND orders.id <> j.ref_id AND " . $counted . "
                    AND orders.order_date >= '" . $from . "' AND orders.order_date < '" . $to . "'))";
    }

    $found = (array) db_items("SELECT j.contact_id, j.kind, j.at, j.detail, j.ref_id, v.channel, v.campaign, v.landing_page, v.id AS visit_id
        FROM contact_journey j
        LEFT JOIN lead_visits v ON v.id = " . $visit . "
        WHERE j.kind IN ('" . PG_LEAD_JOURNEY_LEAD . "', " . $customer . ") AND ((j.at >= '" . $from . "' AND j.at < '" . $to . "')" . $earlier . ")
            AND NOT EXISTS (SELECT 1 FROM contact_journey e WHERE e.contact_id = j.contact_id AND e.kind = j.kind AND (e.at < j.at OR (e.at = j.at AND e.id < j.id)))");

    // Every customer row of the leads' contacts, to tell whether each lead
    // became a customer at or after it.
    $leads = array();

    foreach ($found as $row) {
        if ((int) $row['kind'] === PG_LEAD_JOURNEY_LEAD) {
            $leads[(int) $row['contact_id']] = true;
        }
    }

    $customers = array();

    foreach (array_chunk(array_keys($leads), 500) as $chunk) {
        foreach ((array) db_items("SELECT contact_id, at, detail, ref_id FROM contact_journey WHERE kind = " . $customer . " AND contact_id IN (" . implode(',', $chunk) . ")") as $row) {
            $customers[] = $row;
        }
    }

    $orders = $commerce ? pg_lead_customer_orders(array_merge($found, $customers)) : array('own' => array(), 'others' => array());
    $judge = function ($row) use ($orders) {
        return pg_lead_customer_read($row, $orders['own'][(int) $row['ref_id']] ?? null, $orders['others'][(int) $row['contact_id']] ?? array());
    };
    $became = array();

    foreach ($customers as $row) {
        $read = $judge($row);

        if ($read !== null) {
            $became[(int) $row['contact_id']][] = $read['at'];
        }
    }

    $rows = array();

    foreach ($found as $row) {
        $kind = (int) $row['kind'];
        $converted = false;
        $revenue = 0;

        if ($kind === PG_LEAD_JOURNEY_CUSTOMER) {
            $read = $judge($row);

            if (($read === null) || ($read['at'] < $from) || ($read['at'] >= $to)) {
                continue;
            }

            $revenue = $read['revenue'];
        } else {
            foreach ($became[(int) $row['contact_id']] ?? array() as $at) {
                $converted = $converted || ($at >= (int) $row['at']);
            }
        }

        $rows[] = array(
            'kind'         => $kind,
            'channel'      => (string) $row['channel'],
            'campaign'     => (string) $row['campaign'],
            'landing_page' => (string) $row['landing_page'],
            'converted'    => $converted,
            'revenue'      => $revenue,
            'gone'         => $row['visit_id'] === null,
        );
    }

    return $rows;
}

/**
 * A group's name as the report shows it: a channel by the visitor reports'
 * names, an empty key in words; a touch whose visit is gone is "Not known".
 *
 * @param string $by   channel, campaign or landing_page
 * @param string $key
 * @param bool   $gone the group's gone (pg_lead_sources_group())
 * @return string
 */
function pg_lead_sources_label($by, $key, $gone = false)
{
    if ($gone) {
        return lang('Not known');
    }

    if ($key === '') {
        return ($by === 'campaign') ? lang('No campaign') : lang('Not known');
    }

    return ($by === 'channel') ? pg_lead_journey_channel_label($key) : $key;
}
