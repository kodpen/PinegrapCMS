<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Looking after the database's tables: the catalog of every table with its
 * engine, size and free space, the facts of the database server, OPTIMIZE /
 * CHECK / REPAIR TABLE on one table, and the findings the Database Engine
 * screen (database_engine.php) lists from all of them.
 *
 * db() is not used for the maintenance statements and for information_schema.
 * It answers a failed query with output_error() and ends the request; here a
 * failure is one of the answers - a table the server cannot open, a variable
 * an older server does not know, a lock that could not be had - and the
 * screen has to go on and say so. The plain mysqli calls return false
 * instead, as in includes/fn/innodb.php.
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
 * Every base table of this database, largest first, from one
 * information_schema query.
 *
 * A table whose ENGINE comes back NULL is one whose definition the server
 * could not read; it stays in the list with engine '' (a finding of its own,
 * see pg_db_table_findings()).
 *
 * MySQL 8 serves these statistics from a cache refreshed once a day by
 * default; the session asks for fresh ones first, as pg_innodb_table_status()
 * does. The variable is unknown to MySQL 5.7 and MariaDB and the failed SET
 * is ignored.
 *
 * @return array table => array('engine' => lower case, 'rows' => int,
 *               'data' => float, 'index' => float, 'bytes' => float (data +
 *               index), 'free' => float, 'collation' => string,
 *               'row_format' => string, 'auto_increment' => int|null,
 *               'updated' => string|null)
 */
function pg_db_table_catalog()
{
    static $fresh_statistics = false;

    if (!class_exists('db') || empty(db::$con)) {
        return array();
    }

    if (!$fresh_statistics) {
        $fresh_statistics = true;
        @mysqli_query(db::$con, "SET SESSION information_schema_stats_expiry = 0");
    }

    $result = @mysqli_query(db::$con,
        "SELECT
            TABLE_NAME, ENGINE, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, DATA_FREE,
            TABLE_COLLATION, ROW_FORMAT, AUTO_INCREMENT, UPDATE_TIME
        FROM information_schema.TABLES
        WHERE
            (TABLE_SCHEMA = DATABASE())
            AND (TABLE_TYPE = 'BASE TABLE')");

    if (!$result) {
        return array();
    }

    $catalog = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $data = (float) $row['DATA_LENGTH'];
        $index = (float) $row['INDEX_LENGTH'];

        $catalog[(string) $row['TABLE_NAME']] = array(
            'engine' => ($row['ENGINE'] !== null) ? strtolower((string) $row['ENGINE']) : '',
            'rows' => (int) $row['TABLE_ROWS'],
            'data' => $data,
            'index' => $index,
            'bytes' => $data + $index,
            'free' => (float) $row['DATA_FREE'],
            'collation' => (string) $row['TABLE_COLLATION'],
            'row_format' => (string) $row['ROW_FORMAT'],
            'auto_increment' => ($row['AUTO_INCREMENT'] !== null) ? (int) $row['AUTO_INCREMENT'] : null,
            'updated' => ($row['UPDATE_TIME'] !== null) ? (string) $row['UPDATE_TIME'] : null,
        );
    }

    uasort($catalog, function ($a, $b) {
        if ($a['bytes'] == $b['bytes']) {
            return 0;
        }
        return ($a['bytes'] > $b['bytes']) ? -1 : 1;
    });

    return $catalog;
}

/**
 * What the database server is and how it is set up, for the screen to show.
 *
 * Each value is asked on its own: a variable that one server or version does
 * not have (tx_isolation is gone from MySQL 8, transaction_isolation is new
 * in MariaDB 11.1) leaves that value null and nothing else.
 *
 * @return array('version', 'database', 'charset', 'collation',
 *               'connection_charset', 'page_size', 'buffer_pool',
 *               'file_per_table', 'row_format', 'strict_mode',
 *               'max_allowed_packet', 'default_engine', 'sql_mode',
 *               'isolation', 'wait_timeout', 'max_connections', 'uptime',
 *               'threads_connected'), every value a string or null
 */
function pg_db_server_facts()
{
    $facts = array(
        'version' => null,
        'database' => null,
        'charset' => null,
        'collation' => null,
        'connection_charset' => null,
        'page_size' => null,
        'buffer_pool' => null,
        'file_per_table' => null,
        'row_format' => null,
        'strict_mode' => null,
        'max_allowed_packet' => null,
        'default_engine' => null,
        'sql_mode' => null,
        'isolation' => null,
        'wait_timeout' => null,
        'max_connections' => null,
        'uptime' => null,
        'threads_connected' => null,
    );

    if (!class_exists('db') || empty(db::$con)) {
        return $facts;
    }

    // The first column of the first row, or null.
    $value = function ($sql, $column = 0) {
        $result = @mysqli_query(db::$con, $sql);
        if (!$result) {
            return null;
        }
        $row = mysqli_fetch_row($result);
        return (is_array($row) && isset($row[$column])) ? (string) $row[$column] : null;
    };

    $facts['version'] = $value("SELECT VERSION()");
    $facts['database'] = $value("SELECT DATABASE()");
    $facts['charset'] = $value("SELECT @@character_set_database");
    $facts['collation'] = $value("SELECT @@collation_database");
    $facts['page_size'] = $value("SELECT @@innodb_page_size");
    $facts['buffer_pool'] = $value("SELECT @@innodb_buffer_pool_size");
    $facts['file_per_table'] = $value("SELECT @@innodb_file_per_table");
    $facts['row_format'] = $value("SELECT @@innodb_default_row_format");
    $facts['strict_mode'] = $value("SELECT @@innodb_strict_mode");
    $facts['max_allowed_packet'] = $value("SELECT @@max_allowed_packet");
    $facts['default_engine'] = $value("SELECT @@default_storage_engine");
    $facts['sql_mode'] = $value("SELECT @@sql_mode");
    $facts['isolation'] = $value("SELECT @@transaction_isolation");

    if ($facts['isolation'] === null) {
        $facts['isolation'] = $value("SELECT @@tx_isolation");
    }

    $facts['wait_timeout'] = $value("SELECT @@wait_timeout");
    $facts['max_connections'] = $value("SELECT @@max_connections");
    $facts['uptime'] = $value("SHOW STATUS LIKE 'Uptime'", 1);
    $facts['threads_connected'] = $value("SHOW STATUS LIKE 'Threads_connected'", 1);

    $connection_charset = @mysqli_character_set_name(db::$con);
    $facts['connection_charset'] = ($connection_charset !== false && $connection_charset !== null) ? (string) $connection_charset : null;

    return $facts;
}

/**
 * A statement on this table that another connection is running right now,
 * of the kinds given: typically the ALTER or OPTIMIZE a timed-out request
 * started, which the server carries on with after the web server has given
 * up on the request. Starting another one would only queue behind its
 * metadata lock, and everything else that touches the table would queue
 * behind both.
 *
 * With $table '' any table will do: the screen asks whether anything of the
 * kind is running at all, in one read of the list instead of one per table.
 *
 * Without the PROCESS privilege information_schema.PROCESSLIST still lists
 * the threads of the same database user, and those are the ones that matter
 * here: the software's own requests.
 *
 * @param string $table
 * @param array  $verbs statement beginnings, e.g. 'OPTIMIZE TABLE'
 * @return array|null the PROCESSLIST row (ID, TIME, STATE, INFO), or null when
 *                    there is none or the list cannot be read
 */
function pg_db_running_statement($table, $verbs = array('ALTER TABLE', 'OPTIMIZE TABLE', 'REPAIR TABLE', 'CHECK TABLE'))
{
    if (!class_exists('db') || empty(db::$con)) {
        return null;
    }

    $likes = array();
    $verb_patterns = array();

    foreach ((array) $verbs as $verb) {
        $verb = trim((string) $verb);
        if ($verb === '') {
            continue;
        }
        $likes[] = "(INFO LIKE '" . mysqli_real_escape_string(db::$con, $verb) . "%')";
        $verb_patterns[] = preg_replace('/\s+/', '\s+', preg_quote($verb, '/'));
    }

    if (count($likes) == 0) {
        return null;
    }

    $result = @mysqli_query(db::$con,
        "SELECT ID, TIME, STATE, INFO
        FROM information_schema.PROCESSLIST
        WHERE
            (ID <> CONNECTION_ID())
            AND (INFO IS NOT NULL)
            AND (" . implode(' OR ', $likes) . ")");

    if (!$result) {
        return null;
    }

    // The table is the first name after the verb, or any name of the list
    // OPTIMIZE, CHECK and REPAIR accept ("CHECK TABLE a, b").
    if ((string) $table === '') {
        $pattern = '/^\s*(?:' . implode('|', $verb_patterns) . ')\s/i';
    } else {
        $pattern = '/(?:' . implode('|', $verb_patterns) . ')\s+(?:`?[A-Za-z0-9_$]+`?\s*,\s*)*`?'
            . preg_quote((string) $table, '/') . '`?(?![A-Za-z0-9_$])/i';
    }

    while ($row = mysqli_fetch_assoc($result)) {
        if (preg_match($pattern, (string) $row['INFO'])) {
            return $row;
        }
    }

    return null;
}

/**
 * The file that says an OPTIMIZE TABLE has started and not yet ended:
 * {"table": ..., "started": unix time, "pid": ...}. Separate from the
 * conversion marker (pg_innodb_temp_marker()): an OPTIMIZE that died would
 * otherwise read as an aborted conversion of the same table.
 *
 * @return string
 */
function pg_db_optimize_marker()
{
    return PG_FUNCTIONS_DIR . '/data/temp/db_optimize.json';
}

/**
 * Runs one table maintenance statement (OPTIMIZE, CHECK or REPAIR TABLE) the
 * way all three are run: the name is checked against the database's own list,
 * nothing starts while another maintenance statement on the table is running,
 * the wait for the metadata lock is bounded, and the rows the server answers
 * with come back as 'messages'.
 *
 * These statements report most failures as result rows (Msg_type 'error')
 * rather than as a failed query; both end up in the answer.
 *
 * @param string $table
 * @param string $statement the statement, with {table} where the quoted name
 *                          goes
 * @param array  $result    the answer to fill in
 * @param string $marker    a marker file to hold for the statement's duration,
 *                          or ''
 * @return array $result with 'state' 'missing', 'running', 'busy', 'failed'
 *               or 'ran', and 'catalog' => the table's catalog row before
 */
function pg_db_run_table_statement($table, $statement, $result, $marker = '')
{
    // The name goes into the statement between backticks: it must look like
    // one of the software's names and be a table this database has.
    if (preg_match('/^[a-z0-9_]+$/', $table) !== 1) {
        $result['error'] = lang('invalid table name');
        return $result;
    }

    if (!class_exists('db') || empty(db::$con)) {
        $result['error'] = lang('no database connection');
        return $result;
    }

    $catalog = pg_db_table_catalog();

    if (!isset($catalog[$table])) {
        $result['state'] = 'missing';
        return $result;
    }

    $result['catalog'] = $catalog[$table];

    $running = pg_db_running_statement($table);

    if ($running !== null) {
        $result['state'] = 'running';
        $result['running_seconds'] = (int) $running['TIME'];
        return $result;
    }

    if ($marker !== '') {
        $marker_directory = dirname($marker);

        if (!is_dir($marker_directory)) {
            @mkdir($marker_directory, 0755, true);
        }

        @file_put_contents($marker, json_encode(array(
            'table' => $table,
            'started' => time(),
            'pid' => (function_exists('getmypid') ? getmypid() : 0),
        )), LOCK_EX);
    }

    // A bounded wait for the metadata lock, put back afterwards: the same
    // connection carries on with the rest of the request. Without it the
    // statement waits as long as the server's default (a year), and every
    // query on the table queues behind the waiting statement.
    $previous_wait = null;

    $previous_result = @mysqli_query(db::$con, "SELECT @@SESSION.lock_wait_timeout");

    if ($previous_result) {
        $previous_row = mysqli_fetch_row($previous_result);
        if (isset($previous_row[0])) {
            $previous_wait = (int) $previous_row[0];
        }
    }

    @mysqli_query(db::$con, "SET SESSION lock_wait_timeout = 20");

    $started = microtime(true);

    $done = @mysqli_query(db::$con, str_replace('{table}', '`' . $table . '`', $statement));

    $result['seconds'] = round(microtime(true) - $started, 1);

    $errors = array();

    if (!$done) {
        $result['errno'] = (int) mysqli_errno(db::$con);
        $result['error'] = (string) mysqli_error(db::$con);
    } elseif ($done instanceof mysqli_result) {
        while ($row = mysqli_fetch_assoc($done)) {
            $message = array(
                'Table' => isset($row['Table']) ? (string) $row['Table'] : '',
                'Op' => isset($row['Op']) ? (string) $row['Op'] : '',
                'Msg_type' => isset($row['Msg_type']) ? (string) $row['Msg_type'] : '',
                'Msg_text' => isset($row['Msg_text']) ? (string) $row['Msg_text'] : '',
            );
            $result['messages'][] = $message;
            if (strtolower($message['Msg_type']) === 'error') {
                $errors[] = $message['Msg_text'];
            }
        }
    }

    if ($previous_wait !== null) {
        @mysqli_query(db::$con, "SET SESSION lock_wait_timeout = " . $previous_wait);
    }

    if ($marker !== '') {
        @unlink($marker);
    }

    $lock_wait = ($result['errno'] == 1205);

    foreach ($errors as $error) {
        if (stripos($error, 'lock wait timeout') !== false) {
            $lock_wait = true;
        }
    }

    if ($lock_wait) {
        $result['state'] = 'busy';
    } elseif (!$done) {
        $result['state'] = 'failed';
    } elseif (count($errors) > 0) {
        $result['state'] = 'failed';
        $result['error'] = implode('; ', array_unique($errors));
    } else {
        $result['state'] = 'ran';
    }

    return $result;
}

/**
 * The health caches that a CHECK or REPAIR outdates: the hourly table sweep
 * and the System Status widget built on it.
 */
function pg_db_forget_health_caches()
{
    foreach (array('db_health_cache.json', 'system_status_cache.json') as $cache) {
        $cache_file = PG_FUNCTIONS_DIR . '/data/temp/' . $cache;
        if (file_exists($cache_file)) {
            @unlink($cache_file);
        }
    }
}

/**
 * OPTIMIZE TABLE on one table: gives the free space inside it back.
 *
 * On MyISAM it rewrites the data file without the holes deleted rows left
 * and writes wait meanwhile; on InnoDB it rebuilds the table (the server
 * answers with the note "Table does not support optimize, doing recreate +
 * analyze instead", which is not an error) while the site keeps writing.
 * Either way it may run for minutes on a large table, so the marker file
 * says it is under way: a request that dies under it leaves the marker, and
 * the screen reports the OPTIMIZE that did not finish.
 *
 * The answer's 'state':
 *   missing   the table is not in this database
 *   running   another connection is running a maintenance statement on it
 *             ('running_seconds')
 *   busy      the table's lock could not be had within 20 s (MySQL 1205)
 *   failed    the statement failed, or a row of its answer is an error
 *             ('errno', 'error')
 *   done      optimized; 'bytes_after' and 'free_after' read afresh
 *
 * @param string $table
 * @return array('state', 'table', 'seconds', 'bytes_before', 'bytes_after',
 *               'free_before', 'free_after', 'messages', 'errno', 'error',
 *               'running_seconds')
 */
function pg_db_optimize_table($table)
{
    $table = (string) $table;

    $result = array(
        'state' => 'failed',
        'table' => $table,
        'seconds' => 0.0,
        'bytes_before' => 0.0,
        'bytes_after' => 0.0,
        'free_before' => 0.0,
        'free_after' => 0.0,
        'messages' => array(),
        'errno' => 0,
        'error' => '',
        'running_seconds' => 0,
    );

    $result = pg_db_run_table_statement($table, "OPTIMIZE TABLE {table}", $result, pg_db_optimize_marker());

    if (isset($result['catalog'])) {
        $result['bytes_before'] = $result['catalog']['bytes'];
        $result['free_before'] = $result['catalog']['free'];
        $result['bytes_after'] = $result['catalog']['bytes'];
        $result['free_after'] = $result['catalog']['free'];
        unset($result['catalog']);
    }

    if ($result['state'] === 'ran') {
        $result['state'] = 'done';

        $after = pg_db_table_catalog();

        if (isset($after[$table])) {
            $result['bytes_after'] = $after[$table]['bytes'];
            $result['free_after'] = $after[$table]['free'];
        }
    }

    return $result;
}

/**
 * CHECK TABLE ... MEDIUM on one table: reads every row and checks the links
 * between rows and index entries.
 *
 * The answer's 'state':
 *   missing, running, busy, failed  as for pg_db_optimize_table()
 *   ok      every row of the answer says OK, or is a status or note without
 *           "error" or "corrupt" in it
 *   issue   a row of the answer is a warning or an error, or mentions an
 *           error or corruption ('messages' says what)
 *
 * @param string $table
 * @return array('state', 'table', 'seconds', 'messages', 'errno', 'error',
 *               'running_seconds')
 */
function pg_db_check_table($table)
{
    $table = (string) $table;

    $result = array(
        'state' => 'failed',
        'table' => $table,
        'seconds' => 0.0,
        'messages' => array(),
        'errno' => 0,
        'error' => '',
        'running_seconds' => 0,
    );

    $result = pg_db_run_table_statement($table, "CHECK TABLE {table} MEDIUM", $result);

    unset($result['catalog']);

    // A failed row is a finding of the check here, not a failure of it:
    // MyISAM answers a crashed table with Msg_type 'error'.
    if (($result['state'] === 'ran') || (($result['state'] === 'failed') && ($result['errno'] == 0) && (count($result['messages']) > 0))) {

        $issue = false;

        foreach ($result['messages'] as $message) {
            $type = strtolower($message['Msg_type']);
            $text = $message['Msg_text'];

            if (in_array($type, array('error', 'warning'), true)
                || (stripos($text, 'error') !== false)
                || (stripos($text, 'corrupt') !== false)) {
                $issue = true;
            }
        }

        $result['state'] = $issue ? 'issue' : 'ok';
        $result['error'] = '';
    }

    if (in_array($result['state'], array('ok', 'issue', 'failed'), true)) {
        pg_db_forget_health_caches();
    }

    return $result;
}

/**
 * REPAIR TABLE on one table, for the engines that have one (MyISAM, Aria,
 * MERGE). InnoDB has no REPAIR: it recovers from its own log when the server
 * starts, and a damaged InnoDB table is restored from a backup.
 *
 * The answer's 'state':
 *   missing, running, busy, failed  as for pg_db_optimize_table()
 *   unsupported  the table's engine cannot be repaired ('error' says why)
 *   repaired     the server answered without an error
 *
 * @param string $table
 * @return array('state', 'table', 'engine', 'seconds', 'messages', 'errno',
 *               'error', 'running_seconds')
 */
function pg_db_repair_table($table)
{
    $table = (string) $table;

    $result = array(
        'state' => 'failed',
        'table' => $table,
        'engine' => '',
        'seconds' => 0.0,
        'messages' => array(),
        'errno' => 0,
        'error' => '',
        'running_seconds' => 0,
    );

    if ((preg_match('/^[a-z0-9_]+$/', $table) === 1) && class_exists('db') && !empty(db::$con)) {

        $catalog = pg_db_table_catalog();

        if (isset($catalog[$table])) {

            $result['engine'] = $catalog[$table]['engine'];

            if (!in_array($result['engine'], array('myisam', 'aria', 'mrg_myisam', 'isam'), true)) {
                $result['state'] = 'unsupported';
                $result['error'] = lang('InnoDB tables cannot be repaired with REPAIR TABLE; InnoDB recovers from its own log, and a damaged InnoDB table is restored from a backup.');
                return $result;
            }
        }
    }

    $result = pg_db_run_table_statement($table, "REPAIR TABLE {table}", $result);

    unset($result['catalog']);

    if ($result['state'] === 'ran') {
        $result['state'] = 'repaired';
    }

    if (in_array($result['state'], array('repaired', 'failed'), true)) {
        pg_db_forget_health_caches();
    }

    return $result;
}

/**
 * The hourly table sweep (check_and_repair_database_tables(), the routine
 * one behind the System Status widget, not the deep one), reduced to the
 * tables that need a look.
 *
 * The routine sweep checks the MyISAM family with CHECK TABLE FAST QUICK and
 * every table for a readable definition; InnoDB tables are read through only
 * by the deep sweep ("Check all tables").
 *
 * @return array('checked_at' => unix time of the last sweep, 0 if none,
 *               'tables' => number of tables in the report,
 *               'issues' => table => list of 'error' | 'check_failed' |
 *               'repaired')
 */
function pg_db_health_report()
{
    $report = check_and_repair_database_tables();

    $cache = @json_decode((string) @file_get_contents(PG_FUNCTIONS_DIR . '/data/temp/db_health_cache.json'), true);

    $issues = array();

    foreach ((array) $report as $table => $states) {
        $table_issues = array_values(array_unique(array_intersect((array) $states, array('error', 'check_failed', 'repaired'))));
        if (count($table_issues) > 0) {
            $issues[(string) $table] = $table_issues;
        }
    }

    return array(
        'checked_at' => (is_array($cache) && isset($cache['last_check'])) ? (int) $cache['last_check'] : 0,
        'tables' => count((array) $report),
        'issues' => $issues,
    );
}

/**
 * The tables worth an OPTIMIZE: at least 10 MB of free space inside them,
 * and that space at least a fifth of the file. Below 10 MB the gain is not
 * worth rewriting a table for - InnoDB keeps a few MB of free extents in
 * every tablespace by itself - and a large table with a small share of free
 * space is rewritten whole for a few per cent.
 *
 * With innodb_file_per_table off the InnoDB tables live in the shared
 * tablespace, every one of them reports that tablespace's free space as its
 * own, and OPTIMIZE does not shrink it: no InnoDB table is a candidate then.
 *
 * No database access.
 *
 * @param array $catalog pg_db_table_catalog()
 * @param array $facts   pg_db_server_facts() (only 'file_per_table' is read)
 * @return array table names, in catalog order
 */
function pg_db_optimize_candidates($catalog, $facts = array())
{
    $minimum_free = 10485760;
    $minimum_share = 0.2;

    $shared_tablespace = isset($facts['file_per_table']) && ($facts['file_per_table'] !== null)
        && in_array(strtoupper(trim((string) $facts['file_per_table'])), array('0', 'OFF'), true);

    $candidates = array();

    foreach ((array) $catalog as $table => $row) {
        if ($shared_tablespace && isset($row['engine']) && ($row['engine'] === 'innodb')) {
            continue;
        }

        $free = isset($row['free']) ? (float) $row['free'] : 0.0;
        $bytes = isset($row['bytes']) ? (float) $row['bytes'] : 0.0;

        if (($free >= $minimum_free) && ($free >= $minimum_share * ($bytes + $free))) {
            $candidates[] = (string) $table;
        }
    }

    return $candidates;
}

/**
 * What the Database Engine screen has to say about the database, most
 * serious first.
 *
 * No database access: everything comes in through the arguments.
 *
 * @param array $catalog pg_db_table_catalog()
 * @param array $pending pg_innodb_pending_tables(), each row with
 *                       'row_estimate' => pg_innodb_row_estimate() added
 * @param array $health  pg_db_health_report()
 * @param array $facts   pg_db_server_facts()
 * @return array list of array('kind', 'table' => '' or a table name,
 *               'level' => 'danger' | 'warning' | 'info', 'text', 'bytes')
 */
function pg_db_table_findings($catalog, $pending, $health, $facts)
{
    // A table on every backup's critical path from this size on.
    $large_bytes = 104857600;

    // Only the largest few: on a big site every table of the list would be
    // large and the list would say nothing.
    $large_count = 5;

    $catalog = (array) $catalog;
    $pending = (array) $pending;

    $findings = array();

    $table_bytes = function ($table) use ($catalog) {
        return isset($catalog[$table]['bytes']) ? (float) $catalog[$table]['bytes'] : 0.0;
    };

    $unreadable = array();

    foreach ($catalog as $table => $row) {
        if ($row['engine'] === '') {
            $unreadable[$table] = true;
            $findings[] = array(
                'kind' => 'unreadable',
                'table' => (string) $table,
                'level' => 'danger',
                'text' => lang(array('string' => '{var:1}: the server cannot read the table\'s definition', 'vars' => array($table))),
                'bytes' => $table_bytes($table),
            );
        }
    }

    $health_issues = (isset($health['issues']) && is_array($health['issues'])) ? $health['issues'] : array();

    foreach ($health_issues as $table => $states) {

        // The sweep reports an unreadable table as an error too; it is
        // already in the list above.
        if (isset($unreadable[$table])) {
            continue;
        }

        $states = (array) $states;

        if (in_array('error', $states, true)) {
            $level = 'danger';
            $text = lang(array('string' => '{var:1}: the last check found an error in the table', 'vars' => array($table)));
        } elseif (in_array('check_failed', $states, true)) {
            $level = 'danger';
            $text = lang(array('string' => '{var:1}: the last check could not run on the table', 'vars' => array($table)));
        } elseif (in_array('repaired', $states, true)) {
            $level = 'warning';
            $text = lang(array('string' => '{var:1} had an error and was repaired by the last check', 'vars' => array($table)));
        } else {
            continue;
        }

        $findings[] = array(
            'kind' => 'health',
            'table' => (string) $table,
            'level' => $level,
            'text' => $text,
            'bytes' => $table_bytes($table),
        );
    }

    foreach ($pending as $table => $row) {
        if (isset($row['row_estimate']) && is_array($row['row_estimate']) && ($row['row_estimate']['fits'] === false)) {
            $findings[] = array(
                'kind' => 'too_wide',
                'table' => (string) $table,
                'level' => 'warning',
                'text' => pg_innodb_state_text(array('state' => 'too_wide', 'table' => $table, 'row_estimate' => $row['row_estimate'])),
                'bytes' => $table_bytes($table),
            );
        }
    }

    // The InnoDB tables of a shared tablespace are left out here as well
    // (see pg_db_optimize_candidates()).
    foreach (pg_db_optimize_candidates($catalog, $facts) as $table) {
        $free = (float) $catalog[$table]['free'];
        $findings[] = array(
            'kind' => 'fragmented',
            'table' => $table,
            'level' => 'warning',
            'text' => lang(array(
                'string' => '{var:1} holds {var:2} of free space ({var:3} %); OPTIMIZE TABLE gives it back',
                'vars' => array($table, pg_innodb_size_label($free), pg_format_number(100 * $free / max(1, $catalog[$table]['bytes'] + $free), 0)),
            )),
            'bytes' => $free,
        );
    }

    $large = array();

    foreach ($catalog as $table => $row) {
        if ($row['bytes'] >= $large_bytes) {
            $large[$table] = $row['bytes'];
        }
    }

    arsort($large);

    foreach (array_slice($large, 0, $large_count, true) as $table => $bytes) {
        $findings[] = array(
            'kind' => 'large',
            'table' => (string) $table,
            'level' => 'info',
            'text' => lang(array(
                'string' => '{var:1} is {var:2} ({var:3} rows); every backup copies it',
                'vars' => array($table, pg_innodb_size_label($bytes), pg_format_number($catalog[$table]['rows'], 0)),
            )),
            'bytes' => (float) $bytes,
        );
    }

    if (count($pending) > 0) {
        $pending_bytes = 0.0;
        foreach ($pending as $row) {
            $pending_bytes += isset($row['bytes']) ? (float) $row['bytes'] : 0.0;
        }
        $findings[] = array(
            'kind' => 'myisam',
            'table' => '',
            'level' => 'info',
            'text' => lang(array('string' => '{var:1} table(s) are still on MyISAM', 'vars' => array(pg_format_number(count($pending), 0)))),
            'bytes' => $pending_bytes,
        );
    }

    // Server variables come back as 0/1 from SELECT @@...; OFF/ON is
    // accepted as well.
    $is_off = function ($value) {
        return ($value !== null) && in_array(strtoupper(trim((string) $value)), array('0', 'OFF'), true);
    };

    if ($is_off(isset($facts['file_per_table']) ? $facts['file_per_table'] : null)) {
        $findings[] = array(
            'kind' => 'file_per_table',
            'table' => '',
            'level' => 'info',
            'text' => lang('innodb_file_per_table is off: OPTIMIZE TABLE rewrites an InnoDB table but the shared tablespace does not shrink'),
            'bytes' => 0.0,
        );
    }

    if ($is_off(isset($facts['strict_mode']) ? $facts['strict_mode'] : null)) {
        $findings[] = array(
            'kind' => 'strict_mode',
            'table' => '',
            'level' => 'info',
            'text' => lang('innodb_strict_mode is off: a table whose row is too wide is created with a warning and fails later, on write'),
            'bytes' => 0.0,
        );
    }

    // Danger first, then warning, then info; the larger first within a
    // level, and the order above where that is equal too.
    $rank = array('danger' => 0, 'warning' => 1, 'info' => 2);

    foreach ($findings as $position => $finding) {
        $findings[$position]['position'] = $position;
    }

    usort($findings, function ($a, $b) use ($rank) {
        if ($rank[$a['level']] != $rank[$b['level']]) {
            return ($rank[$a['level']] < $rank[$b['level']]) ? -1 : 1;
        }
        if ($a['bytes'] != $b['bytes']) {
            return ($a['bytes'] > $b['bytes']) ? -1 : 1;
        }
        return ($a['position'] < $b['position']) ? -1 : 1;
    });

    foreach ($findings as $position => $finding) {
        unset($findings[$position]['position']);
    }

    return $findings;
}
