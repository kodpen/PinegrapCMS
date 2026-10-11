<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the table list and the pure helpers of includes/fn/innodb.php.
 * The list is checked against the schema sources it is meant to cover: the
 * starter dump under data/backups and the CREATE TABLE statements of the
 * migrations.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The table names of every CREATE TABLE statement in a piece of SQL or PHP.
function pg_test_innodb_created_tables($source)
{
	preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-z0-9_]+)`?\s*\(/i', $source, $matches);

	return array_values(array_unique($matches[1]));
}

// The tables of the starter dump, all created on MyISAM.
function pg_test_innodb_dump_tables()
{
	return pg_test_innodb_created_tables((string) file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/english_default/sql.sql'));
}

// The tables the migrations create on the legacy ENGINE constant (MyISAM):
// the CREATE TABLE statements whose closing parenthesis is followed by
// `" . ENGINE`.
function pg_test_innodb_migration_myisam_tables()
{
	$tables = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/migrations/*.php') as $file) {
		$source = (string) file_get_contents($file);

		if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-z0-9_]+)`?\s*\((?:(?!CREATE\s+TABLE).)*?\)\s*"\s*\.\s*ENGINE\b/is', $source, $matches)) {
			foreach ($matches[1] as $table) {
				$tables[] = $table;
			}
		}
	}

	return array_values(array_unique($tables));
}

// The groups share no table, they hold 144 between them, and the flat list
// has each table once.
function test_innodb_groups_are_disjoint_and_complete()
{
	$seen = array();
	$duplicates = array();
	$total = 0;

	foreach (pg_innodb_table_groups() as $group => $tables) {
		foreach ($tables as $table) {
			$total++;
			if (isset($seen[$table])) {
				$duplicates[] = $table . ' (' . $seen[$table] . ', ' . $group . ')';
			}
			$seen[$table] = $group;
		}
	}

	pg_assert_same(array(), $duplicates, 'tables in more than one group');
	pg_assert_same(144, $total, 'tables in the groups');
	pg_assert_same(array('orders', 'products', 'people', 'site', 'search'), array_keys(pg_innodb_table_groups()), 'group order');

	$core = pg_innodb_core_tables();

	pg_assert_same(144, count($core), 'core tables');
	pg_assert_same(count($core), count(array_unique($core)), 'core tables are unique');
}

// Every table of the list is created somewhere: in the starter dump or by a
// migration.
function test_innodb_tables_exist_in_schema_sources()
{
	$known = pg_test_innodb_dump_tables();

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/migrations/*.php') as $file) {
		$known = array_merge($known, pg_test_innodb_created_tables((string) file_get_contents($file)));
	}

	$known = array_flip($known);

	$unknown = array();

	foreach (pg_innodb_core_tables() as $table) {
		if (!isset($known[$table])) {
			$unknown[] = $table;
		}
	}

	pg_assert_same(array(), $unknown, 'tables no schema source creates');
}

// The list is exactly the MyISAM tables of the sources, less the ones earlier
// versions already moved (visitors 2026.3.6, user and files 2026.4.4) and
// custom_apps, dropped in 2026.4.4. A table added to the dump later and
// forgotten in the list fails here.
function test_innodb_list_covers_every_myisam_source_table()
{
	$dump = pg_test_innodb_dump_tables();

	pg_assert_same(141, count($dump), 'tables in the starter dump');

	$migrations = pg_test_innodb_migration_myisam_tables();

	sort($migrations);

	pg_assert_same(
		array('custom_apps', 'iyzipay_3ds_state', 'local_sale_history', 'local_sale_history_items', 'notifications', 'order_refunds', 'product_barcodes'),
		array_values(array_diff($migrations, $dump)),
		'MyISAM tables created by the migrations outside the dump'
	);

	$expected = array_diff(array_unique(array_merge($dump, $migrations)), array('visitors', 'user', 'files', 'custom_apps'));

	sort($expected);

	$list = pg_innodb_core_tables();

	sort($list);

	pg_assert_same(array_values($expected), $list, 'the list against the sources');
}

// Every name is safe to put between backticks in an ALTER statement.
function test_innodb_table_names_are_safe()
{
	$unsafe = array();

	foreach (pg_innodb_core_tables() as $table) {
		if (preg_match('/^[a-z0-9_]+$/', $table) !== 1) {
			$unsafe[] = $table;
		}
	}

	pg_assert_same(array(), $unsafe, 'unsafe table names');
}

// Sizes read in KB below a megabyte, MB below a gigabyte, GB from there.
function test_innodb_size_label()
{
	pg_assert_same('0 KB', pg_innodb_size_label(0));
	pg_assert_same('16 KB', pg_innodb_size_label(16384));
	pg_assert_same('1 MB', pg_innodb_size_label(1048576));
	pg_assert_same('128 MB', pg_innodb_size_label(134217728));
	pg_assert_same('1.0 GB', pg_innodb_size_label(1073741824));
	pg_assert_same('2.5 GB', pg_innodb_size_label(2684354560));
}

// The record limit is half the free space of an empty page; 64 KB pages are
// capped by the record format itself.
function test_innodb_row_limit()
{
	pg_assert_same(8126, pg_innodb_row_limit(16384), '16 KB');
	pg_assert_same(8126, pg_innodb_row_limit(), 'default');
	pg_assert_same(4030, pg_innodb_row_limit(8192), '8 KB');
	pg_assert_same(1982, pg_innodb_row_limit(4096), '4 KB');
	pg_assert_same(16318, pg_innodb_row_limit(32768), '32 KB');
	pg_assert_same(16383, pg_innodb_row_limit(65536), '64 KB');
}

// One column of each kind the estimate tells apart.
function pg_test_innodb_estimate_fixture()
{
	return array(
		array('name' => 'id', 'type' => 'int', 'octets' => null, 'precision' => 10, 'scale' => 0, 'nullable' => false, 'column_type' => 'int(10) unsigned', 'charset' => null),
		array('name' => 'short_latin1', 'type' => 'varchar', 'octets' => 100, 'precision' => null, 'scale' => null, 'nullable' => false, 'column_type' => 'varchar(100)', 'charset' => 'latin1'),
		array('name' => 'short_utf8mb4', 'type' => 'varchar', 'octets' => 400, 'precision' => null, 'scale' => null, 'nullable' => false, 'column_type' => 'varchar(100)', 'charset' => 'utf8mb4'),
		array('name' => 'body', 'type' => 'text', 'octets' => 65535, 'precision' => null, 'scale' => null, 'nullable' => false, 'column_type' => 'text', 'charset' => 'utf8mb4'),
		array('name' => 'flag', 'type' => 'tinyint', 'octets' => null, 'precision' => 3, 'scale' => 0, 'nullable' => true, 'column_type' => 'tinyint(1)', 'charset' => null),
		array('name' => 'price', 'type' => 'decimal', 'octets' => null, 'precision' => 10, 'scale' => 2, 'nullable' => false, 'column_type' => 'decimal(10,2)', 'charset' => null),
		array('name' => 'mode', 'type' => 'enum', 'octets' => 4, 'precision' => null, 'scale' => null, 'nullable' => false, 'column_type' => "enum('a','b')", 'charset' => 'utf8mb4'),
		array('name' => 'code', 'type' => 'char', 'octets' => 40, 'precision' => null, 'scale' => null, 'nullable' => false, 'column_type' => 'char(10)', 'charset' => 'utf8mb4'),
	);
}

// Worked by hand, 16 KB page:
//   int 4 + varchar(100) latin1 (100 octets, short: 100 + 1) 101
//   + varchar(100) utf8mb4 (400 octets, long: 41) 41 + text 41 + tinyint 1
//   + decimal(10,2) (8 whole digits 4, 2 fraction digits 1) 5 + enum 1
//   + char(10) utf8mb4 (variable, 40 octets, short: 40 + 1) 41    = 235
//   + header 5 + null bitmap (1 nullable column) 1                = 241
//   + DB_ROW_ID, DB_TRX_ID, DB_ROLL_PTR without a primary key 19  = 260
//   + DB_TRX_ID, DB_ROLL_PTR with one 13                          = 254
function test_innodb_row_estimate_counts_inline_and_external()
{
	$estimate = pg_innodb_row_estimate_from_columns(pg_test_innodb_estimate_fixture(), false);

	pg_assert_same(260, $estimate['bytes'], 'bytes without a primary key');
	pg_assert_same(8126, $estimate['limit'], 'limit');
	pg_assert_true($estimate['fits'], 'fits');
	pg_assert_same(8, $estimate['columns'], 'columns');
	pg_assert_same(2, $estimate['inline_columns'], 'inline columns');
	pg_assert_same(2, $estimate['external_columns'], 'external columns');
	pg_assert_same(4, $estimate['fixed_columns'], 'fixed columns');
	pg_assert_same(1, $estimate['nullable'], 'nullable columns');

	pg_assert_same('short_latin1', $estimate['widest'][0]['name'], 'widest column');
	pg_assert_same(101, $estimate['widest'][0]['bytes'], 'widest column bytes');
	pg_assert_same('latin1', $estimate['widest'][0]['charset'], 'widest column charset');
	pg_assert_same(8, count($estimate['widest']), 'widest list');

	$keyed = pg_innodb_row_estimate_from_columns(pg_test_innodb_estimate_fixture(), true);

	pg_assert_same(254, $keyed['bytes'], 'bytes with a primary key');

	$small_page = pg_innodb_row_estimate_from_columns(pg_test_innodb_estimate_fixture(), true, 4096);

	pg_assert_same(1982, $small_page['limit'], 'limit on a 4 KB page');

	// MariaDB counts the two long columns as 21 instead of 41: 260 - 40.
	$mariadb = pg_innodb_row_estimate_from_columns(pg_test_innodb_estimate_fixture(), false, 16384, 'mariadb');

	pg_assert_same(220, $mariadb['bytes'], 'bytes, MariaDB count');
	pg_assert_same(2, $mariadb['inline_columns'], 'inline columns, MariaDB count');

	// MySQL 5.7 counts the short latin1 VARCHAR(100) as 41 instead of 101
	// (over 40 bytes); CHAR(10) in utf8mb4 is 40 bytes and stays 40 + 1.
	$mysql57 = pg_innodb_row_estimate_from_columns(pg_test_innodb_estimate_fixture(), false, 16384, 'mysql57');

	pg_assert_same(200, $mysql57['bytes'], 'bytes, MySQL 5.7 count');
	pg_assert_same(2, $mysql57['inline_columns'], 'inline columns, MySQL 5.7 count');
}

// The count follows the server: MariaDB from 10.4, MySQL from 8.0; the
// older ones count like MySQL 5.7, and an unknown version gets the strict
// count.
function test_innodb_server_rule()
{
	pg_assert_same('mariadb', pg_innodb_server_rule('10.11.14-MariaDB'), 'MariaDB 10.11');
	pg_assert_same('mariadb', pg_innodb_server_rule('10.4.32-MariaDB-log'), 'MariaDB 10.4');
	pg_assert_same('mariadb', pg_innodb_server_rule('11.4.2-MariaDB-ubu2404'), 'MariaDB 11.4');
	pg_assert_same('mariadb', pg_innodb_server_rule('5.5.5-10.6.16-MariaDB'), 'MariaDB behind the 5.5.5 prefix');
	pg_assert_same('mysql57', pg_innodb_server_rule('10.3.39-MariaDB'), 'MariaDB 10.3');
	pg_assert_same('mysql57', pg_innodb_server_rule('10.2.44-MariaDB-log'), 'MariaDB 10.2');
	pg_assert_same('mysql80', pg_innodb_server_rule('8.0.36'), 'MySQL 8.0');
	pg_assert_same('mysql80', pg_innodb_server_rule('8.0.36-28'), 'Percona Server 8.0');
	pg_assert_same('mysql80', pg_innodb_server_rule('9.1.0'), 'MySQL 9.1');
	pg_assert_same('mysql57', pg_innodb_server_rule('5.7.44-log'), 'MySQL 5.7');
	pg_assert_same('mysql80', pg_innodb_server_rule(''), 'unknown');
}

// Many short columns of a single-byte character set, the shape of a latin1
// `config`.
function pg_test_innodb_varchar_columns($charset, $bytes_per_character)
{
	$columns = array();

	foreach (array(100 => 58, 255 => 33) as $length => $count) {
		for ($i = 0; $i < $count; $i++) {
			$columns[] = array(
				'name' => 'c' . $length . '_' . $i,
				'type' => 'varchar',
				'octets' => $length * $bytes_per_character,
				'precision' => null,
				'scale' => null,
				'nullable' => true,
				'column_type' => 'varchar(' . $length . ')',
				'charset' => $charset,
			);
		}
	}

	return $columns;
}

// 58 x VARCHAR(100) + 33 x VARCHAR(255): in latin1 every one of them stays
// inside the row (58 x 101 + 33 x 256 = 14306 bytes); in utf8mb4 every one
// leaves it (91 x 41).
function test_innodb_row_estimate_latin1_config_does_not_fit()
{
	$latin1 = pg_innodb_row_estimate_from_columns(pg_test_innodb_varchar_columns('latin1', 1), false);

	pg_assert_false($latin1['fits'], 'latin1 fits');
	pg_assert_same(5 + 19 + 12 + 14306, $latin1['bytes'], 'latin1 bytes');
	pg_assert_same(91, $latin1['inline_columns'], 'latin1 inline columns');

	$utf8mb4 = pg_innodb_row_estimate_from_columns(pg_test_innodb_varchar_columns('utf8mb4', 4), false);

	pg_assert_true($utf8mb4['fits'], 'utf8mb4 fits');
	pg_assert_same(5 + 19 + 12 + (91 * 41), $utf8mb4['bytes'], 'utf8mb4 bytes');
	pg_assert_same(91, $utf8mb4['external_columns'], 'utf8mb4 external columns');
}

// One column definition ("VARCHAR(255) NOT NULL DEFAULT ''") as the
// estimate wants it, for a utf8mb4 table.
function pg_test_innodb_column_from_definition($name, $definition)
{
	if (!preg_match('/^\s*([a-z]+)(?:\(((?:[^()\']|\'(?:[^\'\\\\]|\\\\.|\'\')*\')*)\))?(.*)$/is', $definition, $match)) {
		return null;
	}

	$type = strtolower($match[1]);
	$arguments = isset($match[2]) ? $match[2] : '';
	$rest = isset($match[3]) ? $match[3] : '';
	$length = (int) $arguments;
	$character_type = in_array($type, array('varchar', 'char', 'tinytext', 'text', 'mediumtext', 'longtext', 'enum', 'set'), true);

	$octets = null;

	if (($type === 'varchar') || ($type === 'char')) {
		$octets = 4 * $length;
	} elseif (($type === 'varbinary') || ($type === 'binary')) {
		$octets = $length;
	}

	$scale = null;

	if ($type === 'decimal') {
		$parts = explode(',', $arguments);
		$scale = isset($parts[1]) ? (int) $parts[1] : 0;
	}

	return array(
		'name' => $name,
		'type' => $type,
		'octets' => $octets,
		'precision' => (($type === 'decimal') || ($type === 'bit')) ? $length : null,
		'scale' => $scale,
		'nullable' => (stripos($rest, 'NOT NULL') === false),
		'column_type' => $type . (($arguments !== '') ? '(' . $arguments . ')' : ''),
		'charset' => $character_type ? 'utf8mb4' : null,
	);
}

// The columns of the stock `config`: the starter dump's CREATE TABLE, then
// every column the migrations add with install_add_column('config', ...) or
// ALTER TABLE config ADD and the dump does not have yet, changed by
// install_modify_column('config', ...), less the ones dropped with
// install_drop_column('config', ...). Checked against a fresh sandbox
// install: the same 414 names.
function pg_test_innodb_stock_config_columns()
{
	$columns = array();
	$added_columns = array();
	$modified = array();

	$dump = (string) file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/english_default/sql.sql');

	if (preg_match('/CREATE TABLE `config` \((.*?)\) ENGINE/s', $dump, $create)) {
		foreach (explode("\n", $create[1]) as $line) {
			if (preg_match('/^\s*`([a-z0-9_]+)`\s+(.*?),?\s*$/i', $line, $column)) {
				$columns[$column[1]] = pg_test_innodb_column_from_definition($column[1], $column[2]);
			}
		}
	}

	$dropped = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/migrations/*.php') as $file) {
		$source = (string) file_get_contents($file);

		preg_match_all('/install_add_column\(\s*[\'"]config[\'"]\s*,\s*[\'"]([a-z0-9_]+)[\'"]\s*,\s*([\'"])(.*?)\2/s', $source, $added, PREG_SET_ORDER);

		foreach ($added as $column) {
			$added_columns[$column[1]] = pg_test_innodb_column_from_definition($column[1], $column[3]);
		}

		// An ALTER TABLE config statement runs to the end of its string and
		// may add several columns ("ADD a ..., ADD b ...").
		preg_match_all('/ALTER\s+TABLE\s+`?config`?\s+(ADD\s.*?)(?:"|$)/is', $source, $statements);

		foreach ($statements[1] as $statement) {
			foreach (preg_split('/,\s*(?=ADD\s)/i', $statement) as $clause) {
				if (preg_match('/^\s*ADD\s+(?:COLUMN\s+)?`?([a-z0-9_]+)`?\s+(.*?)\s*$/is', $clause, $column)) {
					$added_columns[$column[1]] = pg_test_innodb_column_from_definition($column[1], $column[2]);
				}
			}
		}

		preg_match_all('/install_modify_column\(\s*[\'"]config[\'"]\s*,\s*[\'"]([a-z0-9_]+)[\'"]\s*,\s*([\'"])(.*?)\2/s', $source, $changes, PREG_SET_ORDER);

		foreach ($changes as $column) {
			$modified[$column[1]] = pg_test_innodb_column_from_definition($column[1], $column[3]);
		}

		preg_match_all('/install_drop_column\(\s*[\'"]config[\'"]\s*,\s*[\'"]([a-z0-9_]+)[\'"]/', $source, $drops);

		foreach ($drops[1] as $column) {
			$dropped[] = $column;
		}
	}

	// The dump is newer than the legacy ALTER statements, so its definition
	// of a column they add wins.
	foreach ($added_columns as $name => $column) {
		if (!isset($columns[$name])) {
			$columns[$name] = $column;
		}
	}

	// upgrade_2026_4_4_erp_tax_number_width() widens this one from a list.
	$modified['erp_seller_vkn'] = pg_test_innodb_column_from_definition('erp_seller_vkn', "VARCHAR(32) NOT NULL DEFAULT ''");

	foreach ($modified as $name => $column) {
		if (isset($columns[$name])) {
			$columns[$name] = $column;
		}
	}

	foreach ($dropped as $column) {
		unset($columns[$column]);
	}

	return $columns;
}

// The VARCHAR columns of the stock `config`, by name.
function pg_test_innodb_config_varchars($columns)
{
	$names = array();

	foreach ($columns as $name => $column) {
		if ($column['type'] === 'varchar') {
			$names[] = $name;
		}
	}

	return $names;
}

// The stock `config` with what 8.16 (upgrade_2026_4_8_config_text_columns())
// does to it: every VARCHAR becomes TEXT.
function pg_test_innodb_config_after_text_columns($columns)
{
	$changed = array();

	foreach ($columns as $column) {
		if ($column['type'] === 'varchar') {
			$column['type'] = 'text';
			$column['octets'] = 65535;
			$column['column_type'] = 'text';
		}
		$changed[] = $column;
	}

	return $changed;
}

// Before 8.16 the stock `config` is too wide for MySQL 8.0 (8512 bytes of
// 8126) while MySQL 5.7 (7032) and MariaDB 10.4+ (5892) take it. With every
// VARCHAR turned into TEXT it fits all three, with room for ten more long
// columns on MySQL 8.0: once `config` is on InnoDB, the first
// install_add_column() that takes its row past the limit fails on the
// customer's server with MySQL 1118, and this test is meant to fail first.
// A migration that adds or drops a `config` column updates the column count
// below (and, if the room runs out, has to move columns to a table of their
// own).
function test_innodb_stock_config_row_fits()
{
	$columns = pg_test_innodb_stock_config_columns();

	pg_assert_same(array(), array_keys(array_filter($columns, 'is_null')), 'definitions that could not be read');

	pg_assert_same(414, count($columns), 'columns of the stock config');

	$before = array_values($columns);

	pg_assert_same(8512, pg_innodb_row_estimate_from_columns($before, false, 16384, 'mysql80')['bytes'], 'stock config on MySQL 8.0 before 8.16');
	pg_assert_false(pg_innodb_row_estimate_from_columns($before, false, 16384, 'mysql80')['fits'], 'stock config fits MySQL 8.0 before 8.16');
	pg_assert_true(pg_innodb_row_estimate_from_columns($before, false, 16384, 'mysql57')['fits'], 'stock config fits MySQL 5.7 before 8.16');
	pg_assert_true(pg_innodb_row_estimate_from_columns($before, false, 16384, 'mariadb')['fits'], 'stock config fits MariaDB before 8.16');

	$after = pg_test_innodb_config_after_text_columns($columns);

	foreach (array('mysql80', 'mysql57', 'mariadb') as $rule) {
		$estimate = pg_innodb_row_estimate_from_columns($after, false, 16384, $rule);
		pg_assert_true($estimate['fits'], 'config fits on InnoDB, ' . $rule . ' (' . $estimate['bytes'] . ' bytes)');
	}

	$room = 41 * 10;

	$strict = pg_innodb_row_estimate_from_columns($after, false, 16384, 'mysql80');

	pg_assert_true($strict['bytes'] <= ($strict['limit'] - $room), 'config leaves room for ten long columns on MySQL 8.0 (' . $strict['bytes'] . ' of ' . $strict['limit'] . ' bytes)');
}

// The server's own row limit, 65,535 bytes, counts a VARCHAR at its longest
// and a TEXT column as 10 bytes. The stock `config` came to 64,082 bytes
// before 8.16 (the figure a fresh sandbox install measures: 1453 bytes of
// room), so one more VARCHAR(255) - 1022 bytes in utf8mb4 - would have
// refused the next migration on every server. After 8.16 it is to stay at
// least 20 KB under the limit.
function test_innodb_stock_config_sql_row_size()
{
	$columns = pg_test_innodb_stock_config_columns();

	$before = pg_innodb_sql_row_estimate_from_columns(array_values($columns));

	pg_assert_same(64082, $before['bytes'], 'stock config before 8.16');
	pg_assert_true($before['fits'], 'stock config fits the server row before 8.16');

	$after = pg_innodb_sql_row_estimate_from_columns(pg_test_innodb_config_after_text_columns($columns));

	pg_assert_true($after['bytes'] <= (65535 - 20480), 'stock config stays 20 KB under the server row limit (' . $after['bytes'] . ' bytes)');
}

// The server row count, worked by hand: int 4 + VARCHAR(100) latin1 100 + 1
// + VARCHAR(100) utf8mb4 400 + 2 + TEXT 10 + tinyint 1 + decimal(10,2) 5
// + enum 1 + CHAR(10) utf8mb4 40 = 564, + one nullable column 1 = 565.
function test_innodb_sql_row_estimate_counts()
{
	$estimate = pg_innodb_sql_row_estimate_from_columns(pg_test_innodb_estimate_fixture());

	pg_assert_same(565, $estimate['bytes'], 'bytes');
	pg_assert_same(65535, $estimate['limit'], 'limit');
	pg_assert_true($estimate['fits'], 'fits');
}

// The VARCHAR columns of `config` in the schema sources are these, the ones
// 8.16 turns into TEXT. A new text column of `config` is TEXT, not VARCHAR:
// a VARCHAR counts in full against the server's 65,535-byte row, and one of
// 255 bytes or less in full against InnoDB's 8126 (see
// upgrade_2026_4_8_config_text_columns()).
function test_innodb_config_has_no_varchar_columns_in_sources()
{
	$expected = array(
		'url_scheme', 'email_address', 'stats_url',
		'title', 'meta_description', 'organization_name',
		'organization_address_1', 'organization_address_2', 'organization_city',
		'organization_state', 'organization_zip_code', 'organization_country',
		'ecommerce_email_address', 'registration_email_address', 'ecommerce_paypal_payflow_pro_merchant_login',
		'ecommerce_paypal_payflow_pro_password', 'membership_email_address', 'member_id_label',
		'ecommerce_product_restriction_message', 'ecommerce_no_shipping_methods_message', 'opt_in_label',
		'ecommerce_tax_exempt_label', 'affiliate_email_address', 'pay_per_click_flag',
		'ecommerce_paypal_payflow_pro_partner', 'ecommerce_paypal_payflow_pro_user', 'ecommerce_authorizenet_api_login_id',
		'ecommerce_authorizenet_transaction_key', 'proxy_address', 'ecommerce_clearcommerce_client_id',
		'ecommerce_clearcommerce_user_id', 'ecommerce_clearcommerce_password', 'ecommerce_paypal_express_checkout_api_username',
		'ecommerce_paypal_express_checkout_api_password', 'ecommerce_paypal_express_checkout_api_signature', 'ecommerce_first_data_global_gateway_store_number',
		'ecommerce_first_data_global_gateway_pem_file_name', 'version', 'membership_expiration_warning_email_subject',
		'hostname', 'ecommerce_paypal_payments_pro_api_username', 'ecommerce_paypal_payments_pro_api_password',
		'ecommerce_paypal_payments_pro_api_signature', 'usps_user_id', 'ecommerce_givex_primary_hostname',
		'ecommerce_givex_secondary_hostname', 'ecommerce_givex_user_id', 'ecommerce_givex_password',
		'subscription_id', 'google_analytics_web_property_id', 'whos_online_server_url',
		'whos_online_group_id', 'whos_online_chat_button_online_file_name', 'whos_online_chat_button_offline_file_name',
		'badge_label', 'last_sitemap_check_hash', 'ecommerce_reward_program_email_bcc_email_address',
		'ecommerce_reward_program_email_subject', 'ecommerce_sage_merchant_id', 'ecommerce_sage_merchant_key',
		'path', 'installer', 'ecommerce_custom_product_field_1_label',
		'ecommerce_custom_product_field_2_label', 'ecommerce_custom_product_field_3_label', 'ecommerce_custom_product_field_4_label',
		'timezone', 'ecommerce_stripe_api_key', 'ups_key',
		'ups_user_id', 'ups_password', 'ups_account',
		'fedex_key', 'fedex_password', 'fedex_account',
		'fedex_meter', 'mailchimp_key', 'mailchimp_list_id',
		'mailchimp_store_id', 'subscription_key', 'ecommerce_iyzipay_api_key',
		'ecommerce_iyzipay_secret_key', 'barcode_default_type', 'parasut_client_id',
		'parasut_client_secret', 'parasut_username', 'parasut_password',
		'parasut_company_id', 'parasut_default_product_id', 'parasut_default_warehouse_id',
		'indexnow_key', 'waf_external_provider', 'chat_welcome_message',
		'chat_widget_theme', 'chat_widget_color', 'chat_widget_icon',
		'chat_widget_title', 'oauth_google_client_id', 'oauth_google_client_secret',
		'push_vapid_public', 'app_icon', 'signature_tsa_url',
		'signature_tsa_auth', 'signature_tsa_username', 'signature_tsa_password',
		'security_csp_mode', 'erp_default_series', 'erp_web_address',
		'erp_seller_vkn', 'erp_seller_tax_office', 'erp_fx_currencies',
		'erp_number_style', 'erp_tax_name', 'local_sale_prices',
		'erp_tax2_name', 'erp_shipping_tax', 'erp_credit_limit_mode',
		'og_default_image', 'organization_logo', 'merchant_country',
		'ws_ai_license_state', 'translation_source_language', 'backup_remote_type',
		'iyzipay_protected_currency_code',
	);

	pg_assert_same($expected, pg_test_innodb_config_varchars(pg_test_innodb_stock_config_columns()), 'VARCHAR columns of config in the sources');
}

// The too_wide line names the sizes and the short columns; without an
// estimate that says it does not fit it falls back to the server's message.
function test_innodb_state_text_too_wide()
{
	$estimate = pg_innodb_row_estimate_from_columns(pg_test_innodb_varchar_columns('latin1', 1), false);

	$text = pg_innodb_state_text(array('state' => 'too_wide', 'table' => 'config', 'row_estimate' => $estimate));

	pg_assert_contains('config', $text);
	pg_assert_contains(pg_format_number($estimate['bytes'], 0), $text);
	pg_assert_contains(pg_format_number(8126, 0), $text);

	$fallback = pg_innodb_state_text(array('state' => 'too_wide', 'table' => 'config', 'row_estimate' => null, 'errno' => 1118, 'error' => 'Row size too large'));

	pg_assert_contains('1118', $fallback);
	pg_assert_contains('Row size too large', $fallback);
}
