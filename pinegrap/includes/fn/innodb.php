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
 * The largest clustered-index record InnoDB accepts on a page of this size:
 * half the free space of an empty page (16 KB: 8126 bytes), and 16383 on
 * 64 KB pages, where the record format itself is the limit. A table whose
 * widest possible row reaches this figure is refused with MySQL error 1118.
 *
 * No database access.
 *
 * @param int $page_size innodb_page_size in bytes
 * @return int
 */
function pg_innodb_row_limit($page_size = 16384)
{
    $page_size = (int) $page_size;

    if ($page_size >= 65536) {
        return 16383;
    }

    // 132 bytes of an empty page are never free: the page header, the
    // infimum and supremum records, the trailer and two directory slots.
    return (int) floor(($page_size - 132) / 2);
}

/**
 * The size of a fixed-length column in an InnoDB record and in the server's
 * 65,535-byte row: integers, floating point, DECIMAL (packed, 9 digits in 4
 * bytes), temporal types (with 1 to 3 bytes of fractional seconds), ENUM,
 * SET, BIT, BINARY, and CHAR in a character set whose characters all have
 * one width. null for a variable-length column - VARCHAR, VARBINARY, CHAR in
 * utf8mb4 and the like, TEXT, BLOB - and for a type this list does not know.
 *
 * No database access.
 *
 * @param array $column one column as pg_innodb_row_estimate_from_columns()
 *                      takes it
 * @return int|null
 */
function pg_innodb_fixed_column_size($column)
{
    $fixed_sizes = array(
        'tinyint' => 1, 'smallint' => 2, 'mediumint' => 3, 'int' => 4, 'integer' => 4,
        'bigint' => 8, 'float' => 4, 'double' => 8, 'real' => 8,
        'date' => 3, 'year' => 1, 'time' => 3, 'datetime' => 5, 'timestamp' => 4,
    );

    // Temporal types with fractional seconds store them in 1 to 3 more bytes.
    $fractional_types = array('time', 'datetime', 'timestamp');

    // CHAR(n) is stored with a fixed length only when every character of the
    // set has the same width; in these sets it is variable, up to n times
    // the widest character.
    $variable_width_charsets = array(
        'utf8', 'utf8mb3', 'utf8mb4', 'utf16', 'utf16le',
        'big5', 'gbk', 'gb2312', 'gb18030', 'sjis', 'cp932', 'ujis', 'eucjpms', 'euckr',
    );

    // Bytes for the leftover digits of a DECIMAL part (9 digits take 4).
    $decimal_leftover = array(0, 1, 1, 2, 2, 3, 3, 4, 4, 4);

    $type = strtolower(trim((string) (isset($column['type']) ? $column['type'] : '')));
    $column_type = strtolower((string) (isset($column['column_type']) ? $column['column_type'] : ''));
    $octets = (isset($column['octets']) && ($column['octets'] !== null) && ($column['octets'] !== '')) ? (float) $column['octets'] : null;
    $charset = (isset($column['charset']) && ($column['charset'] !== null)) ? strtolower((string) $column['charset']) : '';

    if (isset($fixed_sizes[$type])) {

        $size = $fixed_sizes[$type];

        if (in_array($type, $fractional_types, true) && preg_match('/\((\d)\)/', $column_type, $fsp)) {
            $size += (int) ceil(((int) $fsp[1]) / 2);
        }

        return $size;
    }

    if (($type === 'decimal') || ($type === 'numeric')) {

        $precision = isset($column['precision']) ? (int) $column['precision'] : 10;
        $scale = isset($column['scale']) ? (int) $column['scale'] : 0;
        $whole = max(0, $precision - $scale);

        return (intdiv($whole, 9) * 4) + $decimal_leftover[$whole % 9]
            + (intdiv($scale, 9) * 4) + $decimal_leftover[$scale % 9];
    }

    if (($type === 'enum') || ($type === 'set')) {

        $members = preg_match_all("/'(?:[^'\\\\]|''|\\\\.)*'/", $column_type);

        if ($type === 'enum') {
            return ($members > 255) ? 2 : 1;
        }

        $size = max(1, (int) floor(($members + 7) / 8));

        return ($size > 4) ? 8 : $size;
    }

    if ($type === 'bit') {

        $width = isset($column['precision']) ? (int) $column['precision'] : 0;

        if (($width < 1) && preg_match('/\((\d+)\)/', $column_type, $bit)) {
            $width = (int) $bit[1];
        }

        return max(1, (int) ceil($width / 8));
    }

    if (($type === 'binary') || (($type === 'char') && !in_array($charset, $variable_width_charsets, true))) {
        return (int) $octets;
    }

    return null;
}

/**
 * The worst-case size of one row of a table on InnoDB with the DYNAMIC row
 * format, counted the way the server counts it when it creates the table:
 * a table at or over pg_innodb_row_limit() is refused with MySQL 1118 (with
 * innodb_strict_mode on; with it off it is created and fails on write).
 *
 * A record carries a 5-byte header, one bit per nullable column, the hidden
 * transaction and roll pointer columns (13 bytes; 19 with the row id InnoDB
 * adds to a table without a primary key) and every column. A fixed-length
 * column takes its full size. A variable-length column whose longest value
 * is above 255 bytes, and every TEXT, BLOB, JSON or spatial column, can be
 * moved off the page and costs at most 41 bytes in the row: a 40-byte local
 * part or pointer and a length byte. A variable-length column of 255 bytes
 * or less never leaves the row - its one-byte length has no room for the
 * "stored elsewhere" flag - so it costs its full length plus one. That is
 * why the character set decides: VARCHAR(100) is 400 bytes in utf8mb4 and
 * goes off the page, but 100 bytes in latin1 and stays inside it.
 *
 * The servers do not count alike, and $rule says whose count to use:
 *   mysql80  MySQL 8.0 and later (get_field_max_size()): a long column 41,
 *            a short one its length plus one - the strictest count
 *   mysql57  MySQL 5.7, and MariaDB before 10.4 whose InnoDB comes from it
 *            (dict_index_too_big_for_tree()): any variable-length column
 *            over 40 bytes 41, short ones included. The ALTER goes through,
 *            and a row that really is too long fails later, on write.
 *   mariadb  MariaDB 10.4 and later (dict_index_t::record_size_info()): a
 *            long column 21 (a 20-byte pointer and a length byte), a short
 *            one its length plus one
 * The stock `config` before 2026.4.8 came to 8468 bytes by the first count,
 * 6988 by the second and 5868 by the third. 'inline_columns' and
 * 'external_columns' say where a column can live, whatever the count.
 *
 * $columns, in table order, each:
 *   name         column name
 *   type         DATA_TYPE, lower case
 *   octets       CHARACTER_OCTET_LENGTH (null for non-character types)
 *   precision    NUMERIC_PRECISION
 *   scale        NUMERIC_SCALE
 *   nullable     bool
 *   column_type  COLUMN_TYPE (enum and set members, bit width, fractional
 *                seconds)
 *   charset      CHARACTER_SET_NAME (null for non-character types)
 *
 * No database access.
 *
 * @param array $columns
 * @param bool  $has_primary_key
 * @param int   $page_size innodb_page_size in bytes
 * @param string $rule     'mysql80', 'mysql57' or 'mariadb'
 *                         (pg_innodb_server_rule())
 * @return array('bytes', 'limit', 'fits', 'columns', 'inline_columns',
 *               'external_columns', 'fixed_columns', 'nullable', 'widest')
 */
function pg_innodb_row_estimate_from_columns($columns, $has_primary_key, $page_size = 16384, $rule = 'mysql80')
{
    // Types InnoDB always treats as long, whatever their declared length.
    $long_types = array(
        'tinytext', 'text', 'mediumtext', 'longtext',
        'tinyblob', 'blob', 'mediumblob', 'longblob', 'json',
        'geometry', 'point', 'linestring', 'polygon', 'multipoint',
        'multilinestring', 'multipolygon', 'geometrycollection', 'geomcollection',
    );

    $bytes = 5 + ($has_primary_key ? 13 : 19);
    $nullable = 0;
    $inline = 0;
    $external = 0;
    $fixed = 0;
    $sizes = array();

    foreach ((array) $columns as $position => $column) {

        $type = strtolower(trim((string) (isset($column['type']) ? $column['type'] : '')));
        $column_type = strtolower((string) (isset($column['column_type']) ? $column['column_type'] : ''));
        $octets = (isset($column['octets']) && ($column['octets'] !== null) && ($column['octets'] !== '')) ? (float) $column['octets'] : null;
        $charset = (isset($column['charset']) && ($column['charset'] !== null)) ? strtolower((string) $column['charset']) : '';

        if (!empty($column['nullable'])) {
            $nullable++;
        }

        // null: variable length, sized by $octets below.
        $size = pg_innodb_fixed_column_size($column);

        if ($size !== null) {

            $fixed++;

        } elseif (in_array($type, $long_types, true)
            || !in_array($type, array('varchar', 'varbinary', 'char'), true)
            || ($octets === null) || ($octets > 255)) {

            // Long, or a type this list does not know: it can leave the page.
            $size = ($rule === 'mariadb') ? 21 : 41;
            $external++;

        } else {

            // Short: it stays inside the row. MySQL 5.7 counts it as a long
            // one all the same once it is over 40 bytes.
            $size = (($rule === 'mysql57') && ($octets > 40)) ? 41 : ((int) $octets + 1);
            $inline++;

        }

        $bytes += $size;

        $sizes[] = array(
            'position' => $position,
            'name' => (string) (isset($column['name']) ? $column['name'] : ''),
            'bytes' => $size,
            'column_type' => $column_type,
            'charset' => $charset,
        );
    }

    $bytes += (int) ceil($nullable / 8);

    // Widest first; columns of the same width in table order.
    usort($sizes, function ($a, $b) {
        if ($a['bytes'] != $b['bytes']) {
            return ($a['bytes'] > $b['bytes']) ? -1 : 1;
        }
        return ($a['position'] < $b['position']) ? -1 : (($a['position'] > $b['position']) ? 1 : 0);
    });

    $widest = array();

    foreach (array_slice($sizes, 0, 10) as $size_row) {
        unset($size_row['position']);
        $widest[] = $size_row;
    }

    $limit = pg_innodb_row_limit($page_size);

    return array(
        'bytes' => $bytes,
        'limit' => $limit,
        'fits' => ($bytes < $limit),
        'columns' => count($sizes),
        'inline_columns' => $inline,
        'external_columns' => $external,
        'fixed_columns' => $fixed,
        'nullable' => $nullable,
        'widest' => $widest,
    );
}

/**
 * The size of a row as the server counts it against its own limit of 65,535
 * bytes, which comes before any engine's and applies to MyISAM and InnoDB
 * alike: every column at its longest - VARCHAR and VARBINARY at their octet
 * length plus a length byte (two above 255), CHAR at its octet length - but
 * TEXT and BLOB columns only as their length and pointer (9 to 12 bytes),
 * plus a bit per nullable column. A CREATE or ALTER that passes the limit is
 * refused with MySQL 1118, whatever the engine and the sql_mode.
 *
 * No database access.
 *
 * @param array $columns as for pg_innodb_row_estimate_from_columns()
 * @return array('bytes' => int, 'limit' => 65535, 'fits' => bool)
 */
function pg_innodb_sql_row_estimate_from_columns($columns)
{
    $pointer_sizes = array(
        'tinytext' => 9, 'tinyblob' => 9, 'text' => 10, 'blob' => 10,
        'mediumtext' => 11, 'mediumblob' => 11, 'longtext' => 12, 'longblob' => 12,
        'json' => 12, 'geometry' => 12, 'point' => 12, 'linestring' => 12, 'polygon' => 12,
        'multipoint' => 12, 'multilinestring' => 12, 'multipolygon' => 12,
        'geometrycollection' => 12, 'geomcollection' => 12,
    );

    $bytes = 0;
    $nullable = 0;

    foreach ((array) $columns as $column) {

        $type = strtolower(trim((string) (isset($column['type']) ? $column['type'] : '')));
        $octets = (isset($column['octets']) && ($column['octets'] !== null) && ($column['octets'] !== '')) ? (int) $column['octets'] : 0;

        if (!empty($column['nullable'])) {
            $nullable++;
        }

        $size = pg_innodb_fixed_column_size($column);

        if ($size === null) {
            if ($type === 'char') {
                $size = $octets;
            } elseif (isset($pointer_sizes[$type])) {
                $size = $pointer_sizes[$type];
            } elseif (($type === 'varchar') || ($type === 'varbinary')) {
                $size = $octets + (($octets > 255) ? 2 : 1);
            } else {
                // A type this list does not know: counted like the largest
                // pointer.
                $size = 12;
            }
        }

        $bytes += $size;
    }

    $bytes += (int) ceil($nullable / 8);

    return array(
        'bytes' => $bytes,
        'limit' => 65535,
        'fits' => ($bytes <= 65535),
    );
}

/**
 * Whose row size count applies on this server (see
 * pg_innodb_row_estimate_from_columns()), from its version string.
 *
 * MariaDB 10.4 and later count a long column as 21 bytes; MariaDB 10.2 and
 * 10.3 still carry the InnoDB of MySQL 5.7 and count the way it does. MySQL
 * 8.0 and later (Percona Server alike) use the strict count. A version that
 * cannot be read gets the strict count too.
 *
 * No database access.
 *
 * @param string $version_string SELECT VERSION(), e.g. '10.11.14-MariaDB',
 *                               '8.0.36', '5.7.44-log'
 * @return string 'mysql80', 'mysql57' or 'mariadb'
 */
function pg_innodb_server_rule($version_string)
{
    $version_string = (string) $version_string;

    if (stripos($version_string, 'mariadb') !== false) {

        // A replication-compatible prefix ('5.5.5-10.6.16-MariaDB') is not
        // the version.
        $version_string = preg_replace('/^5\.5\.5-/', '', $version_string);

        if (preg_match('/^(\d+)\.(\d+)/', $version_string, $match)) {
            return (version_compare($match[1] . '.' . $match[2], '10.4', '>=')) ? 'mariadb' : 'mysql57';
        }

        return 'mysql57';
    }

    if (preg_match('/^(\d+)\.(\d+)/', $version_string, $match)) {
        return (version_compare($match[1] . '.' . $match[2], '8.0', '>=')) ? 'mysql80' : 'mysql57';
    }

    return 'mysql80';
}

/**
 * pg_innodb_row_estimate_from_columns() for a table of this database, read
 * from information_schema and counted the way this server counts
 * (pg_innodb_server_rule()), plus 'charsets' (character set => number of
 * columns), which tells a latin1 table from a utf8mb4 one at a glance,
 * 'rule' and 'server' (the VERSION() string).
 *
 * Plain mysqli calls, as everywhere in this file: a failing query is an
 * answer (null), not the end of the request.
 *
 * @param string $table
 * @return array|null null when the table does not exist or cannot be read
 */
function pg_innodb_row_estimate($table)
{
    static $page_size = null;
    static $server = null;

    $table = (string) $table;

    if (preg_match('/^[a-z0-9_]+$/', $table) !== 1) {
        return null;
    }

    if (!class_exists('db') || empty(db::$con)) {
        return null;
    }

    $result = @mysqli_query(db::$con,
        "SELECT
            COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, CHARACTER_OCTET_LENGTH,
            CHARACTER_SET_NAME, NUMERIC_PRECISION, NUMERIC_SCALE, IS_NULLABLE
        FROM information_schema.COLUMNS
        WHERE
            (TABLE_SCHEMA = DATABASE())
            AND (TABLE_NAME = '" . mysqli_real_escape_string(db::$con, $table) . "')
        ORDER BY ORDINAL_POSITION");

    if (!$result) {
        return null;
    }

    $columns = array();
    $charsets = array();

    while ($row = mysqli_fetch_assoc($result)) {

        $charset = ($row['CHARACTER_SET_NAME'] !== null) ? strtolower((string) $row['CHARACTER_SET_NAME']) : null;

        if ($charset !== null) {
            $charsets[$charset] = isset($charsets[$charset]) ? $charsets[$charset] + 1 : 1;
        }

        $columns[] = array(
            'name' => (string) $row['COLUMN_NAME'],
            'type' => strtolower((string) $row['DATA_TYPE']),
            'octets' => ($row['CHARACTER_OCTET_LENGTH'] !== null) ? (float) $row['CHARACTER_OCTET_LENGTH'] : null,
            'precision' => ($row['NUMERIC_PRECISION'] !== null) ? (int) $row['NUMERIC_PRECISION'] : null,
            'scale' => ($row['NUMERIC_SCALE'] !== null) ? (int) $row['NUMERIC_SCALE'] : null,
            'nullable' => (strtoupper((string) $row['IS_NULLABLE']) === 'YES'),
            'column_type' => (string) $row['COLUMN_TYPE'],
            'charset' => $charset,
        );
    }

    if (count($columns) == 0) {
        return null;
    }

    $has_primary_key = false;

    $key_result = @mysqli_query(db::$con,
        "SELECT COUNT(*)
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE
            (TABLE_SCHEMA = DATABASE())
            AND (TABLE_NAME = '" . mysqli_real_escape_string(db::$con, $table) . "')
            AND (CONSTRAINT_TYPE = 'PRIMARY KEY')");

    if ($key_result) {
        $key_row = mysqli_fetch_row($key_result);
        $has_primary_key = (isset($key_row[0]) && ((int) $key_row[0] > 0));
    }

    if ($page_size === null) {

        $page_size = 16384;

        $page_result = @mysqli_query(db::$con, "SELECT @@innodb_page_size");

        if ($page_result) {
            $page_row = mysqli_fetch_row($page_result);
            if (isset($page_row[0]) && ((int) $page_row[0] > 0)) {
                $page_size = (int) $page_row[0];
            }
        }
    }

    if ($server === null) {

        $server = '';

        $version_result = @mysqli_query(db::$con, "SELECT VERSION()");

        if ($version_result) {
            $version_row = mysqli_fetch_row($version_result);
            $server = isset($version_row[0]) ? (string) $version_row[0] : '';
        }
    }

    arsort($charsets);

    $rule = pg_innodb_server_rule($server);

    $estimate = pg_innodb_row_estimate_from_columns($columns, $has_primary_key, $page_size, $rule);
    $estimate['charsets'] = $charsets;
    $estimate['rule'] = $rule;
    $estimate['server'] = $server;

    return $estimate;
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
    // The PROCESSLIST reading is shared with the maintenance statements of
    // the Database Engine screen (includes/fn/db_maintenance.php).
    return pg_db_running_statement($table, array('ALTER TABLE'));
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
 *   too_wide     its widest possible row is over InnoDB's row limit
 *                ('row_estimate', see pg_innodb_row_estimate()); also the
 *                answer when the ALTER itself fails with MySQL 1118
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
 * The row size is asked before the ALTER rather than learned from it, in
 * this server's own count (pg_innodb_row_estimate()). Under DYNAMIC a long
 * column leaves the page and costs 41 bytes in the row (21 on MariaDB), but
 * a column of 255 bytes or less must stay inside it at full length; a table
 * with many columns of either kind passes 8126 bytes - the stock `config`
 * did on MySQL 8.0 until 2026.4.8 turned its VARCHAR columns into TEXT. A
 * table the server would refuse cannot be held on InnoDB as it is, and
 * copying it whole only to be refused at the end would hold its writes for
 * nothing.
 *
 * @param string $table
 * @param array  $options
 * @return array('state', 'table', 'rows', 'bytes', 'seconds', 'errno', 'error', 'running_seconds', 'row_estimate')
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
        'row_estimate' => null,
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

    $result['row_estimate'] = pg_innodb_row_estimate($table);

    if (is_array($result['row_estimate']) && ($result['row_estimate']['fits'] === false)) {
        $result['state'] = 'too_wide';
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
    } elseif ($result['errno'] == 1118) {
        // Row size too large although the estimate said it fits: a server
        // that counts differently. The answer still carries the estimate.
        $result['state'] = 'too_wide';
        if (!is_array($result['row_estimate'])) {
            $result['row_estimate'] = pg_innodb_row_estimate($table);
        }
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

        case 'too_wide':
            // Without an estimate that says it does not fit (the ALTER was
            // refused with 1118 but the estimate could not be read, or it
            // counted the row as fitting) the server's own message is the
            // better explanation: the default branch below.
            if (isset($result['row_estimate']) && is_array($result['row_estimate']) && ($result['row_estimate']['fits'] === false)) {
                $estimate = $result['row_estimate'];
                return lang(array(
                    'string' => '{var:1} cannot be held on InnoDB as it is: its widest possible row needs {var:2} bytes and an InnoDB row may use {var:3}. {var:4} of its {var:5} columns are shorter than 256 bytes and must stay inside the row. It stays on MyISAM.',
                    'vars' => array(
                        $table,
                        pg_format_number($estimate['bytes'], 0),
                        pg_format_number($estimate['limit'], 0),
                        pg_format_number($estimate['inline_columns'], 0),
                        pg_format_number($estimate['columns'], 0),
                    ),
                ));
            }
            // no break

        default:
            return lang(array(
                'string' => '{var:1} could not be moved (MySQL {var:2}: {var:3}); it stays on MyISAM',
                'vars' => array($table, isset($result['errno']) ? (int) $result['errno'] : 0, isset($result['error']) ? (string) $result['error'] : ''),
            ));
    }
}
