<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The length limits of the Workspace's change catalogue, ws_change_types():
 * a proposal, the record drawer, a bulk change and a scheduled change write
 * a text field into its column, and the session runs without strict mode,
 * so MySQL would cut a value longer than the column in silence while the
 * decision shows the whole text. Every text limit has to fit the column it
 * is written to, as the setup SQL defines it (as
 * tests/api_text_bounds_test.php holds the external API's limits), so a
 * limit widened past its column fails here.
 *
 * The catalogue is read from source (tests/helpers/source_reader.php): the
 * function drops kinds and fields after schema probes a test cannot run.
 * Tables an upgrade step creates are not in the setup SQL and are listed
 * with the reason, as are the columns the setup SQL does not have.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');

// The kinds of field whose limit is a number of characters written to a
// column (ws_change_value()).
function workspace_change_limits_test_text_kinds()
{
	return array('text', 'long', 'email', 'keywords', 'code');
}

// The columns of one table in a setup SQL file, as name => the most
// characters the column holds, or null for a column that holds no text (a
// number, a date, an enum). varchar(N) and char(N) hold N characters; a TEXT
// type holds bytes, and a utf8mb4 character takes up to four. Null when the
// setup SQL has no such table.
function workspace_change_limits_test_columns($table, $setup = 'english_default')
{
	static $files = array();

	if (!isset($files[$setup])) {
		$files[$setup] = (string) file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/' . $setup . '/sql.sql');
	}

	if (!preg_match('/CREATE TABLE `' . preg_quote($table, '/') . '` \((.*?)\) ENGINE/s', $files[$setup], $match)) {
		return null;
	}

	preg_match_all('/^\s*`([a-z0-9_]+)` ([a-z]+)(?:\((\d+)\))?/m', $match[1], $columns, PREG_SET_ORDER);

	$text_bytes = array('tinytext' => 255, 'text' => 65535, 'mediumtext' => 16777215, 'longtext' => 4294967295);
	$out = array();

	foreach ($columns as $column) {
		$type = strtolower($column[2]);

		if (in_array($type, array('varchar', 'char'), true) && isset($column[3])) {
			$out[$column[1]] = (int) $column[3];
		} elseif (isset($text_bytes[$type])) {
			$out[$column[1]] = intdiv($text_bytes[$type], 4);
		} else {
			$out[$column[1]] = null;
		}
	}

	return $out;
}

// Tables a kind of change writes that an upgrade step creates: the setup SQL
// has none of them, so their widths are read in the step itself
// (includes/migrations/) when a limit on them changes.
function workspace_change_limits_test_upgrade_tables()
{
	return array(
		'erp_accounts'  => 'created by the ERP module\'s upgrade (2026.4.4); its tax_number was widened to 32 later in the same version',
		'ws_channels'   => 'created by the Workspace\'s upgrade (2026.4.4)',
		'ws_tasks'      => 'created by the Workspace\'s upgrade (2026.4.4)',
		'ws_task_notes' => 'created by the Workspace\'s upgrade (2026.4.4)',
		'ws_notes'      => 'created by the Workspace\'s upgrade (2026.4.4)',
	);
}

// Columns of a table in the setup SQL that the setup SQL does not have, as
// table.column => why.
function workspace_change_limits_test_columns_elsewhere()
{
	return array(
		'products.vat_exemption_code' => 'added by an upgrade step (2026.4.4)',
		'contacts.tax_number'         => 'added by an upgrade step (2026.1.17)',
		'contacts.tax_office'         => 'added by an upgrade step (2026.1.17)',
		'orders.notes'                => 'made by no upgrade step; ws_change_types() offers the field only where the column is',
		'files.content'               => 'not a column: the file on disk, read by ws_change_record() and written by the file writer',
	);
}

// Every field of the catalogue with a column: kind.field => [kind of value,
// limit, table, column]. Fields a new record or a membership takes have no
// column; a column starting with "__" is read by code, not from the table.
function workspace_change_limits_test_fields()
{
	$out = array();

	foreach (pg_test_ws_change_types() as $kind => $type) {
		foreach (array('fields', 'create', 'members') as $list) {
			foreach (($type[$list] ?? array()) as $name => $field) {
				$column = (string) $field[3];

				if (($column === '') || (strpos($column, '__') === 0)) {
					continue;
				}

				$out[$kind . '.' . $name] = array($field[0], $field[1], $type['table'], $column);
			}
		}
	}

	return $out;
}

function test_workspace_change_limits_the_catalogue_is_read()
{
	$types = pg_test_ws_change_types();

	foreach (array('product', 'product_group', 'page', 'calendar_event', 'contact') as $kind) {
		pg_assert_true(isset($types[$kind]['fields']) && (count($types[$kind]['fields']) > 0), $kind . ' is read from ws_change_types()');
	}

	pg_assert_true(count(workspace_change_limits_test_fields()) > 100, 'the catalogue\'s fields are read');
}

function test_workspace_change_limits_the_setup_files_agree()
{
	$tables = array();

	foreach (workspace_change_limits_test_fields() as $field) {
		$tables[$field[2]] = true;
	}

	foreach (array_keys($tables) as $table) {
		$english = workspace_change_limits_test_columns($table);

		if ($english === null) {
			continue;
		}

		pg_assert_same($english, workspace_change_limits_test_columns($table, 'turkish_default'), $table . ' has the same columns in both setups');
	}
}

function test_workspace_change_limits_text_fields_fit_their_columns()
{
	$upgrade_tables = workspace_change_limits_test_upgrade_tables();
	$elsewhere = workspace_change_limits_test_columns_elsewhere();
	$checked = 0;

	foreach (workspace_change_limits_test_fields() as $label => $field) {
		list($kind, $limit, $table, $column) = $field;

		if (!in_array($kind, workspace_change_limits_test_text_kinds(), true) || isset($upgrade_tables[$table]) || isset($elsewhere[$table . '.' . $column])) {
			continue;
		}

		$columns = workspace_change_limits_test_columns($table);

		if (!pg_assert_true($columns !== null, $label . ': the setup SQL has the table ' . $table . ' (else list it in workspace_change_limits_test_upgrade_tables())')) {
			continue;
		}

		if (!pg_assert_true(array_key_exists($column, $columns), $label . ': the setup SQL has ' . $table . '.' . $column . ' (else list it in workspace_change_limits_test_columns_elsewhere())')) {
			continue;
		}

		if (!pg_assert_true($columns[$column] !== null, $label . ': ' . $table . '.' . $column . ' holds text')) {
			continue;
		}

		if (!pg_assert_true(is_int($limit) && ($limit > 0), $label . ': the limit is a number written out')) {
			continue;
		}

		pg_assert_true($limit <= $columns[$column], $label . ': the limit ' . $limit . ' fits ' . $table . '.' . $column . ' (' . $columns[$column] . ' characters)');
		$checked++;
	}

	pg_assert_true($checked > 60, 'the text fields are checked (' . $checked . ')');
}

// A listed table or column the setup SQL has after all, or that no field
// writes any more, is checked like the others or has gone: the entry goes.
function test_workspace_change_limits_the_lists_hold_no_stale_entries()
{
	$fields = workspace_change_limits_test_fields();
	$tables = array();
	$columns = array();

	foreach ($fields as $field) {
		$tables[$field[2]] = true;
		$columns[$field[2] . '.' . $field[3]] = true;
	}

	foreach (array_keys(workspace_change_limits_test_upgrade_tables()) as $table) {
		pg_assert_true(isset($tables[$table]), $table . ' is written by a kind of change');
		pg_assert_same(null, workspace_change_limits_test_columns($table), $table . ' is not in the setup SQL');
	}

	foreach (array_keys(workspace_change_limits_test_columns_elsewhere()) as $name) {
		list($table, $column) = explode('.', $name, 2);
		$setup = workspace_change_limits_test_columns($table);

		pg_assert_true(isset($columns[$name]), $name . ' is written by a field');
		pg_assert_true(is_array($setup), $table . ' is in the setup SQL');
		pg_assert_false(is_array($setup) && array_key_exists($column, $setup), $name . ' is not in the setup SQL');
	}
}

// The six limits that were once wider than their columns, by value, so a
// wider column has to be a deliberate change here as well.
function test_workspace_change_limits_once_wider_than_their_columns()
{
	$types = pg_test_ws_change_types();
	$expected = array(
		'product_group.short_description'  => 100,
		'product_group.keywords'           => 255,
		'page.meta_description'            => 255,
		'calendar_event.name'              => 100,
		'calendar_event.short_description' => 255,
		'calendar_event.location'          => 100,
	);

	foreach ($expected as $label => $limit) {
		list($kind, $name) = explode('.', $label, 2);

		pg_assert_same($limit, $types[$kind]['fields'][$name][1] ?? null, $label);
	}
}
