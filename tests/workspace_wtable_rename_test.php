<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for renaming a column of a workflow table while formulas name it
 * (ws_calc_bind_spans(), ws_calc_rename() in includes/workspace/calc.php,
 * ws_wtable_formulas_renamed() in wtable_rows.php, the column update in
 * wtables.php): a formula naming the column is written with the new name,
 * so that it keeps its value instead of turning into a ref error; a name
 * that would make a formula work out something else is refused, naming the
 * formula; the rename and the formulas are written in one transaction over
 * the table's columns locked. The write runs in a PHP process of its own
 * with a fake db layer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calc.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtables.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_rows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_screen.php');

// The table of the report: Adet (number), Tutar ₺ (money), Toplam = Adet *
// Tutar ₺, and Net, a formula over Toplam.
function ws_wtable_rename_test_columns()
{
	$columns = array(
		'1' => array('Adet', 'number', array()),
		'2' => array('Tutar ₺', 'money', array('decimals' => 2)),
		'3' => array('Toplam', 'formula', array('expression' => 'Adet * Tutar ₺')),
		'4' => array('Net', 'formula', array('expression' => 'Toplam - 2')),
	);

	$out = array();

	foreach ($columns as $id => $column) {
		$out[$id] = array('id' => (int) $id, 'name' => $column[0], 'type' => $column[1], 'options' => ws_wtable_column_options_check($column[1], $column[2])['value']);
	}

	return $out;
}

// The names of a list of columns as ws_calc_bind() takes them.
function ws_wtable_rename_test_known($names)
{
	$known = array();

	foreach ($names as $slot => $text) {
		$known[ws_calc_fold($text)] = array('text' => $text, 'slot' => $slot);
	}

	return $known;
}

// The places of the names: byte offsets, longest names first, a function
// call left alone; ws_calc_bind() puts its tokens at the same places.
function test_workspace_wtable_rename_bind_spans()
{
	$known = ws_wtable_rename_test_known(array(1 => 'Net', 2 => 'Net total', 3 => 'Adet'));

	pg_assert_same(array(
		array('at' => 0, 'length' => 9, 'slot' => 2),
		array('at' => 12, 'length' => 3, 'slot' => 1),
		array('at' => 18, 'length' => 4, 'slot' => 3),
	), ws_calc_bind_spans('Net total - Net + adet + ADET(1;2)', $known), 'ADET( is the count function, not the column');

	pg_assert_same(array('@L2 - @L1 + @L3 + ADET(1;2)', true), ws_calc_bind('Net total - Net + adet + ADET(1;2)', $known));
	pg_assert_same(array('1 + 2', false), ws_calc_bind('1 + 2', $known));
	pg_assert_same(array(), ws_calc_bind_spans('', $known));
}

// The new name is written where the old one was bound, as typed in any
// case; the other names and the function of the same word stay as written.
function test_workspace_wtable_rename_rewrites_the_name()
{
	$before = ws_wtable_rename_test_known(array(1 => 'Adet', 2 => 'Tutar ₺', 3 => 'Net', 4 => 'Net total'));
	$after = ws_wtable_rename_test_known(array(1 => 'Miktar Ğ', 2 => 'Tutar ₺', 3 => 'Net', 4 => 'Net total'));

	$rename = ws_calc_rename('adet * Tutar ₺ + ADET(Adet; 1)', $before, $after, 1, 'Miktar Ğ');
	pg_assert_same('Miktar Ğ * Tutar ₺ + ADET(Miktar Ğ; 1)', $rename['expression']);
	pg_assert_true($rename['changed']);
	pg_assert_true($rename['faithful']);

	$rename = ws_calc_rename('Tutar ₺ * 2', $before, $after, 1, 'Miktar Ğ');
	pg_assert_same('Tutar ₺ * 2', $rename['expression'], 'a formula without the name stays');
	pg_assert_false($rename['changed']);
	pg_assert_true($rename['faithful']);

	// Net total is one name: renaming Net leaves it whole.
	$after = ws_wtable_rename_test_known(array(1 => 'Adet', 2 => 'Tutar ₺', 3 => 'Gross', 4 => 'Net total'));
	$rename = ws_calc_rename('Net total - Net', $before, $after, 3, 'Gross');
	pg_assert_same('Net total - Gross', $rename['expression']);
	pg_assert_true($rename['faithful']);

	// A longer new name holding another name is still read as one.
	$after = ws_wtable_rename_test_known(array(1 => 'Adet', 2 => 'Net adet', 3 => 'Net', 4 => 'Net total'));
	$rename = ws_calc_rename('Tutar ₺ * Net', $before, $after, 2, 'Net adet');
	pg_assert_same('Net adet * Net', $rename['expression']);
	pg_assert_true($rename['faithful']);
}

// A new name that would read another column, or take a figure out of the
// formula, is not faithful; one that fixes a name nobody gave is.
function test_workspace_wtable_rename_unfaithful_names()
{
	$before = ws_wtable_rename_test_known(array(1 => 'Adet', 2 => 'Tutar'));

	// Of two columns of one name the later one is meant: the other column
	// when it comes after the renamed one, the renamed one otherwise.
	$after = ws_wtable_rename_test_known(array(1 => 'Tutar', 2 => 'Tutar'));
	pg_assert_false(ws_calc_rename('Adet * Tutar', $before, $after, 1, 'Tutar')['faithful'], 'Adet would now read the other column');
	pg_assert_true(ws_calc_rename('Tutar * 2', $before, $after, 1, 'Tutar')['faithful'], 'Tutar still reads the other column');

	$after = ws_wtable_rename_test_known(array(2 => 'Tutar', 1 => 'Tutar'));
	pg_assert_false(ws_calc_rename('Adet * Tutar', $before, $after, 1, 'Tutar')['faithful'], 'Tutar would now read the renamed column');
	pg_assert_false(ws_calc_rename('Tutar * 2', $before, $after, 1, 'Tutar')['faithful'], 'Tutar would now read the renamed column');

	$after = ws_wtable_rename_test_known(array(1 => '2', 2 => 'Tutar'));
	pg_assert_false(ws_calc_rename('Tutar * 2', $before, $after, 1, '2')['faithful'], 'the figure 2 would become the column');

	$after = ws_wtable_rename_test_known(array(1 => 'Miktar', 2 => 'Tutar'));
	$rename = ws_calc_rename('Miktar * Tutar + Adet', $before, $after, 1, 'Miktar');
	pg_assert_same('Miktar * Tutar + Miktar', $rename['expression']);
	pg_assert_true($rename['faithful'], 'Miktar was a name nobody gave: a ref error the rename mends');
}

// The report's table: Toplam follows Adet to Miktar Ğ and keeps 37.5; Net,
// over Toplam, follows Toplam's own rename.
function test_workspace_wtable_rename_formulas_follow()
{
	$columns = ws_wtable_rename_test_columns();
	$cells = array('1' => 3, '2' => 1250);

	pg_assert_same(37.5, ws_wtable_formula_values($columns, $cells)['values']['3']);

	$renamed = ws_wtable_formulas_renamed($columns, 1, 'Miktar Ğ');
	pg_assert_true($renamed['ok']);
	pg_assert_same(array('3' => 'Miktar Ğ * Tutar ₺'), $renamed['expressions']);
	pg_assert_same(array('Toplam'), $renamed['formula_names']);

	$columns['1']['name'] = 'Miktar Ğ';
	$out = ws_wtable_formula_values($columns, $cells);
	pg_assert_same('ref', $out['errors']['3'], 'the old expression no longer binds: the fault of the report');

	$columns['3']['options']['expression'] = $renamed['expressions']['3'];
	$out = ws_wtable_formula_values($columns, $cells);
	pg_assert_same(37.5, $out['values']['3']);
	pg_assert_same(35.5, $out['values']['4']);
	pg_assert_same(array(), $out['errors']);

	$renamed = ws_wtable_formulas_renamed($columns, 3, 'Ara toplam');
	pg_assert_same(array('4' => 'Ara toplam - 2'), $renamed['expressions'], 'a formula over a formula follows too');

	$renamed = ws_wtable_formulas_renamed($columns, 3, 'Toplam');
	pg_assert_same(array('ok' => true, 'error' => '', 'expressions' => array(), 'formula_names' => array()), $renamed, 'the same name changes nothing');

	$renamed = ws_wtable_formulas_renamed($columns, 2, 'TUTAR ₺');
	pg_assert_true($renamed['ok']);
	pg_assert_same(array('3' => 'Miktar Ğ * TUTAR ₺'), $renamed['expressions'], 'a change of case is written too');

	$columns['3']['options']['expression'] = 'Toplam * 2';
	pg_assert_same(array('4' => 'Ara toplam - 2'), ws_wtable_formulas_renamed($columns, 3, 'Ara toplam')['expressions'], 'the renamed formula\'s own expression is what the person gives');
}

// A name another column has, or one that would take a figure, is refused
// naming the formula; so is a formula grown past the limit.
function test_workspace_wtable_rename_refused()
{
	$columns = ws_wtable_rename_test_columns();

	$renamed = ws_wtable_formulas_renamed($columns, 1, 'Tutar ₺');
	pg_assert_same(array('ok' => false, 'error' => 'name_changes_formula', 'expressions' => array(), 'formula_names' => array('Toplam')), $renamed);

	// Toplam keeps reading Tutar as 2; Net's figure 2 would become the column.
	$renamed = ws_wtable_formulas_renamed($columns, 2, '2');
	pg_assert_same('name_changes_formula', $renamed['error']);
	pg_assert_same(array('Net'), $renamed['formula_names']);

	$columns['3']['options']['expression'] = 'Adet * Tutar ₺' . str_repeat(' + Adet', 140);
	pg_assert_true(ws_wtable_length($columns['3']['options']['expression']) <= ws_wtable_limits()['formula']);
	$renamed = ws_wtable_formulas_renamed($columns, 1, 'Satılan adet');
	pg_assert_same('name_formula_too_long', $renamed['error']);
	pg_assert_same(array('Toplam'), $renamed['formula_names']);
}

// The screen names the formulas a rename wrote into, or the ones it would
// have changed.
function test_workspace_wtable_rename_screen_texts()
{
	pg_assert_same('The column was saved.', ws_wtable_screen_column_saved_text(array('ok' => true)));
	pg_assert_same('The column was saved. These formulas now use its new name: Toplam, Net.',
		ws_wtable_screen_column_saved_text(array('ok' => true, 'formula_names' => array('Toplam', 'Net'))));
	pg_assert_same('With this name a formula of the table would work out something else: choose another name. Formulas: Toplam.',
		ws_wtable_screen_refusal_text(array('error' => 'name_changes_formula', 'formula_names' => array('Toplam'))));
	pg_assert_same('With this name a formula of the table would be longer than 1000 characters.',
		ws_wtable_screen_refusal_text(array('error' => 'name_formula_too_long')));
}

// Renames column $id of the report's table to $name, as the owner, with a
// fake db layer. $meanwhile is a formula column another request added
// after the first check and before the lock: the locked read sees it.
// Returns the answer, the statements in order and the columns stored after.
function ws_wtable_rename_test_run($id, $name, $meanwhile = null)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgrename');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('PG_FUNCTIONS_DIR', $pg_dir);

function lang($text) { return is_array($text) ? (string) $text['string'] : (string) $text; }
function e($value) { return addslashes((string) $value); }
function pg_schema_has($table) { return true; }
function ws_rights_for_id($user_id) { return array('member' => true); }

$GLOBALS['statements'] = array();
$GLOBALS['stored'] = array(
	'1' => array('Adet', 'number', '{"decimals":0}'),
	'2' => array('Tutar ₺', 'money', '{"decimals":2}'),
	'3' => array('Toplam', 'formula', '{"expression":"Adet * Tutar ₺"}'),
	'4' => array('Net', 'formula', '{"expression":"Toplam - 2"}'),
);

function ws_wtable_rename_test_rows()
{
	$rows = array();

	foreach ($GLOBALS['stored'] as $id => $column) {
		$rows[] = array('id' => $id, 'table_id' => 7, 'name' => $column[0], 'type' => $column[1], 'options' => $column[2], 'position' => $id, 'created_at' => 0);
	}

	return $rows;
}

function db_items($sql)
{
	if (strpos($sql, 'FROM ws_wtable_columns WHERE table_id') === false) {
		throw new Exception('unexpected db_items: ' . $sql);
	}

	if (strpos($sql, 'FOR UPDATE') !== false) {
		$GLOBALS['statements'][] = 'lock columns';

		if ($GLOBALS['meanwhile'] !== null) {
			$GLOBALS['stored']['5'] = $GLOBALS['meanwhile'];
		}
	}

	return ws_wtable_rename_test_rows();
}

function db($sql)
{
	$sql = trim($sql);

	if (in_array($sql, array('START TRANSACTION', 'COMMIT', 'ROLLBACK'), true)) {
		$GLOBALS['statements'][] = $sql;
		return true;
	}

	if (preg_match("/^UPDATE ws_wtable_columns SET (?:name = '((?:[^'\\\\]|\\\\.)*)', )?options = '((?:[^'\\\\]|\\\\.)*)' WHERE id = '([0-9]+)' AND table_id = '7'$/", $sql, $match)) {
		if ($match[1] !== '') {
			$GLOBALS['stored'][$match[3]][0] = stripslashes($match[1]);
		}

		$GLOBALS['stored'][$match[3]][2] = stripslashes($match[2]);
		$GLOBALS['statements'][] = 'write column ' . $match[3];
		return true;
	}

	if (strpos($sql, 'UPDATE ws_wtables SET revision = revision + 1') === 0) {
		$GLOBALS['statements'][] = 'table revision';
		return true;
	}

	throw new Exception('unexpected db: ' . $sql);
}

function db_item($sql) { throw new Exception('unexpected db_item: ' . $sql); }
function db_value($sql) { throw new Exception('unexpected db_value: ' . $sql); }

require $pg_dir . '/includes/workspace/calc.php';
require $pg_dir . '/includes/workspace/wtables.php';
require $pg_dir . '/includes/workspace/wtable_rows.php';
require $pg_dir . '/includes/workspace/wtable_lookup.php';
require $pg_dir . '/includes/workspace/wtable_rollup.php';

$viewer = array('id' => 3, 'member' => true);
$table = array('id' => 7, 'user_id' => 3);
$columns = ws_wtable_columns(7);
$GLOBALS['statements'] = array();

$result = ws_wtable_column_update($viewer, $table, $columns[$GLOBALS['renamed']], array('name' => $GLOBALS['new_name'], 'options' => array('decimals' => 0)));
$after = array();

foreach (ws_wtable_columns(7) as $id => $column) {
	$after[$id] = array('name' => $column['name'], 'options' => $column['options']);
}

echo json_encode(array('result' => $result, 'statements' => $GLOBALS['statements'], 'stored' => $after), JSON_UNESCAPED_UNICODE);
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$GLOBALS[\'renamed\'] = ' . var_export((string) $id, true) . ";\n"
		. '$GLOBALS[\'new_name\'] = ' . var_export($name, true) . ";\n"
		. '$GLOBALS[\'meanwhile\'] = ' . var_export($meanwhile, true) . ";\n"
		. $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $lines), true);

	if (($code !== 0) || !is_array($result) || !isset($result['result'])) {
		return array('error' => implode("\n", $lines));
	}

	return $result;
}

// The rename and the formula it rewrites are written in one transaction,
// after the columns are locked and checked again.
function test_workspace_wtable_rename_written_with_the_formulas()
{
	$run = ws_wtable_rename_test_run(1, 'Miktar Ğ');

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_true($run['result']['ok']);
	pg_assert_same(array('Toplam'), $run['result']['formula_names']);
	pg_assert_same(array('START TRANSACTION', 'lock columns', 'write column 1', 'write column 3', 'COMMIT', 'table revision'), $run['statements']);
	pg_assert_same('Miktar Ğ', $run['stored']['1']['name']);
	pg_assert_same(array('expression' => 'Miktar Ğ * Tutar ₺'), $run['stored']['3']['options']);
	pg_assert_same(array('expression' => 'Toplam - 2'), $run['stored']['4']['options']);
}

// A formula added meanwhile is read under the lock and rewritten too.
function test_workspace_wtable_rename_sees_a_formula_added_meanwhile()
{
	$run = ws_wtable_rename_test_run(1, 'Miktar Ğ', array('Çift', 'formula', '{"expression":"Adet * 2"}'));

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_same(array('Toplam', 'Çift'), $run['result']['formula_names']);
	pg_assert_same(array('expression' => 'Miktar Ğ * 2'), $run['stored']['5']['options']);
}

// A refused name writes nothing, the column's name included.
function test_workspace_wtable_rename_refused_writes_nothing()
{
	$run = ws_wtable_rename_test_run(1, 'Tutar ₺');

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_false($run['result']['ok']);
	pg_assert_same('name_changes_formula', $run['result']['error']);
	pg_assert_same(409, $run['result']['status']);
	pg_assert_same('name', $run['result']['field']);
	pg_assert_same(array('Toplam'), $run['result']['formula_names']);
	pg_assert_same(array(), $run['statements']);
	pg_assert_same('Adet', $run['stored']['1']['name']);

	// A column of the same name added meanwhile refuses it under the lock.
	$run = ws_wtable_rename_test_run(1, 'Kalan', array('Kalan', 'number', '{"decimals":0}'));
	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_same('name_changes_formula', $run['result']['error']);
	pg_assert_same(array('START TRANSACTION', 'lock columns', 'ROLLBACK'), $run['statements']);
	pg_assert_same('Adet', $run['stored']['1']['name']);
}
