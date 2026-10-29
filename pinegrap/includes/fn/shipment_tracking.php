<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Shipment tracking - the scheduled job that follows the parcels a carrier
 * label service opened and writes where each one stands back onto its row.
 *
 * A parcel is followed while it is open: an active label (shipment_labels,
 * not a return label) of a driver that is on and can track, made in the last
 * PG_SHIPMENT_TRACKING_DAYS days, whose parcel row is still there and has
 * neither arrived nor gone back, on an order that is not cancelled. Its
 * driver is asked through pg_label_call() (track()), and the answer reaches
 * the parcel only through the shipment write path (includes/fn/shipping.php):
 * pg_shipment_record() for its status, its carrier and the carrier's own
 * tracking page, pg_shipment_delivered() for its arrival, which announces
 * order.delivered and starts the order_delivered e-mail and text message
 * once per address, whoever learnt of the arrival first.
 *
 * A run asks about PG_SHIPMENT_TRACKING_BATCH parcels at most and starts no
 * question after PG_SHIPMENT_TRACKING_SECONDS. Each driver keeps its place
 * in data/temp/shipment_tracking.json, the last parcel asked about, so the
 * next run carries on after it and every open parcel takes its turn. The
 * drivers' parcels are asked in turn; a driver that fails
 * PG_SHIPMENT_TRACKING_FAILURES times in a row is asked nothing more in that
 * run, and its parcels wait after its place for the next one. A failed
 * question writes nothing.
 *
 * Loaded where it is needed: shipment_tracking_job.php, and the Shipping
 * card's save, which switches the job on (pg_label_settings_save()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Parcels one run asks about at most.
define('PG_SHIPMENT_TRACKING_BATCH', 100);

// Seconds after which a run starts no new question.
define('PG_SHIPMENT_TRACKING_SECONDS', 90);

// A label older than this many days is no longer followed.
define('PG_SHIPMENT_TRACKING_DAYS', 60);

// Failed answers in a row after which a driver is asked nothing more in a run.
define('PG_SHIPMENT_TRACKING_FAILURES', 3);

/**
 * The drivers whose parcels are followed: switched on, set up and able to
 * track.
 *
 * @return string[] driver keys
 */
function pg_shipment_tracking_drivers()
{
    $drivers = array();

    foreach (array_keys(pg_label_drivers()) as $key) {
        // An add-on driver whose file cannot be loaded is left out of the
        // run instead of ending it.
        try {
            if (pg_label_can($key, 'track') && pg_label_ready($key)) {
                $drivers[] = $key;
            }
        } catch (\Throwable $exception) {
            continue;
        }
    }

    return $drivers;
}

/**
 * The open parcels of one driver after a label id, in id order.
 *
 * @param string $driver
 * @param int    $after  the last label id asked about, 0 for the start
 * @param int    $limit
 * @param int    $now
 * @return array list of id (the label's), order_id, order_number,
 *               ship_to_id, driver, reference, number (as the parcel row
 *               keeps it), carrier, tracking_url, status
 */
function pg_shipment_tracking_open($driver, $after, $limit, $now)
{
    return db_items(
        "SELECT
            shipment_labels.id,
            shipment_labels.order_id,
            orders.order_number,
            shipment_labels.ship_to_id,
            shipment_labels.driver,
            shipment_labels.reference,
            shipping_tracking_numbers.number,
            shipping_tracking_numbers.carrier,
            shipping_tracking_numbers.tracking_url,
            shipping_tracking_numbers.status
        FROM shipment_labels
        INNER JOIN shipping_tracking_numbers ON
            (shipping_tracking_numbers.ship_to_id = shipment_labels.ship_to_id)
            AND (shipping_tracking_numbers.number = shipment_labels.number)
        INNER JOIN orders ON orders.id = shipment_labels.order_id
        WHERE
            (shipment_labels.state = 'active')
            AND (shipment_labels.driver = '" . e((string) $driver) . "')
            AND (shipment_labels.id > '" . (int) $after . "')
            AND (shipment_labels.is_return = '0')
            AND (shipment_labels.reference != '')
            AND (shipment_labels.created_at >= '" . ((int) $now - (PG_SHIPMENT_TRACKING_DAYS * 86400)) . "')
            AND (shipping_tracking_numbers.delivered_at = '0')
            AND (shipping_tracking_numbers.status NOT IN ('delivered', 'returned'))
            AND (orders.status NOT IN ('incomplete', 'cancelled'))
        ORDER BY shipment_labels.id
        LIMIT " . (int) $limit);
}

/**
 * What a track() answer changes on a parcel. A failure changes nothing; an
 * arrival is recorded by pg_shipment_delivered(); any other status, and a
 * carrier or a tracking page the row does not have yet, by
 * pg_shipment_record(). The number stays the label's: a carrier's own number
 * given later would renumber the parcel away from its label. Pure.
 *
 * @param array $row    pg_shipment_tracking_open() row (status, carrier,
 *                      tracking_url)
 * @param mixed $answer pg_label_answer() of track()
 * @param int   $now
 * @return array ok, error, delivered (bool), status (to write, '' for none),
 *               at (when the service says the parcel got there, at most
 *               now), options (carrier and tracking_url to write), write
 *               (pg_shipment_record() has something to write)
 */
function pg_shipment_tracking_decide($row, $answer, $now)
{
    $now = (int) $now;
    $out = array(
        'ok'        => false,
        'error'     => '',
        'delivered' => false,
        'status'    => '',
        'at'        => $now,
        'options'   => array(),
        'write'     => false,
    );

    if (!is_array($answer) || empty($answer['success'])) {
        $out['error'] = (is_array($answer) && isset($answer['error']) && is_scalar($answer['error'])) ? (string) $answer['error'] : '';

        return $out;
    }

    $out['ok'] = true;

    if (isset($answer['status_at']) && ((int) $answer['status_at'] > 0)) {
        $out['at'] = min((int) $answer['status_at'], $now);
    }

    $status = isset($answer['status']) ? (string) $answer['status'] : '';

    if ($status === 'delivered') {
        $out['delivered'] = true;
    } elseif ((pg_shipment_status_reported($status) !== '') && ($status !== (string) $row['status'])) {
        $out['status'] = $status;
    }

    foreach (array('carrier', 'tracking_url') as $name) {
        $value = isset($answer[$name]) ? (string) $answer[$name] : '';

        if (($value !== '') && ($value !== (string) $row[$name])) {
            $out['options'][$name] = $value;
        }
    }

    $out['write'] = ($out['status'] !== '') || ($out['options'] !== array());

    return $out;
}

/**
 * The drivers' parcels in the order they are asked about: the first of each
 * driver, then the second of each, and so on, so one slow service does not
 * hold the others back. Pure.
 *
 * @param array $lists driver => list of rows
 * @return array list of rows
 */
function pg_shipment_tracking_queue($lists)
{
    $lists = array_map('array_values', array_filter((array) $lists, 'is_array'));
    $longest = $lists ? max(array_map('count', $lists)) : 0;
    $queue = array();

    for ($index = 0; $index < $longest; $index++) {
        foreach ($lists as $list) {
            if (isset($list[$index])) {
                $queue[] = $list[$index];
            }
        }
    }

    return $queue;
}

/**
 * One run over the open parcels: each is asked about in turn and its answer
 * written, until $limit parcels were asked about or $deadline has come. A
 * driver that fails PG_SHIPMENT_TRACKING_FAILURES times in a row is asked
 * nothing more. Pure but for the callables.
 *
 * @param array         $lists    driver => list of pg_shipment_tracking_open() rows
 * @param callable      $ask      row => pg_label_answer() of track()
 * @param callable      $apply    (row, pg_shipment_tracking_decide()) => writes it
 * @param int           $deadline unix time from which nothing more is asked
 * @param int           $limit    parcels asked about at most
 * @param callable      $clock    () => unix time
 * @param callable|null $progress (driver, label id) after each parcel asked about
 * @return array last (driver => the last label id asked about), asked,
 *               changed, delivered, failed, errors (driver => its last
 *               failure's words), stopped (driver => true: its failures in a
 *               row stopped it), timed_out (bool)
 */
function pg_shipment_tracking_pass($lists, $ask, $apply, $deadline, $limit, $clock = 'time', $progress = null)
{
    $out = array(
        'last'      => array(),
        'asked'     => 0,
        'changed'   => 0,
        'delivered' => 0,
        'failed'    => 0,
        'errors'    => array(),
        'stopped'   => array(),
        'timed_out' => false,
    );
    $failures = array();

    foreach (pg_shipment_tracking_queue($lists) as $row) {
        $driver = (string) $row['driver'];

        if (isset($out['stopped'][$driver])) {
            continue;
        }

        if ($out['asked'] >= (int) $limit) {
            break;
        }

        $now = (int) call_user_func($clock);

        if ($now >= (int) $deadline) {
            $out['timed_out'] = true;
            break;
        }

        $decision = pg_shipment_tracking_decide($row, call_user_func($ask, $row), $now);
        $out['asked']++;
        $out['last'][$driver] = (int) $row['id'];

        if (!$decision['ok']) {
            $out['failed']++;
            $out['errors'][$driver] = $decision['error'];
            $failures[$driver] = (isset($failures[$driver]) ? $failures[$driver] : 0) + 1;

            if ($failures[$driver] >= PG_SHIPMENT_TRACKING_FAILURES) {
                $out['stopped'][$driver] = true;
            }
        } else {
            $failures[$driver] = 0;

            if ($decision['write'] || $decision['delivered']) {
                $out['changed']++;
                $out['delivered'] += $decision['delivered'] ? 1 : 0;
                call_user_func($apply, $row, $decision);
            }
        }

        if ($progress !== null) {
            call_user_func($progress, $driver, (int) $row['id']);
        }
    }

    return $out;
}

/**
 * Where a driver's next run starts: after the last parcel asked about, or
 * from the start once a run has asked about the last open parcel. Pure.
 *
 * @param int      $after the place this run started from
 * @param array    $rows  the driver's rows of this run, in id order
 * @param int|null $last  the last label id asked about, null for none
 * @param int      $limit the rows a run reads at most
 * @return int
 */
function pg_shipment_tracking_next_place($after, $rows, $last, $limit)
{
    $rows = array_values((array) $rows);

    if ($last === null) {
        return $rows ? (int) $after : 0;
    }

    $end = (int) $rows[count($rows) - 1]['id'];

    return (((int) $last >= $end) && (count($rows) < (int) $limit)) ? 0 : (int) $last;
}

/**
 * @return string
 */
function pg_shipment_tracking_state_path()
{
    return PG_FUNCTIONS_DIR . '/data/temp/shipment_tracking.json';
}

/**
 * The job's kept state from the file's text: each driver's place and when
 * its failures were last logged. Anything unreadable starts afresh. Pure.
 *
 * @param string $text
 * @return array after (driver => label id), logged (driver => unix time)
 */
function pg_shipment_tracking_state_read($text)
{
    $read = json_decode((string) $text, true);
    $state = array('after' => array(), 'logged' => array());

    foreach (array_keys($state) as $name) {
        if (is_array($read) && isset($read[$name]) && is_array($read[$name])) {
            foreach ($read[$name] as $driver => $value) {
                if (pg_label_key_valid($driver) && is_int($value) && ($value >= 0)) {
                    $state[$name][$driver] = $value;
                }
            }
        }
    }

    return $state;
}

/**
 * @return array pg_shipment_tracking_state_read()
 */
function pg_shipment_tracking_state()
{
    $path = pg_shipment_tracking_state_path();

    return pg_shipment_tracking_state_read(is_file($path) ? (string) @file_get_contents($path) : '');
}

/**
 * @param array $state pg_shipment_tracking_state_read()
 */
function pg_shipment_tracking_state_save($state)
{
    @file_put_contents(pg_shipment_tracking_state_path(), json_encode($state), LOCK_EX);
}

/**
 * Writes what a track() answer changes onto the parcel, through the shipment
 * write path only: its status, carrier and tracking page by
 * pg_shipment_record(), which updates the row and never adds one, then an
 * arrival by pg_shipment_delivered(), so its mail-merge fields already carry
 * the carrier's tracking page. A parcel removed from the order screen since
 * the run read it takes nothing: an arrival recorded for it would stamp the
 * address's other parcels.
 *
 * @param array $row      pg_shipment_tracking_open() row
 * @param array $decision pg_shipment_tracking_decide()
 */
function pg_shipment_tracking_apply($row, $decision)
{
    if (!$decision['write'] && !$decision['delivered']) {
        return;
    }

    $recorded = pg_shipment_record((int) $row['order_id'], (int) $row['ship_to_id'], (string) $row['number'], array(
        'source'        => 'carrier',
        'status'        => $decision['status'],
        'status_at'     => $decision['at'],
        'existing_only' => true,
    ) + $decision['options']);

    if (!$recorded['ok'] || !$decision['delivered']) {
        return;
    }

    pg_shipment_delivered((int) $row['ship_to_id'], $decision['at']);

    log_activity(lang(array(
        'string' => '{var:1}: {var:2}',
        'vars'   => array(lang('Shipment Tracking'), lang(array(
            'string' => '{var:1} was delivered',
            'vars'   => array(lang('Order') . ' ' . $row['order_number'] . ' (' . $row['number'] . ')')))))), 'UNKNOWN');
}

/**
 * One run of the job: the open parcels of every driver that tracks, asked
 * about within the time and row budgets, each answer written back. The
 * failures of a driver are logged once a day.
 *
 * @param int $seconds
 * @param int $limit
 * @return array asked, changed, delivered, failed
 */
function pg_shipment_tracking_run($seconds = PG_SHIPMENT_TRACKING_SECONDS, $limit = PG_SHIPMENT_TRACKING_BATCH)
{
    $result = array('asked' => 0, 'changed' => 0, 'delivered' => 0, 'failed' => 0);

    if (!pg_shipment_labels_ready() || !pg_shipment_rows_ready()) {
        return $result;
    }

    $drivers = pg_shipment_tracking_drivers();

    if (!$drivers) {
        return $result;
    }

    $now = time();
    $state = pg_shipment_tracking_state();
    $lists = array();
    $from = array();

    foreach ($drivers as $driver) {
        $from[$driver] = isset($state['after'][$driver]) ? $state['after'][$driver] : 0;
        $lists[$driver] = pg_shipment_tracking_open($driver, $from[$driver], $limit, $now);

        // Past the last open parcel: the round starts again.
        if (!$lists[$driver] && ($from[$driver] > 0)) {
            $from[$driver] = 0;
            $lists[$driver] = pg_shipment_tracking_open($driver, 0, $limit, $now);
        }
    }

    $accounts = array();

    $ask = function ($row) use (&$accounts) {
        $driver = (string) $row['driver'];

        if (!isset($accounts[$driver])) {
            $accounts[$driver] = pg_label_account(pg_label_fields($driver), pg_label_settings($driver));
        }

        return pg_label_call($driver, 'track', array((string) $row['reference'], $accounts[$driver]));
    };

    // The place is kept after every parcel, so a run the server stops still
    // moves the round on.
    $progress = function ($driver, $id) use (&$state) {
        $state['after'][$driver] = $id;
        pg_shipment_tracking_state_save($state);
    };

    $pass = pg_shipment_tracking_pass($lists, $ask, 'pg_shipment_tracking_apply', $now + (int) $seconds, $limit, 'time', $progress);

    foreach ($drivers as $driver) {
        $state['after'][$driver] = pg_shipment_tracking_next_place($from[$driver], $lists[$driver], isset($pass['last'][$driver]) ? $pass['last'][$driver] : null, $limit);
    }

    foreach ($pass['errors'] as $driver => $error) {
        $logged = isset($state['logged'][$driver]) ? $state['logged'][$driver] : 0;

        if (($now - $logged) >= 86400) {
            $definition = pg_label_driver($driver);

            log_activity(lang(array(
                'string' => '{var:1}: {var:2}',
                'vars'   => array(lang('Shipment Tracking'), $definition['label'] . ' - ' . $error))), 'UNKNOWN');

            $state['logged'][$driver] = $now;
        }
    }

    pg_shipment_tracking_state_save($state);

    return array(
        'asked'     => $pass['asked'],
        'changed'   => $pass['changed'],
        'delivered' => $pass['delivered'],
        'failed'    => $pass['failed'],
    );
}

/**
 * Adds the tracking job to the jobs the general job runs, when the general
 * job is the one running them; a site that schedules each job on its own
 * keeps its own schedule.
 *
 * @return bool the job is (now) in the list
 */
function pg_shipment_tracking_job_switch_on()
{
    if (!defined('JOB_DISPATCH_ENABLED') || !JOB_DISPATCH_ENABLED) {
        return false;
    }

    $list = pg_cron_dispatch_list();

    if (in_array('shipment_tracking_job', $list, true)) {
        return true;
    }

    $list[] = 'shipment_tracking_job';

    db("UPDATE config SET job_dispatch = '" . e(implode(',', $list)) . "'");

    return true;
}
