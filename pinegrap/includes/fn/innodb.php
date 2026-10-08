<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Moving the software's tables from MyISAM to InnoDB, one table at a time.
 *
 * Shared by the 2026.4.8 schema step (includes/migrations/2026.4.8.php), the
 * Database Engine screen (database_engine.php) and the Storage Engine line of
 * System Status, so the list of tables, the size limits and the way a single
 * conversion is attempted and reported are written once.
 *
 * A conversion is ALTER TABLE ... ENGINE=InnoDB, which copies the whole table
 * while writes to it wait. On a large table that runs for minutes, longer than
 * a web request is allowed to live; when the request was cut off and retried,
 * every retry started another ALTER on the same table behind the first one's
 * metadata lock and the site stopped answering. So every attempt here asks
 * first: is the table already converted, is it too large for an unattended
 * run, is an ALTER on it still running on the server from an earlier request,
 * did an earlier attempt on it end without finishing. Only then does it start,
 * with a bounded wait for the metadata lock, and whatever MySQL answers comes
 * back as a result rather than as a dead page: the site works on either
 * engine, so a table that could not be moved is a note, not a failure.
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
 * The tables that are moved to InnoDB, by group, in the order the groups are
 * converted. Every table the software creates on MyISAM is here: the 141 of
 * the starter dumps under data/backups and the ones the migrations create with
 * the legacy ENGINE constant, less visitors, user and files, which earlier
 * versions already moved (2026.3.6, 2026.4.4). tests/innodb_test.php compares
 * this list with those sources, so a table added to a dump later and
 * forgotten here fails the test instead of staying on MyISAM unnoticed.
 *
 * No database access.
 *
 * @return array group => list of table names
 */
function pg_innodb_table_groups()
{
    return array(
        'orders' => array(
            'next_order_number', 'orders', 'order_items', 'order_item_gift_cards',
            'applied_gift_cards', 'gift_cards', 'ship_tos', 'shipping_tracking_numbers',
            'verified_shipping_addresses', 'address_book', 'key_codes', 'commissions',
            'recurring_commission_profiles', 'order_reports', 'order_report_filters',
            'offers', 'offer_actions', 'offer_rules', 'offer_rules_products_xref',
            'offers_offer_actions_xref', 'offer_actions_shipping_methods_xref',
            'order_refunds', 'iyzipay_3ds_state', 'local_sale_history',
            'local_sale_history_items', 'shipping_methods', 'shipping_methods_zones_xref',
            'shipping_rates', 'shipping_cutoffs', 'shipping_delivery_dates',
            'ship_date_adjustments',
        ),
        'products' => array(
            'products', 'product_groups', 'products_groups_xref', 'product_attributes',
            'product_attribute_options', 'products_attributes_xref',
            'product_groups_attributes_xref', 'products_images_xref',
            'product_groups_images_xref', 'products_zones_xref',
            'product_submit_form_fields', 'product_barcodes', 'tag_cloud_keywords',
            'tag_cloud_keywords_xref',
        ),
        'people' => array(
            'contacts', 'contact_groups', 'contacts_contact_groups_xref',
            'users_contact_groups_xref', 'log', 'email_recipients', 'email_campaigns',
            'email_campaign_profiles', 'contact_groups_email_campaigns_xref', 'opt_in',
            'messages', 'users_messages_xref', 'comments', 'allow_new_comments_for_items',
            'submitted_form_info', 'submitted_form_views', 'form_data', 'forms',
            'form_fields', 'form_field_options', 'form_list_view_browse_fields',
            'form_list_view_filters', 'form_view_directories_form_list_views_xref',
            'notifications', 'visitor_reports', 'visitor_report_filters',
            'referral_sources',
        ),
        'site' => array(
            'config', 'page', 'style', 'pregion', 'cregion', 'dregion', 'folder',
            'aclfolder', 'menus', 'menu_items', 'users_menus_xref', 'containers',
            'system_style_cells', 'system_theme_css_rules', 'preview_styles', 'dashboard',
            'users_common_regions_xref', 'login_regions', 'affiliate_sign_up_form_pages',
            'order_form_pages', 'shipping_address_and_arrival_pages', 'custom_form_pages',
            'search_results_pages', 'form_item_view_pages', 'shipping_method_pages',
            'billing_information_pages', 'photo_gallery_pages', 'update_address_book_pages',
            'catalog_pages', 'email_a_friend_pages', 'order_receipt_pages',
            'calendar_view_pages', 'form_view_directory_pages', 'catalog_detail_pages',
            'order_preview_pages', 'form_list_view_pages', 'folder_view_pages',
            'custom_form_confirmation_pages', 'express_order_pages', 'shopping_cart_pages',
            'calendar_event_view_pages', 'calendars', 'calendar_events',
            'calendar_event_locations', 'calendar_events_calendars_xref',
            'calendar_events_calendar_event_locations_xref', 'calendar_views_calendars_xref',
            'calendar_event_views_calendars_xref', 'calendar_event_exceptions',
            'users_calendars_xref', 'remaining_reservation_spots', 'zones',
            'zones_countries_xref', 'zones_states_xref', 'tax_zones',
            'tax_zones_countries_xref', 'tax_zones_states_xref', 'countries', 'states',
            'currencies', 'arrival_dates', 'excluded_transit_dates', 'banned_ip_addresses',
            'cookies', 'watchers', 'target_options', 'short_links', 'auto_dialogs', 'ads',
            'ad_regions', 'users_ad_regions_xref',
        ),
        'search' => array(
            'search_items',
        ),
    );
}

/**
 * Every table of pg_innodb_table_groups() in one flat list, each once.
 *
 * No database access.
 *
 * @return array
 */
function pg_innodb_core_tables()
{
    $tables = array();

    foreach (pg_innodb_table_groups() as $group_tables) {
        foreach ($group_tables as $table) {
            $tables[] = $table;
        }
    }

    return array_values(array_unique($tables));
}

/**
 * The file that says a conversion has started and not yet ended:
 * {"table": ..., "started": unix time, "pid": ...}. It is written before the
 * ALTER and removed after it, whichever way it ended, so a file that is still
 * there after the ALTER is no longer running means the request died under it.
 *
 * @return string
 */
function pg_innodb_temp_marker()
{
    return PG_FUNCTIONS_DIR . '/data/temp/innodb_convert.json';
}

/**
 * Whether this database server can hold the software's tables on InnoDB.
 *
 * Two questions, both asked with the plain mysqli call: a failing query here
 * means "no", and db() would turn it into an exit (or, inside the upgrade
 * runner, an exception).
 *
 *  - InnoDB is available at all (SHOW ENGINES, Support YES or DEFAULT).
 *  - New InnoDB tables are created with the DYNAMIC row format. `config`
 *    carries some 350 columns, more than thirty of them TEXT. Under the older
 *    COMPACT format every long column keeps a 768-byte prefix inside the row,
 *    which together passes InnoDB's 8126-byte row limit: the ALTER may even
 *    succeed, and later an UPDATE of the settings fails at run time with
 *    error 1118. DYNAMIC moves long columns off the page whole. The index on
 *    search_items.url(250) needs it as well: 1000 bytes under utf8mb4, past
 *    the 767 bytes COMPACT allows for a key. innodb_default_row_format exists
 *    from MySQL 5.7.9 and MariaDB 10.2.2; on an older server the variable is
 *    unknown, the query fails, and the tables stay where they are.
 *
 * @return array('ok' => bool, 'reason' => string)
 */
function pg_innodb_capability()
{
    static $capability = null;

    if ($capability !== null) {
        return $capability;
    }

    if (!class_exists('db') || empty(db::$con)) {
        return array('ok' => false, 'reason' => lang('no database connection'));
    }

    $innodb = false;

    $engines = @mysqli_query(db::$con, "SHOW ENGINES");

    if ($engines) {
        while ($engine = mysqli_fetch_assoc($engines)) {
            if ((strcasecmp((string) $engine['Engine'], 'InnoDB') == 0)
                && in_array(strtoupper((string) $engine['Support']), array('YES', 'DEFAULT'))) {
                $innodb = true;
            }
        }
    }

    if (!$innodb) {
        $capability = array('ok' => false, 'reason' => lang('the database server does not offer the InnoDB engine'));
        return $capability;
    }

    $row_format = '';

    $result = @mysqli_query(db::$con, "SELECT @@innodb_default_row_format");

    if ($result) {
        $row = mysqli_fetch_row($result);
        $row_format = strtolower((string) (isset($row[0]) ? $row[0] : ''));
    }

    if ($row_format !== 'dynamic') {
        $capability = array('ok' => false, 'reason' => lang('the database server does not create InnoDB tables with the DYNAMIC row format'));
        return $capability;
    }

    $capability = array('ok' => true, 'reason' => '');

    return $capability;
}

/**
 * Engine, row count and size of the named tables, in one information_schema
 * query. A table that does not exist is not in the answer.
 *
 * On MySQL 8 information_schema serves table statistics from a cache that is
 * refreshed once a day by default; the size limits below would then be judged
 * against yesterday's row count. The session is asked for fresh statistics
 * first - the variable is unknown to MySQL 5.7 and MariaDB, where the
 * statistics are always current, and the failed SET is ignored.
 *
 * @param array $tables
 * @return array table => array('engine' => lower case, 'rows' => int, 'bytes' => float)
 */
function pg_innodb_table_status($tables)
{
    static $fresh_statistics = false;

    $names = array();

    foreach ((array) $tables as $table) {
        $names[] = "'" . e((string) $table) . "'";
    }

    if (count($names) == 0) {
        return array();
    }

    if (!$fresh_statistics) {
        $fresh_statistics = true;
        @mysqli_query(db::$con, "SET SESSION information_schema_stats_expiry = 0");
    }

    $rows = db_items(
        "SELECT
            TABLE_NAME AS table_name,
            ENGINE AS engine,
            TABLE_ROWS AS table_rows,
            (DATA_LENGTH + INDEX_LENGTH) AS bytes
        FROM information_schema.TABLES
        WHERE
            (TABLE_SCHEMA = DATABASE())
            AND (TABLE_NAME IN (" . implode(', ', $names) . "))");

    $status = array();

    foreach ((array) $rows as $row) {
        $status[(string) $row['table_name']] = array(
            'engine' => strtolower((string) $row['engine']),
            'rows' => (int) $row['table_rows'],
            'bytes' => (float) $row['bytes'],
        );
    }

    return $status;
}

/**
 * The tables of the list that exist and are not on InnoDB yet, smallest
 * first.
 *
 * @return array table => array('engine', 'rows', 'bytes')
 */
function pg_innodb_pending_tables()
{
    $pending = array();

    foreach (pg_innodb_table_status(pg_innodb_core_tables()) as $table => $status) {
        if ($status['engine'] !== 'innodb') {
            $pending[$table] = $status;
        }
    }

    uasort($pending, function ($a, $b) {
        if ($a['bytes'] == $b['bytes']) {
            return 0;
        }
        return ($a['bytes'] < $b['bytes']) ? -1 : 1;
    });

    return $pending;
}

/**
 * An ALTER TABLE on this table that another connection is running right now:
 * typically the conversion a timed-out request started, which the server
 * carries on with after the web server has given up on the request. Starting
 * a second one would only queue behind its metadata lock, and everything else
 * that touches the table would queue behind both.
 *
 * Without the PROCESS privilege information_schema.PROCESSLIST still lists
 * the threads of the same database user, and those are the ones that matter
 * here: the software's own requests.
 *
 * @param string $table
 * @return array|null the PROCESSLIST row (ID, TIME, STATE, INFO), or null when
 *                    there is none or the list cannot be read
 */
function pg_innodb_running_alter($table)
{
    if (!class_exists('db') || empty(db::$con)) {
        return null;
    }

    $result = @mysqli_query(db::$con,
        "SELECT ID, TIME, STATE, INFO
        FROM information_schema.PROCESSLIST
        WHERE
            (ID <> CONNECTION_ID())
            AND (INFO IS NOT NULL)
            AND (INFO LIKE 'ALTER TABLE%')");

    if (!$result) {
        return null;
    }

    $pattern = '/ALTER\s+TABLE\s+`?' . preg_quote((string) $table, '/') . '`?(?![A-Za-z0-9_$])/i';

    while ($row = mysqli_fetch_assoc($result)) {
        if (preg_match($pattern, (string) $row['INFO'])) {
            return $row;
        }
    }

    return null;
}

/**
 * Moves one table to InnoDB, unless there is a reason not to start.
 *
 * $options:
 *   max_rows   int    leave a table with more rows than this alone (0: no limit)
 *   max_bytes  float  leave a table larger than this alone (0: no limit)
 *   retry      bool   false: a table an earlier attempt did not finish is left
 *                     alone (the upgrade must not walk into the same wall
 *                     again); true: try it anyway (the operator asked)
 *
 * The answer's 'state', checked in this order:
 *   missing      the table does not exist
 *   already      the table is on InnoDB
 *   unsupported  the server cannot hold it on InnoDB ('error' says why)
 *   deferred     over max_rows or max_bytes
 *   running      another connection is running an ALTER on it
 *                ('running_seconds')
 *   aborted      the marker of an earlier attempt on it is at least 30 s old
 *                and the table is still MyISAM
 *   busy         the ALTER gave up waiting for the table (MySQL 1205)
 *   failed       any other error ('errno', 'error')
 *   converted    done
 *
 * db() is not used anywhere in here: outside the upgrade runner it answers a
 * failed query with output_error() and ends the request, inside it throws.
 * A conversion that does not succeed is one of the answers of this function.
 *
 * @param string $table
 * @param array  $options
 * @return array('state', 'table', 'rows', 'bytes', 'seconds', 'errno', 'error', 'running_seconds')
 */
function pg_innodb_convert_table($table, $options = array())
{
    $table = (string) $table;

    $max_rows = isset($options['max_rows']) ? (int) $options['max_rows'] : 0;
    $max_bytes = isset($options['max_bytes']) ? (float) $options['max_bytes'] : 0;
    $retry = !empty($options['retry']);

    $result = array(
        'state' => 'failed',
        'table' => $table,
        'rows' => 0,
        'bytes' => 0.0,
        'seconds' => 0.0,
        'errno' => 0,
        'error' => '',
        'running_seconds' => 0,
    );

    // The name goes into the statement between backticks; only the names the
    // software itself uses are accepted.
    if (preg_match('/^[a-z0-9_]+$/', $table) !== 1) {
        $result['error'] = lang('invalid table name');
        return $result;
    }

    if (!class_exists('db') || empty(db::$con)) {
        $result['error'] = lang('no database connection');
        return $result;
    }

    $status = pg_innodb_table_status(array($table));

    if (!isset($status[$table])) {
        $result['state'] = 'missing';
        return $result;
    }

    $result['rows'] = $status[$table]['rows'];
    $result['bytes'] = $status[$table]['bytes'];

    $marker_file = pg_innodb_temp_marker();
    $marker = @json_decode((string) @file_get_contents($marker_file), true);
    $marker_is_ours = (is_array($marker) && isset($marker['table']) && ($marker['table'] === $table));

    if ($status[$table]['engine'] === 'innodb') {
        // A marker left by an attempt that the server finished after the
        // request had gone.
        if ($marker_is_ours) {
            @unlink($marker_file);
        }
        $result['state'] = 'already';
        return $result;
    }

    $capability = pg_innodb_capability();

    if (!$capability['ok']) {
        $result['state'] = 'unsupported';
        $result['error'] = $capability['reason'];
        return $result;
    }

    if ((($max_rows > 0) && ($result['rows'] > $max_rows)) || (($max_bytes > 0) && ($result['bytes'] > $max_bytes))) {
        $result['state'] = 'deferred';
        return $result;
    }

    $running = pg_innodb_running_alter($table);

    if ($running !== null) {
        $result['state'] = 'running';
        $result['running_seconds'] = (int) $running['TIME'];
        return $result;
    }

    // Nothing is running on the table, it is still MyISAM, and an attempt on
    // it began more than half a minute ago and never removed its marker: the
    // request (or the server) died during the copy. Whatever stopped it then
    // is likely to stop it again, so only the operator retries it.
    if ($marker_is_ours && !$retry && isset($marker['started']) && (((int) $marker['started'] + 30) <= time())) {
        $result['state'] = 'aborted';
        return $result;
    }

    $marker_directory = dirname($marker_file);

    if (!is_dir($marker_directory)) {
        @mkdir($marker_directory, 0755, true);
    }

    @file_put_contents($marker_file, json_encode(array(
        'table' => $table,
        'started' => time(),
        'pid' => (function_exists('getmypid') ? getmypid() : 0),
    )), LOCK_EX);

    // The session settings are put back afterwards; the same connection
    // carries on with the rest of the request.
    $previous = null;

    $previous_result = @mysqli_query(db::$con, "SELECT @@SESSION.lock_wait_timeout AS lock_wait_timeout, @@SESSION.sql_mode AS sql_mode");

    if ($previous_result) {
        $previous = mysqli_fetch_assoc($previous_result);
    }

    // A bounded wait for the metadata lock. Without it the ALTER waits as
    // long as the server's default (a year), and every query on the table
    // queues behind the waiting ALTER.
    @mysqli_query(db::$con, "SET SESSION lock_wait_timeout = 20");

    // The copy re-inserts every row under the session's sql_mode. Old MyISAM
    // tables hold zero dates ('0000-00-00') that a strict mode refuses on
    // insert, which would fail the conversion of a table the site reads and
    // writes without complaint. The installer pins the same mode.
    @mysqli_query(db::$con, "SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");

    $started = microtime(true);

    $done = @mysqli_query(db::$con, "ALTER TABLE `" . $table . "` ENGINE=InnoDB");

    $result['seconds'] = round(microtime(true) - $started, 1);

    if (!$done) {
        $result['errno'] = (int) mysqli_errno(db::$con);
        $result['error'] = (string) mysqli_error(db::$con);
    }

    if (is_array($previous)) {
        @mysqli_query(db::$con, "SET SESSION lock_wait_timeout = " . (int) $previous['lock_wait_timeout']);
        @mysqli_query(db::$con, "SET SESSION sql_mode = '" . mysqli_real_escape_string(db::$con, (string) $previous['sql_mode']) . "'");
    }

    @unlink($marker_file);

    if ($done) {
        $result['state'] = 'converted';
    } elseif ($result['errno'] == 1205) {
        $result['state'] = 'busy';
    } else {
        $result['state'] = 'failed';
    }

    return $result;
}

/**
 * A size for a person to read: KB, MB or GB.
 *
 * No database access.
 *
 * @param float|int $bytes
 * @return string
 */
function pg_innodb_size_label($bytes)
{
    $bytes = (float) $bytes;

    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 1) . ' GB';
    }

    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 0) . ' MB';
    }

    return number_format($bytes / 1024, 0) . ' KB';
}

/**
 * One line about the result of pg_innodb_convert_table(), for the upgrade's
 * notes and for the Database Engine screen alike.
 *
 * @param array $result
 * @return string
 */
function pg_innodb_state_text($result)
{
    $table = isset($result['table']) ? (string) $result['table'] : '';
    $rows = pg_format_number(isset($result['rows']) ? $result['rows'] : 0, 0);
    $seconds = pg_format_number(isset($result['seconds']) ? $result['seconds'] : 0, 1);
    $size = pg_innodb_size_label(isset($result['bytes']) ? $result['bytes'] : 0);

    switch (isset($result['state']) ? $result['state'] : '') {

        case 'converted':
            return lang(array(
                'string' => '{var:1} moved to InnoDB ({var:2} rows, {var:3}, {var:4} s)',
                'vars' => array($table, $rows, $size, $seconds),
            ));

        case 'already':
            return lang(array('string' => '{var:1} is already InnoDB', 'vars' => array($table)));

        case 'deferred':
            return lang(array(
                'string' => '{var:1} is large ({var:2} rows, {var:3}) and was left on MyISAM; convert it from the Database Engine screen when the site is quiet',
                'vars' => array($table, $rows, $size),
            ));

        case 'running':
            return lang(array(
                'string' => 'An ALTER TABLE on {var:1} has been running on the database server for {var:2} s from an earlier request; waiting for it',
                'vars' => array($table, isset($result['running_seconds']) ? (int) $result['running_seconds'] : 0),
            ));

        case 'aborted':
            return lang(array(
                'string' => 'An earlier attempt to move {var:1} did not finish, so it was left on MyISAM; convert it from the Database Engine screen',
                'vars' => array($table),
            ));

        case 'busy':
            return lang(array('string' => '{var:1} is in use (lock wait timeout); it will be tried again', 'vars' => array($table)));

        case 'unsupported':
            return lang(array(
                'string' => '{var:1} was left on MyISAM: {var:2}',
                'vars' => array($table, isset($result['error']) ? (string) $result['error'] : ''),
            ));

        case 'missing':
            return lang(array('string' => 'table {var:1} does not exist, skipped', 'vars' => array($table)));

        default:
            return lang(array(
                'string' => '{var:1} could not be moved (MySQL {var:2}: {var:3}); it stays on MyISAM',
                'vars' => array($table, isset($result['errno']) ? (int) $result['errno'] : 0, isset($result['error']) ? (string) $result['error'] : ''),
            ));
    }
}
