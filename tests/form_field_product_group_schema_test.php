<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the 2026.11.0 step that gives form_fields.form_type the
 * 'product_group' value of variant set form templates
 * (upgrade_2026_11_0_form_field_product_group(), 11.0.141): it is called once
 * under its own label and writes no ALTER of its own; run twice against an
 * in-memory form_fields table in a PHP process of its own (the real runner
 * helpers, a fake db layer that stores a value the enum lacks as '', as a
 * non-strict session does) the first run widens the enum and then fills only
 * the template rows stored as '', and the second run writes nothing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function form_field_product_group_schema_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
}

// The body of the step function, from its signature to the closing brace at
// the start of a line.
function form_field_product_group_schema_test_step()
{
	if (!preg_match('/^function upgrade_2026_11_0_form_field_product_group\(\) \{\n(.*?)\n\}\n/ms', form_field_product_group_schema_test_source(), $match)) {
		return '';
	}

	return $match[1];
}

// One form_fields row: id, form_type, page_id, product_id, product_group_id,
// template_field_id.
function form_field_product_group_schema_test_row($id, $form_type, $page_id, $product_id, $product_group_id, $template_field_id)
{
	return array(
		'id' => $id,
		'form_type' => $form_type,
		'page_id' => $page_id,
		'product_id' => $product_id,
		'product_group_id' => $product_group_id,
		'template_field_id' => $template_field_id,
	);
}

// A variant set's template rows stored as '', and every other row shape the
// table holds next to them.
function form_field_product_group_schema_test_rows()
{
	return array(
		form_field_product_group_schema_test_row(1, '', 0, 0, 7, 0),         // template field of set 7
		form_field_product_group_schema_test_row(2, '', 0, 0, 9, 0),         // template field of set 9
		form_field_product_group_schema_test_row(3, 'product', 0, 11, 7, 1), // its copy on a variant
		form_field_product_group_schema_test_row(4, 'product', 0, 11, 0, 0), // a field added to the variant by hand
		form_field_product_group_schema_test_row(5, 'custom', 3, 0, 0, 0),   // a page form's field
		form_field_product_group_schema_test_row(6, '', 0, 0, 0, 0),         // a field of no form
		form_field_product_group_schema_test_row(7, '', 0, 12, 7, 0),        // carries a product
		form_field_product_group_schema_test_row(8, '', 4, 0, 7, 0),         // carries a page
	);
}

// Runs the step twice in a child process against a form_fields table whose
// form_type column has $type. The fake db layer answers the runner's probes
// and the step's count from the arrays, applies the MODIFY and the UPDATE to
// them and logs every write. Null when PHP cannot be started again.
function form_field_product_group_schema_test_run_twice($type, $rows)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgformtype');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('INSTALL_OR_UPDATE', true);

$GLOBALS['fake_columns'] = array(
	'form_type' => array('Type' => $scenario_type, 'Null' => 'NO', 'Default' => ''),
	'page_id' => array('Type' => 'int(10) unsigned', 'Null' => 'NO', 'Default' => '0'),
	'product_id' => array('Type' => 'int(10) unsigned', 'Null' => 'NO', 'Default' => '0'),
	'product_group_id' => array('Type' => 'int(10) unsigned', 'Null' => 'NO', 'Default' => '0'),
	'template_field_id' => array('Type' => 'int(10) unsigned', 'Null' => 'NO', 'Default' => '0'),
);
$GLOBALS['fake_rows'] = $scenario_rows;
$GLOBALS['fake_writes'] = array();

function e($value) { return addslashes((string) $value); }

function lang($text)
{
	if (is_array($text)) {
		return str_replace('{var:1}', (string) $text['vars'], $text['string']);
	}
	return $text;
}

function fake_enum_values($type)
{
	preg_match_all("/'((?:[^']|'')*)'/", $type, $m);
	return $m[1];
}

// The keys of the rows a WHERE of bracketed "column = value" and
// "column <> value" conditions joined by AND selects.
function fake_matching_rows($where)
{
	$conditions = array();

	foreach (preg_split('/\s+AND\s+/', trim($where)) as $condition) {
		if (!preg_match("/^\((\w+) (=|<>) ('[^']*'|\d+)\)$/", trim($condition), $m)) {
			throw new Exception('unexpected condition: ' . $condition);
		}
		$conditions[] = $m;
	}

	$keys = array();

	foreach ($GLOBALS['fake_rows'] as $key => $row) {
		$match = true;
		foreach ($conditions as $m) {
			$quoted = ($m[3][0] === "'");
			$equal = $quoted ? ((string) $row[$m[1]] === substr($m[3], 1, -1)) : ((int) $row[$m[1]] === (int) $m[3]);
			if ($equal !== ($m[2] === '=')) {
				$match = false;
				break;
			}
		}
		if ($match) {
			$keys[] = $key;
		}
	}

	return $keys;
}

function db_value($sql)
{
	if (preg_match("/TABLE_NAME = '([^']+)'/", $sql, $m)) {
		return ($m[1] === 'form_fields') ? 1 : 0;
	}
	if (preg_match('/^SELECT COUNT\(\*\) FROM form_fields WHERE (.*)$/s', $sql, $m)) {
		return (string) count(fake_matching_rows($m[1]));
	}
	throw new Exception('unexpected db_value: ' . $sql);
}

function db_item($sql)
{
	if (preg_match("/^SHOW COLUMNS FROM `form_fields` WHERE Field = '([^']+)'$/", $sql, $m)) {
		return isset($GLOBALS['fake_columns'][$m[1]]) ? (array('Field' => $m[1]) + $GLOBALS['fake_columns'][$m[1]]) : array();
	}
	throw new Exception('unexpected db_item: ' . $sql);
}

function db($sql)
{
	$GLOBALS['fake_writes'][] = $sql;
	if (preg_match("/^ALTER TABLE `form_fields` MODIFY `form_type` ENUM\((.*)\) NOT NULL DEFAULT ''$/s", $sql, $m)) {
		$GLOBALS['fake_columns']['form_type']['Type'] = 'enum(' . $m[1] . ')';
		return true;
	}
	if (preg_match("/^UPDATE form_fields SET form_type = '([^']*)' WHERE (.*)$/s", $sql, $m)) {
		// A non-strict session stores a value the enum lacks as ''.
		$value = in_array($m[1], fake_enum_values($GLOBALS['fake_columns']['form_type']['Type']), true) ? $m[1] : '';
		foreach (fake_matching_rows($m[2]) as $key) {
			$GLOBALS['fake_rows'][$key]['form_type'] = $value;
		}
		return true;
	}
	throw new Exception('unexpected db: ' . $sql);
}

require $pg_dir . '/includes/migrations/runner.php';
require $pg_dir . '/includes/migrations/2026.11.0.php';

$runs = array();
foreach (array(1, 2) as $run) {
	$GLOBALS['fake_writes'] = array();
	$install_runner['executed'] = 0;
	$install_runner['skipped'] = 0;
	upgrade_2026_11_0_form_field_product_group();
	$runs[] = array('writes' => $GLOBALS['fake_writes'], 'executed' => $install_runner['executed'], 'skipped' => $install_runner['skipped']);
}

$form_types = array();
foreach ($GLOBALS['fake_rows'] as $row) {
	$form_types[$row['id']] = $row['form_type'];
}

echo json_encode(array('runs' => $runs, 'type' => $GLOBALS['fake_columns']['form_type']['Type'], 'form_types' => $form_types));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$scenario_type = ' . var_export($type, true) . ";\n"
		. '$scenario_rows = ' . var_export($rows, true) . ";\n"
		. $script . "\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $out), true);

	if (($code !== 0) || !is_array($result) || !isset($result['runs'], $result['type'], $result['form_types'])) {
		return array('error' => implode("\n", $out));
	}

	return $result;
}

// The form_type of every row by id once both runs are over, as a JSON
// round trip gives it back.
function form_field_product_group_schema_test_expected($changed)
{
	$form_types = array();

	foreach (form_field_product_group_schema_test_rows() as $row) {
		$form_types[(string) $row['id']] = in_array($row['id'], $changed, true) ? 'product_group' : $row['form_type'];
	}

	return $form_types;
}

function test_form_field_product_group_schema_step_is_called_once_under_a_label_of_its_own()
{
	$source = form_field_product_group_schema_test_source();

	pg_assert_same(1, substr_count($source, "\tupgrade_2026_11_0_form_field_product_group(); // 11.0.141\n"), 'called once from upgrade_to_2026_11_0()');

	preg_match('/function upgrade_to_2026_11_0\(\) \{(.*?)\n\}\n/s', $source, $entry);
	preg_match_all('#// (11\.0\.\d+)\n#', isset($entry[1]) ? $entry[1] : '', $labels);

	pg_assert_same(1, count(array_keys($labels[1], '11.0.141', true)), 'no other step carries 11.0.141');
}

function test_form_field_product_group_schema_step_changes_the_column_through_the_helper()
{
	$step = form_field_product_group_schema_test_step();

	pg_assert_true($step !== '', 'the step function is found');
	pg_assert_contains("install_column_info('form_fields', 'form_type')", $step, 'the enum is read from the column');
	pg_assert_contains("install_modify_column('form_fields', 'form_type', \$definition);", $step, 'and widened through the helper');
	pg_assert_true(stripos($step, 'ALTER TABLE') === false, 'no ALTER of its own');
	pg_assert_true(preg_match("/install_note\\('(?:[^'\\\\]|\\\\.)+'\\);\\s*\$/", $step) === 1, 'ends with a note for the operator');
}

// The fake stores the value as '' while the enum lacks it, so a backfill sent
// before the MODIFY would leave the template rows as they were.
function test_form_field_product_group_schema_step_widens_the_enum_then_fills_only_template_rows()
{
	$result = form_field_product_group_schema_test_run_twice("enum('','custom','product','shipping','billing')", form_field_product_group_schema_test_rows());

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the step ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	list($first, $second) = $result['runs'];

	pg_assert_same(2, count($first['writes']), 'one MODIFY and one UPDATE');
	pg_assert_same("ALTER TABLE `form_fields` MODIFY `form_type` ENUM('','custom','product','shipping','billing','product_group') NOT NULL DEFAULT ''", $first['writes'][0], 'the value is appended; the null rule and the default stay');
	pg_assert_true(strpos($first['writes'][1], "UPDATE form_fields SET form_type = 'product_group' WHERE ") === 0, 'the backfill follows the MODIFY');
	pg_assert_same(2, $first['executed'], 'both counted as run');
	pg_assert_same("enum('','custom','product','shipping','billing','product_group')", $result['type'], 'the column carries the value');
	pg_assert_same(form_field_product_group_schema_test_expected(array(1, 2)), $result['form_types'], 'only the template rows of sets 7 and 9 change');

	pg_assert_same(array(), $second['writes'], 'the second run writes nothing');
	pg_assert_same(0, $second['executed'], 'nothing counted as run the second time');
	pg_assert_same(2, $second['skipped'], 'the enum and the backfill read as already in place');
}

function test_form_field_product_group_schema_step_keeps_a_value_added_elsewhere()
{
	$result = form_field_product_group_schema_test_run_twice("enum('','custom','product','shipping','billing','quote')", form_field_product_group_schema_test_rows());

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the step ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	pg_assert_same("ALTER TABLE `form_fields` MODIFY `form_type` ENUM('','custom','product','shipping','billing','quote','product_group') NOT NULL DEFAULT ''", $result['runs'][0]['writes'][0], 'the list read from the column is widened, never rewritten');
}

// A column already widened by hand still gets its rows stored as '' filled.
function test_form_field_product_group_schema_step_fills_rows_when_the_enum_already_carries_the_value()
{
	$result = form_field_product_group_schema_test_run_twice("enum('','custom','product','shipping','billing','product_group')", form_field_product_group_schema_test_rows());

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the step ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	list($first, $second) = $result['runs'];

	pg_assert_same(1, count($first['writes']), 'no MODIFY, one UPDATE');
	pg_assert_true(strpos($first['writes'][0], "UPDATE form_fields SET form_type = 'product_group' WHERE ") === 0, 'the backfill');
	pg_assert_same(form_field_product_group_schema_test_expected(array(1, 2)), $result['form_types'], 'only the template rows change');
	pg_assert_same(array(), $second['writes'], 'the second run writes nothing');
}
