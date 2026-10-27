<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visitors table split into one partition per month.
 *
 * The retention period removes the rows of old days a chunk at a time
 * (pg_visitor_cleanup_run()), which keeps the site running but takes long on
 * a large backlog, and the room the rows leave stays inside the table's file
 * until the table is rebuilt. With a partition per month a whole month that
 * is older than the period goes with one ALTER TABLE ... DROP PARTITION: a
 * moment however many rows it holds, and its file goes back to the disk.
 *
 * The table is partitioned by RANGE on start_timestamp, the Unix time a visit
 * began, with one partition per month of the site's clock (named pYYYYMM
 * after the month it holds, its bound the first second of the next month)
 * and a last partition, pmax, for everything after the last month. pmax is
 * what keeps visits recorded when no month has been opened for them yet; the
 * general job opens the coming months ahead of time
 * (pg_visitor_partition_open_run()), which leaves pmax empty.
 *
 * Every unique key of a partitioned table has to hold the partitioning
 * column, so the primary key is (id, start_timestamp): id stays its first
 * column and keeps its AUTO_INCREMENT. Only InnoDB tables are partitioned
 * (MySQL 8 partitions no other engine), and only where the server has
 * partitioning at all: MySQL 8 always, MySQL 5.7 and MariaDB while their
 * partition plugin is active. Everywhere else nothing changes and the chunks
 * remove the rows as before.
 *
 * A table is split by the 2026.4.10 upgrade (10.7) when it is small enough to
 * be copied in seconds; a larger one, or one on MyISAM, by the general job,
 * which builds the partitioned copy next to it while the site keeps running
 * (pg_visitor_move_run() in visitor_retention.php).
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
 * Months opened past the current one, so that a general job that stops for a
 * while does not leave the visits of a new month in pmax.
 */
define('PG_VISITOR_PARTITION_AHEAD', 2);

/**
 * Months before the current one that get a partition of their own when a
 * table is split; rows older than that share the first one. A site that keeps
 * its records without a limit would otherwise get a partition, and a file,
 * for every month it has ever had.
 */
define('PG_VISITOR_PARTITION_PAST', 36);

/**
 * Visits up to which the upgrade splits the table itself. The ALTER TABLE
 * copies the table and every visit waits for it, a few seconds at this size;
 * a larger table is split by the general job (see above).
 */
define('PG_VISITOR_PARTITION_UPGRADE_MAX_ROWS', 250000);

/**
 * Rows in pmax up to which new months are still split off from it. Opening a
 * month copies the rows of pmax while every visit waits, so a pmax filled by
 * months of a general job that did not run is left as it is.
 */
define('PG_VISITOR_PARTITION_OVERFLOW_MAX_ROWS', 50000);

/**
 * The last partition, for every visit after the last month.
 */
define('PG_VISITOR_PARTITION_OVERFLOW', 'pmax');

/**
 * The first second of the month $timestamp falls in, by the site's clock.
 *
 * No database access.
 *
 * @param int $timestamp
 * @return int
 */
function pg_visitor_partition_month($timestamp)
{
    $timestamp = (int) $timestamp;

    return (int) mktime(0, 0, 0, (int) date('n', $timestamp), 1, (int) date('Y', $timestamp));
}

/**
 * The first second of the month after the one $timestamp falls in.
 *
 * No database access.
 *
 * @param int $timestamp
 * @return int
 */
function pg_visitor_partition_next_month($timestamp)
{
    $timestamp = (int) $timestamp;

    return (int) mktime(0, 0, 0, (int) date('n', $timestamp) + 1, 1, (int) date('Y', $timestamp));
}

/**
 * The name of the partition whose bound is $bound: p and the year and month
 * of its last second, so a partition is named after the month it holds.
 *
 * No database access.
 *
 * @param int $bound
 * @return string
 */
function pg_visitor_partition_name($bound)
{
    return 'p' . date('Ym', (int) $bound - 1);
}

/**
 * The months a table is split into: one partition for each month from the
 * month of its oldest visit, at most PG_VISITOR_PARTITION_PAST months back,
 * to PG_VISITOR_PARTITION_AHEAD months after the current one. pmax comes on
 * top and is not part of the list.
 *
 * No database access.
 *
 * @param int $oldest start_timestamp of the oldest visit, 0 when there is none
 * @param int $now
 * @return array name => bound, from the oldest month
 */
function pg_visitor_partition_plan($oldest, $now)
{
    $current = pg_visitor_partition_month($now);

    $first = ((int) $oldest > 0) ? pg_visitor_partition_month($oldest) : $current;

    $earliest = (int) mktime(0, 0, 0, (int) date('n', $current) - PG_VISITOR_PARTITION_PAST, 1, (int) date('Y', $current));
    $last = (int) mktime(0, 0, 0, (int) date('n', $current) + PG_VISITOR_PARTITION_AHEAD, 1, (int) date('Y', $current));

    $first = min(max($first, $earliest), $current);

    $plan = array();

    for ($month = $first; $month <= $last; $month = pg_visitor_partition_next_month($month)) {
        $bound = pg_visitor_partition_next_month($month);
        $plan[pg_visitor_partition_name($bound)] = $bound;
    }

    return $plan;
}

/**
 * The partitions as SQL: the list of monthly partitions and pmax.
 *
 * No database access.
 *
 * @param array $months name => bound
 * @return string
 */
function pg_visitor_partition_list_sql($months)
{
    $parts = array();

    foreach ($months as $name => $bound) {
        $parts[] = 'PARTITION `' . $name . '` VALUES LESS THAN (' . (int) $bound . ')';
    }

    $parts[] = 'PARTITION `' . PG_VISITOR_PARTITION_OVERFLOW . '` VALUES LESS THAN MAXVALUE';

    return '(' . implode(', ', $parts) . ')';
}

/**
 * What ALTER TABLE has to say to split a table into $months: any other
 * changes of the same statement, the primary key widened to
 * (id, start_timestamp) where it is (id), then the partitioning, which
 * follows the changes without a comma. null when the key is something else,
 * which this does not change.
 *
 * No database access.
 *
 * @param array $primary the columns of the primary key, in order
 * @param array $months  pg_visitor_partition_plan()
 * @param array $changes other ALTER TABLE changes, e.g. 'ENGINE=InnoDB'
 * @return string|null
 */
function pg_visitor_partition_alter_sql($primary, $months, $changes = array())
{
    $primary = array_map('strtolower', array_values((array) $primary));
    $clauses = array_values((array) $changes);

    if ($primary === array('id')) {
        $clauses[] = 'DROP PRIMARY KEY';
        $clauses[] = 'ADD PRIMARY KEY (`id`, `start_timestamp`)';
    } elseif ($primary !== array('id', 'start_timestamp')) {
        return null;
    }

    return ltrim(implode(', ', $clauses) . ' PARTITION BY RANGE (`start_timestamp`) ' . pg_visitor_partition_list_sql($months));
}

/**
 * How a table is partitioned, from its rows of information_schema.PARTITIONS.
 *
 * 'none' a table that is not partitioned (its one row has no partition name);
 * 'monthly' this file's layout: RANGE on start_timestamp, partitions with
 * rising bounds and pmax last; 'other' a table partitioned another way,
 * which is left alone; 'missing' no row at all.
 *
 * No database access.
 *
 * @param array $rows PARTITION_NAME, PARTITION_METHOD, PARTITION_EXPRESSION,
 *                    PARTITION_DESCRIPTION, in PARTITION_ORDINAL_POSITION order
 * @return array('state' => string, 'months' => array name => bound)
 */
function pg_visitor_partition_layout($rows)
{
    $layout = array('state' => 'missing', 'months' => array());

    $rows = array_values((array) $rows);

    if (count($rows) === 0) {
        return $layout;
    }

    if (($rows[0]['PARTITION_NAME'] === null) || ((string) $rows[0]['PARTITION_NAME'] === '')) {
        $layout['state'] = 'none';
        return $layout;
    }

    $layout['state'] = 'other';

    $last = array_pop($rows);

    if (((string) $last['PARTITION_NAME'] !== PG_VISITOR_PARTITION_OVERFLOW) || (strtoupper(trim((string) $last['PARTITION_DESCRIPTION'])) !== 'MAXVALUE')) {
        return $layout;
    }

    $bound = 0;
    $months = array();

    foreach (array_merge($rows, array($last)) as $row) {

        if ((strtoupper((string) $row['PARTITION_METHOD']) !== 'RANGE') || (strtolower(str_replace('`', '', trim((string) $row['PARTITION_EXPRESSION']))) !== 'start_timestamp')) {
            return $layout;
        }
    }

    // The names go into statements between backticks.
    foreach ($rows as $row) {

        $description = trim((string) $row['PARTITION_DESCRIPTION']);

        if ((preg_match('/^[0-9]+$/', $description) !== 1) || ((int) $description <= $bound) || (preg_match('/^[A-Za-z0-9_]+$/', (string) $row['PARTITION_NAME']) !== 1)) {
            return $layout;
        }

        $bound = (int) $description;
        $months[(string) $row['PARTITION_NAME']] = $bound;
    }

    $layout['state'] = 'monthly';
    $layout['months'] = $months;

    return $layout;
}

/**
 * The months to open after the last one, so that the table has a partition
 * for every month up to PG_VISITOR_PARTITION_AHEAD months after now. Each
 * starts where the one before it ends and ends with its month; a name the
 * table already has (after a change of the site's time zone) gets its bound
 * added.
 *
 * No database access.
 *
 * @param array $layout pg_visitor_partition_layout(), 'monthly'
 * @param int   $now
 * @return array name => bound
 */
function pg_visitor_partition_to_open($layout, $now)
{
    if (($layout['state'] !== 'monthly') || (count($layout['months']) === 0)) {
        return array();
    }

    $target = pg_visitor_partition_next_month((int) mktime(0, 0, 0, (int) date('n', (int) $now) + PG_VISITOR_PARTITION_AHEAD, 1, (int) date('Y', (int) $now)));

    $bound = (int) end($layout['months']);
    $open = array();

    while ($bound < $target) {

        $bound = pg_visitor_partition_next_month($bound);
        $name = pg_visitor_partition_name($bound);

        if (isset($layout['months'][$name]) || isset($open[$name])) {
            $name .= '_' . $bound;
        }

        $open[$name] = $bound;
    }

    return $open;
}

/**
 * The partitions that hold nothing but visits started before $before: every
 * month whose bound is at or before it, never the last month and never pmax.
 *
 * No database access.
 *
 * @param array $layout pg_visitor_partition_layout()
 * @param int   $before the first moment that is kept
 * @return array names, oldest first
 */
function pg_visitor_partition_to_drop($layout, $before)
{
    if (($layout['state'] !== 'monthly') || ((int) $before <= 0)) {
        return array();
    }

    $months = $layout['months'];

    // The last month stays: it is where the next months are opened from.
    array_pop($months);

    $drop = array();

    foreach ($months as $name => $bound) {
        if ((int) $bound <= (int) $before) {
            $drop[] = (string) $name;
        }
    }

    return $drop;
}

/**
 * The rows of information_schema.PARTITIONS for a table, in order.
 *
 * @param string $table
 * @return array
 */
function pg_visitor_partition_rows($table)
{
    $rows = array();

    if (!class_exists('db') || empty(db::$con)) {
        return $rows;
    }

    $result = db_q(
        "SELECT PARTITION_NAME, PARTITION_METHOD, PARTITION_EXPRESSION, PARTITION_DESCRIPTION
        FROM information_schema.PARTITIONS
        WHERE (TABLE_SCHEMA = DATABASE()) AND (TABLE_NAME = ?)
        ORDER BY PARTITION_ORDINAL_POSITION", array((string) $table));

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $rows[] = $row;
    }

    return $rows;
}

/**
 * How a table is partitioned now: pg_visitor_partition_layout() of its rows.
 *
 * @param string $table
 * @return array
 */
function pg_visitor_partition_read($table = 'visitors')
{
    return pg_visitor_partition_layout(pg_visitor_partition_rows($table));
}

/**
 * Whether this database server can partition an InnoDB table, and why not.
 *
 * MySQL 8 partitions InnoDB tables itself and lists no plugin for it; MySQL
 * 5.7 and MariaDB do it through the partition plugin, which a server can be
 * started without.
 *
 * @return array('ok' => bool, 'reason' => string)
 */
function pg_visitor_partition_support()
{
    static $support = null;

    if ($support !== null) {
        return $support;
    }

    if (!class_exists('db') || empty(db::$con)) {
        return array('ok' => false, 'reason' => lang('no database connection'));
    }

    $result = @mysqli_query(db::$con, "SELECT PLUGIN_STATUS FROM information_schema.PLUGINS WHERE PLUGIN_NAME = 'partition'");
    $row = $result ? mysqli_fetch_row($result) : null;

    if (is_array($row)) {

        $support = (strtoupper((string) $row[0]) === 'ACTIVE')
            ? array('ok' => true, 'reason' => '')
            : array('ok' => false, 'reason' => lang('the partition plugin of the database server is not active'));

        return $support;
    }

    $server = pg_db_server_version(mysqli_get_server_info(db::$con));

    $support = (($server['product'] === 'MySQL') && ($server['version'] !== '') && version_compare($server['version'], '8.0.0', '>='))
        ? array('ok' => true, 'reason' => '')
        : array('ok' => false, 'reason' => lang('the database server has no partitioning'));

    return $support;
}

/**
 * The columns of a table's primary key, in order, lower case.
 *
 * @param string $table
 * @return array
 */
function pg_visitor_partition_primary($table)
{
    $columns = array();

    $result = db_q(
        "SELECT COLUMN_NAME
        FROM information_schema.STATISTICS
        WHERE (TABLE_SCHEMA = DATABASE()) AND (TABLE_NAME = ?) AND (INDEX_NAME = 'PRIMARY')
        ORDER BY SEQ_IN_INDEX", array((string) $table));

    while ($result && ($row = mysqli_fetch_row($result))) {
        $columns[] = strtolower((string) $row[0]);
    }

    return $columns;
}

/**
 * The plan for splitting a table now, from its oldest visit: read through
 * the start_timestamp index.
 *
 * @param string $table
 * @return array pg_visitor_partition_plan()
 */
function pg_visitor_partition_plan_for($table)
{
    $result = @mysqli_query(db::$con, "SELECT MIN(start_timestamp) FROM `" . str_replace('`', '', (string) $table) . "` WHERE start_timestamp > 0");
    $row = $result ? mysqli_fetch_row($result) : null;

    return pg_visitor_partition_plan(is_array($row) ? (int) $row[0] : 0, time());
}

/**
 * Split `visitors` into months in place with one ALTER TABLE, when it is on
 * InnoDB, this server can partition it and it holds at most $max_rows
 * visits. The statement copies the table and every visit waits until it
 * ends, which is why a larger table is left to the general job.
 *
 * Safe to run again: a table that is already split is left as it is.
 *
 * @param int $max_rows
 * @return array('state' => 'done'|'already'|'missing'|'other'|'engine'|
 *               'unsupported'|'key'|'large'|'busy'|'failed', 'rows' => int,
 *               'months' => int, 'seconds' => float, 'error' => string)
 */
function pg_visitor_partition_convert($max_rows)
{
    $result = array('state' => 'failed', 'rows' => 0, 'months' => 0, 'seconds' => 0.0, 'error' => '');

    if (!class_exists('db') || empty(db::$con)) {
        $result['error'] = lang('no database connection');
        return $result;
    }

    $layout = pg_visitor_partition_read('visitors');

    if ($layout['state'] !== 'none') {
        $result['state'] = ($layout['state'] === 'monthly') ? 'already' : $layout['state'];
        $result['months'] = count($layout['months']);
        return $result;
    }

    $engine = pg_visitor_move_engines();

    if ($engine['visitors'] !== 'innodb') {
        $result['state'] = ($engine['visitors'] === '') ? 'missing' : 'engine';
        return $result;
    }

    $support = pg_visitor_partition_support();

    if (!$support['ok']) {
        $result['state'] = 'unsupported';
        $result['error'] = $support['reason'];
        return $result;
    }

    $plan = pg_visitor_partition_plan_for('visitors');
    $alter = pg_visitor_partition_alter_sql(pg_visitor_partition_primary('visitors'), $plan);

    if ($alter === null) {
        $result['state'] = 'key';
        return $result;
    }

    // Counted no further than one past the limit.
    $result['rows'] = pg_visitor_move_number("SELECT COUNT(*) FROM (SELECT 1 FROM visitors LIMIT " . ((int) $max_rows + 1) . ") AS visitor_rows");

    if ($result['rows'] > (int) $max_rows) {
        $result['state'] = 'large';
        return $result;
    }

    $run = pg_db_run_table_statement('visitors', "ALTER TABLE {table} " . $alter, array(
        'state' => 'failed',
        'table' => 'visitors',
        'seconds' => 0.0,
        'messages' => array(),
        'errno' => 0,
        'error' => '',
        'running_seconds' => 0,
    ), '', 20);

    $result['seconds'] = (float) $run['seconds'];

    if ($run['state'] === 'ran') {
        $result['state'] = 'done';
        $result['months'] = count($plan);
    } elseif (in_array($run['state'], array('busy', 'running'), true)) {
        $result['state'] = 'busy';
    } else {
        $result['error'] = ($run['errno'] > 0) ? 'MySQL ' . (int) $run['errno'] . ': ' . $run['error'] : (string) $run['error'];
    }

    return $result;
}

/**
 * Run one ALTER TABLE on the months of `visitors`: no long statement of this
 * database may be running (the ALTER would wait for it, and every visit
 * behind the ALTER), and the wait for the table is 2 s at most.
 *
 * @param string $clauses what follows ALTER TABLE `visitors`
 * @return array('state' => 'ran'|'in_use'|'failed', 'error' => string)
 */
function pg_visitor_partition_alter($clauses)
{
    if (pg_visitor_shrink_long_statement() > 0) {
        return array('state' => 'in_use', 'error' => '');
    }

    $run = pg_db_run_table_statement('visitors', "ALTER TABLE {table} " . $clauses, array(
        'state' => 'failed',
        'table' => 'visitors',
        'seconds' => 0.0,
        'messages' => array(),
        'errno' => 0,
        'error' => '',
        'running_seconds' => 0,
    ), '', 2);

    if ($run['state'] === 'ran') {
        return array('state' => 'ran', 'error' => '');
    }

    if (in_array($run['state'], array('busy', 'running'), true)) {
        return array('state' => 'in_use', 'error' => '');
    }

    return array('state' => 'failed', 'error' => ($run['errno'] > 0) ? 'MySQL ' . (int) $run['errno'] . ': ' . $run['error'] : (string) $run['error']);
}

/**
 * Drop the months of `visitors` that hold only visits started before
 * $before. Run by the cleanup, inside its lock, before it removes the rest a
 * chunk at a time: what is left for the chunks is the month that is only
 * partly older than the period.
 *
 * The rows of those months are counted first, from their smallest index, so
 * the cleanup can say how many records went.
 *
 * @param int   $before   the first moment that is kept
 * @param float $deadline microtime of the end of the run
 * @return array('months' => int, 'rows' => int, 'error' => string)
 */
function pg_visitor_partition_drop_run($before, $deadline)
{
    $result = array('months' => 0, 'rows' => 0, 'error' => '');

    $drop = pg_visitor_partition_to_drop(pg_visitor_partition_read('visitors'), $before);

    if ((count($drop) === 0) || (microtime(true) >= $deadline)) {
        return $result;
    }

    $list = '`' . implode('`, `', $drop) . '`';

    $rows = pg_visitor_move_number("SELECT COUNT(*) FROM visitors PARTITION (" . $list . ")");

    $run = pg_visitor_partition_alter("DROP PARTITION " . $list);

    if ($run['state'] === 'ran') {
        $result['months'] = count($drop);
        $result['rows'] = $rows;
    } else {
        $result['error'] = $run['error'];
    }

    return $result;
}

/**
 * The file that remembers the last look at the coming months.
 *
 * @return string
 */
function pg_visitor_partition_marker()
{
    return PG_FUNCTIONS_DIR . '/data/temp/visitor_partitions.json';
}

/**
 * What the marker file holds, every key present.
 *
 * state: '' never looked, 'none' the table is not split into months,
 * 'ready' the coming months are there, 'waiting' a month is missing and was
 * not opened ('reason': 'in_use', 'overflow'), 'failed' ('error').
 *
 * @return array
 */
function pg_visitor_partition_last()
{
    $last = @json_decode((string) @file_get_contents(pg_visitor_partition_marker()), true);

    if (!is_array($last)) {
        $last = array();
    }

    return array(
        'state' => isset($last['state']) ? (string) $last['state'] : '',
        'reason' => isset($last['reason']) ? (string) $last['reason'] : '',
        'error' => isset($last['error']) ? (string) $last['error'] : '',
        'checked_at' => isset($last['checked_at']) ? (int) $last['checked_at'] : 0,
        'opened' => isset($last['opened']) ? array_map('strval', (array) $last['opened']) : array(),
        'overflow' => isset($last['overflow']) ? (int) $last['overflow'] : 0,
    );
}

/**
 * Write the marker file.
 *
 * @param array $last pg_visitor_partition_last() with the changes
 */
function pg_visitor_partition_save($last)
{
    $marker = pg_visitor_partition_marker();

    if (!is_dir(dirname($marker))) {
        @mkdir(dirname($marker), 0755, true);
    }

    @file_put_contents($marker, json_encode($last), LOCK_EX);
}

/**
 * Whether the general job should look at the coming months now: once a day,
 * once an hour while a month is missing.
 *
 * @return bool
 */
function pg_visitor_partition_due()
{
    $last = pg_visitor_partition_last();

    $every = in_array($last['state'], array('waiting', 'failed'), true) ? 3600 : 86400;

    return $last['checked_at'] < (time() - $every);
}

/**
 * Open the months `visitors` does not have yet, up to
 * PG_VISITOR_PARTITION_AHEAD months after the current one: pmax is
 * reorganized into those months and a new, empty pmax.
 *
 * While pmax is empty that only creates the new partitions, a moment in which
 * the table is held. Visits that landed in pmax are copied into the new
 * months while every visit waits, so a pmax of more than
 * PG_VISITOR_PARTITION_OVERFLOW_MAX_ROWS rows is left as it is: those visits
 * stay recorded there, and the cleanup removes them a chunk at a time.
 *
 * Takes the cleanup's lock, so it never runs during the cleanup, the move or
 * the rebuild.
 *
 * @return array('state' => 'busy'|'none'|'ready'|'opened'|'waiting'|'failed',
 *               'opened' => array, 'text' => string)
 */
function pg_visitor_partition_open_run()
{
    $result = array('state' => 'busy', 'opened' => array(), 'text' => '');

    if (!class_exists('db') || empty(db::$con)) {
        return $result;
    }

    if (pg_visitor_move_number("SELECT GET_LOCK(" . pg_visitor_cleanup_lock_sql() . ", 0)") !== 1) {
        return $result;
    }

    $last = pg_visitor_partition_last();
    $last['checked_at'] = time();
    $last['reason'] = '';
    $last['error'] = '';
    $last['overflow'] = 0;

    $layout = pg_visitor_partition_read('visitors');

    if ($layout['state'] !== 'monthly') {
        $last['state'] = 'none';
    } else {

        $open = pg_visitor_partition_to_open($layout, time());

        if (count($open) === 0) {
            $last['state'] = 'ready';
        } else {

            $last['overflow'] = pg_visitor_move_number(
                "SELECT COUNT(*) FROM (SELECT 1 FROM visitors PARTITION (`" . PG_VISITOR_PARTITION_OVERFLOW . "`) LIMIT " . (PG_VISITOR_PARTITION_OVERFLOW_MAX_ROWS + 1) . ") AS visitor_rows");

            if ($last['overflow'] > PG_VISITOR_PARTITION_OVERFLOW_MAX_ROWS) {
                $last['state'] = 'waiting';
                $last['reason'] = 'overflow';
            } else {

                $run = pg_visitor_partition_alter("REORGANIZE PARTITION `" . PG_VISITOR_PARTITION_OVERFLOW . "` INTO " . pg_visitor_partition_list_sql($open));

                if ($run['state'] === 'ran') {
                    $last['state'] = 'ready';
                    $last['opened'] = array_keys($open);
                    $result['opened'] = array_keys($open);
                } elseif ($run['state'] === 'in_use') {
                    $last['state'] = 'waiting';
                    $last['reason'] = 'in_use';
                } else {
                    $last['state'] = 'failed';
                    $last['error'] = $run['error'];
                    $result['text'] = lang(array(
                        'string' => 'Opening the coming months of the visitors table stopped: {var:1}. Visits are recorded all the same, in the partition for later visits.',
                        'vars' => array($run['error']),
                    ));
                }
            }
        }
    }

    pg_visitor_partition_save($last);

    pg_visitor_move_query("SELECT RELEASE_LOCK(" . pg_visitor_cleanup_lock_sql() . ")");

    $result['state'] = (count($result['opened']) > 0) ? 'opened' : $last['state'];

    return $result;
}

/**
 * The year and month a partition bound ends, as the card shows it (2026-07).
 *
 * No database access.
 *
 * @param int $bound
 * @return string
 */
function pg_visitor_partition_month_label($bound)
{
    return date('Y-m', (int) $bound - 1);
}

/**
 * How the table stands with its months, in a few words for the Visitor
 * records card.
 *
 * @param array  $layout  pg_visitor_partition_read()
 * @param string $engine  of `visitors`, lower case
 * @param array  $support pg_visitor_partition_support()
 * @return string
 */
function pg_visitor_partition_text($layout, $engine, $support)
{
    if ($layout['state'] === 'monthly') {

        $bounds = array_values($layout['months']);
        $text = lang(array(
            'string' => 'One per month, {var:1} months: {var:2} to {var:3}',
            'vars' => array(pg_format_number(count($bounds), 0), pg_visitor_partition_month_label(reset($bounds)), pg_visitor_partition_month_label(end($bounds))),
        ));

        $last = pg_visitor_partition_last();

        if (($last['state'] === 'waiting') && ($last['reason'] === 'overflow')) {
            $text .= '. ' . lang(array(
                'string' => '{var:1}+ visits are past the last month, so no new month is opened; they are kept and removed a chunk at a time.',
                'vars' => array(pg_format_number(PG_VISITOR_PARTITION_OVERFLOW_MAX_ROWS, 0)),
            ));
        } elseif ($last['state'] === 'failed') {
            $text .= '. ' . lang(array('string' => 'Opening the next month stopped: {var:1}', 'vars' => array($last['error'])));
        }

        return $text;
    }

    if ($layout['state'] === 'other') {
        return lang('Partitioned in another way, left as it is');
    }

    if (!$support['ok']) {
        return lang(array('string' => 'Not split: {var:1}', 'vars' => array($support['reason'])));
    }

    if ($engine === 'myisam') {
        return lang('Not split yet: that comes with the move to InnoDB');
    }

    return lang('Not split yet: see above');
}
