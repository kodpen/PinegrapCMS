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
 * with the reason, as are the columns the setup SQL does not have; the
 * widths of those tables' text columns are written out from their step.
 * channel.summary is the one field wider than its column, listed with why.
 *
 * The record drawer draws a field from its limit (saValueInput() in
 * assets/js/workspace.js, run under node where there is one): a short long
 * field is one line unless its value holds a line break.
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

// The text columns of those tables that a field writes, as table.column =>
// the most characters the column holds (TEXT types at four bytes a
// character, as workspace_change_limits_test_columns() counts), written out
// from the step that last made or changed the column: all 2026.4.4.
function workspace_change_limits_test_upgrade_columns()
{
	return array(
		'erp_accounts.title'         => 255,
		'erp_accounts.email'         => 255,
		'erp_accounts.phone'         => 50,
		'erp_accounts.address'       => 255,
		'erp_accounts.district'      => 100,
		'erp_accounts.city'          => 100,
		'erp_accounts.state'         => 100,
		'erp_accounts.postcode'      => 20,
		'erp_accounts.tax_number'    => 32,
		'erp_accounts.tax_office'    => 100,
		'erp_accounts.notes'         => 16383,
		'erp_accounts.country_code'  => 2,
		'erp_accounts.currency'      => 3,
		'erp_accounts.invoice_email' => 255,
		'ws_channels.summary'        => 16383,
		'ws_tasks.title'             => 255,
		'ws_tasks.description'       => 16383,
		'ws_task_notes.body'         => 16383,
		'ws_notes.title'             => 200,
		'ws_notes.body'              => 4194303,
	);
}

// Fields whose limit is wider than the four-bytes-a-character width of
// their column, kept on purpose, as kind.field => why.
function workspace_change_limits_test_wider_on_purpose()
{
	return array(
		'channel.summary' => 'the summary tab keeps 20000 characters as well (ws_channel_set_summary()); ws_channels.summary is TEXT (65535 bytes), which 20000 characters pass only with many four-byte ones (emoji); a wider column is a schema step',
	);
}

// A limit as a number: a literal, or the "defined('X') ? X : N" a module
// constant is read through (the constant when it is defined, else N); null
// for anything else.
function workspace_change_limits_test_limit($limit)
{
	if (is_int($limit)) {
		return $limit;
	}

	if (($limit instanceof PgTestSourceExpression) && preg_match('/^defined\(\'([A-Z0-9_]+)\'\) \? \1 : (\d+)$/', $limit->code, $match)) {
		return defined($match[1]) ? (int) constant($match[1]) : (int) $match[2];
	}

	return null;
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

// A text field on a table an upgrade step creates, against the width its
// step gives the column; a new field needs its column written out first.
function test_workspace_change_limits_upgrade_table_fields_fit_their_columns()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/task_work.php');

	$upgrade_tables = workspace_change_limits_test_upgrade_tables();
	$widths = workspace_change_limits_test_upgrade_columns();
	$wider = workspace_change_limits_test_wider_on_purpose();
	$written = array();

	foreach (workspace_change_limits_test_fields() as $label => $field) {
		list($kind, $limit, $table, $column) = $field;

		if (!in_array($kind, workspace_change_limits_test_text_kinds(), true) || !isset($upgrade_tables[$table])) {
			continue;
		}

		$name = $table . '.' . $column;
		$written[$name] = true;
		$limit = workspace_change_limits_test_limit($limit);

		if (!pg_assert_true(isset($widths[$name]), $label . ': ' . $name . ' is in workspace_change_limits_test_upgrade_columns()')) {
			continue;
		}

		if (!pg_assert_true(is_int($limit) && ($limit > 0), $label . ': the limit is a number')) {
			continue;
		}

		if (isset($wider[$label])) {
			pg_assert_true($limit > $widths[$name], $label . ': still wider than ' . $name . ' (else drop it from workspace_change_limits_test_wider_on_purpose())');
			continue;
		}

		pg_assert_true($limit <= $widths[$name], $label . ': the limit ' . $limit . ' fits ' . $name . ' (' . $widths[$name] . ' characters)');
	}

	foreach (array_keys($widths) as $name) {
		pg_assert_true(isset($written[$name]), $name . ' is written by a text field');
	}

	pg_assert_same(array('channel.summary'), array_keys($wider), 'the fields wider than their columns on purpose');
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

// Runs a script under node with saValueInput() from assets/js/workspace.js
// above it and the helpers it calls stubbed (an element is its tag name and
// value): the script's JSON output, an array with "error" when it failed, or
// null where node cannot be run.
function workspace_change_limits_test_value_input($script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$start = strpos($source, 'function saValueInput(');
	$end = ($start === false) ? false : strpos($source, 'function saLabelled(', $start);

	if (($start === false) || ($end === false)) {
		return array('error' => 'workspace.js has no saValueInput() followed by saLabelled()');
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwslimit');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, "var t = function (key) { return key; };\n"
		. "var el = function (tag) { return { tagName: String(tag).toUpperCase(), value: '', classList: { add: function () {} } }; };\n"
		. "var select = function () { return el('select'); };\n"
		. substr($source, $start, $end - $start) . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		return array('error' => implode("\n", $out));
	}

	return json_decode(implode("\n", $out), true);
}

// A long field of 255 characters or less is drawn as one line, unless its
// value already holds a line break: a one-line input drops the break, and a
// drawer save of any other field of the record would then send the value
// back without it as a change (a group's or an event's short description).
function test_workspace_change_limits_a_short_long_field_keeps_its_line_breaks()
{
	$short = array('kind' => 'long', 'limit' => array('max_length' => 100));
	$cases = array(
		'one line, limit 100'          => array($short, 'Fresh coffee', 'INPUT'),
		'empty, limit 100'             => array($short, null, 'INPUT'),
		'a line feed, limit 100'       => array($short, "Fresh\ncoffee", 'TEXTAREA'),
		'a carriage return, limit 255' => array(array('kind' => 'long', 'limit' => array('max_length' => 255)), "Fresh\r\ncoffee", 'TEXTAREA'),
		'one line, limit 256'          => array(array('kind' => 'long', 'limit' => array('max_length' => 256)), 'Fresh coffee', 'TEXTAREA'),
		'one line, no limit'           => array(array('kind' => 'long'), 'Fresh coffee', 'TEXTAREA'),
	);
	$input = array();

	foreach ($cases as $case) {
		$input[] = array($case[0], $case[1]);
	}

	$result = workspace_change_limits_test_value_input(
		'var cases = ' . json_encode($input) . ";\n"
		. 'console.log(JSON.stringify(cases.map(function (c) { return saValueInput(c[0], c[1]).tagName; })));'
	);

	if ($result === null) {
		return;
	}

	if (!pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran')) {
		return;
	}

	$i = 0;

	foreach ($cases as $label => $case) {
		pg_assert_same($case[2], $result[$i++] ?? null, $label);
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
