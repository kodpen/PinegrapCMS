<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for /create row (/oluştur satır): the kind word, the table named
 * after it (the whole name, else a prefix only one table has), who is
 * offered it and let through (a person who may write a table), a table the
 * person may not write refused, the card's fields and nothing written
 * before Create, the write through ws_wtable_row_create(), the strip.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/command_registry.php');

// The body of a function in a source file, up to the next function.
function workspace_command_create_row_test_body($source, $function)
{
	$start = strpos($source, "\nfunction " . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, ($end === false) ? strlen($source) : $end - $start);
}

// The body of one method of the composer's object literal in workspace.js.
function workspace_command_create_row_test_js($source, $name)
{
	$start = strpos($source, "\n        " . $name . ': function (');

	if ($start === false) {
		return '';
	}

	$end = preg_match('/\n        [A-Za-z]+: function \(/', $source, $match, PREG_OFFSET_CAPTURE, $start + 1) ? $match[0][1] : strlen($source);

	return substr($source, $start, $end - $start);
}

// "satır", "SATIR" and "row" name a row; the rest is the table's name.
function test_workspace_command_create_row_kind_word()
{
	foreach (array('satır', 'SATIR', 'satir', 'row', 'Row') as $word) {
		$parsed = ws_command_parse_create($word . ' Müşteri adayları');
		pg_assert_same('wtable_row', $parsed['kind'], $word . ' names a row');
		pg_assert_same('Müşteri adayları', $parsed['rest'], $word . ': the rest is the table\'s name');
	}
}

// The whole name without case, else a prefix only one table has; several
// or none leave the choice to the card.
function test_workspace_command_create_row_table_match()
{
	$tables = array(
		array('id' => 1, 'name' => 'Leads'),
		array('id' => 2, 'name' => 'Leads 2026'),
		array('id' => 3, 'name' => 'İş başvuruları'),
		array('id' => 4, 'name' => 'Stock count'),
		array('id' => 5, 'name' => 'Suppliers'),
	);

	pg_assert_same(1, ws_command_row_table_match($tables, 'leads'), 'the whole name wins over a longer one it starts');
	pg_assert_same(2, ws_command_row_table_match($tables, 'LEADS  2026'), 'case and spaces');
	pg_assert_same(3, ws_command_row_table_match($tables, 'iş başvuruları'), 'Turkish letters folded');
	pg_assert_same(3, ws_command_row_table_match($tables, 'is'), 'a prefix only one table has');
	pg_assert_same(4, ws_command_row_table_match($tables, 'stock'), 'another such prefix');
	pg_assert_same(0, ws_command_row_table_match($tables, 's'), 'a prefix two tables have: the card asks');
	pg_assert_same(0, ws_command_row_table_match($tables, 'Orders'), 'no table: the card asks');
	pg_assert_same(0, ws_command_row_table_match($tables, ''), 'nothing typed: the card asks');
	pg_assert_same(0, ws_command_row_table_match($tables, 'counts'), 'a name longer than the table\'s is no match');

	$twins = array(array('id' => 7, 'name' => 'Notes'), array('id' => 8, 'name' => 'notes'));
	pg_assert_same(0, ws_command_row_table_match($twins, 'Notes'), 'two tables of that name: the card asks');
	pg_assert_same(0, ws_command_row_table_match(array(), 'Leads'), 'no tables at all');
}

// A row is offered to whoever may write a table, never through another
// kind's right; a table outside the person's list is refused.
function test_workspace_command_create_row_rights()
{
	$rights = function ($allowed) {
		return function ($viewer, $type, $id) use ($allowed) {
			return in_array($type, $allowed, true);
		};
	};

	pg_assert_same(array('wtable_row'), ws_command_create_types_for(array('id' => 3), $rights(array('wtable_row'))), 'a table to write: rows only');
	pg_assert_same(array('product', 'product_group', 'contact'), ws_command_create_types_for(array('id' => 2), $rights(array('product', 'product_group', 'contact'))), 'the other rights open no row');

	$commands = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
	$may = workspace_command_create_row_test_body($commands, 'ws_command_create_may');
	pg_assert_contains("if (\$type === 'wtable_row') {\n        return ws_command_row_tables(\$viewer) !== array();", $may, 'a row: a table the person may write');
	pg_assert_contains("\$may = 'ws_command_create_may'", workspace_command_create_row_test_body($commands, 'ws_command_create_types_for'), 'the kinds ask it');

	$tables = workspace_command_create_row_test_body($commands, 'ws_command_row_tables');
	pg_assert_contains('ws_wtables_reached($viewer)', $tables, 'among the tables the person reaches');
	pg_assert_contains("ws_wtable_may((string) \$table['access'], 'write')", $tables, 'those they may write');

	$row = workspace_command_create_row_test_body($commands, 'ws_command_row');
	$none = strpos($row, "if (\$tables === array()) {");
	$outside = strpos($row, "if ((\$table_id > 0) && !in_array(\$table_id, \$ids, true)) {");
	pg_assert_true(($none !== false) && ($none < strpos($row, 'ws_wtable_row_create(')), 'nobody without a table is let through');
	pg_assert_true(($outside !== false) && ($outside < strpos($row, "if (!empty(\$options['confirm'])) {")), 'a table the person may not write is refused, the card\'s too');
}

// Nothing is written before Create: the card shows the table's fields and
// Create writes through the table's own create, read as its screen reads.
function test_workspace_command_create_row_nothing_before_create()
{
	$commands = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
	$row = workspace_command_create_row_test_body($commands, 'ws_command_row');
	$confirm = strpos($row, "if (!empty(\$options['confirm'])) {");

	pg_assert_same(1, substr_count($row, 'ws_wtable_row_create('), 'one write');
	pg_assert_true($confirm < strpos($row, 'ws_wtable_row_create('), 'only when confirmed');
	pg_assert_contains("\$cells[\$id] = ws_wtable_screen_cell_from_form(\$column, ", $row, 'cells read as the table screen reads them');
	pg_assert_contains("'columns'  => (\$table_id > 0) ? ws_command_row_columns(\$viewer, \$table_id) : array(),", $row, 'the card shows the table\'s fields, none before a table');
	pg_assert_contains("'type'    => 'create_preview',", substr($row, strrpos($row, 'return array(')), 'otherwise a card');

	$columns = workspace_command_create_row_test_body($commands, 'ws_command_row_columns');
	pg_assert_contains('if (!ws_wtable_type_stores($column[\'type\'])) {', $columns, 'a worked-out column is not asked');
	pg_assert_contains('ws_wtable_link_choices($viewer, $column)', $columns, 'a row link offers the rows the person reads');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	pg_assert_contains("'table_id'  => (int) (\$request['table_id'] ?? 0),", $actions, 'the card\'s table reaches the command');
	pg_assert_contains("'cells'     => (array) (\$request['cells'] ?? array()),", $actions, 'and its fields');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$draw = workspace_command_create_row_test_js($js, 'drawRowCreate');
	pg_assert_contains("if (preview.kind === 'wtable_row') {\n                self.drawRowCreate(command);", workspace_command_create_row_test_js($js, 'drawCreatePreview'), 'a row draws its own card');
	pg_assert_contains('create.disabled = !tableId;', $draw, 'no Create before a table');
	pg_assert_contains('self.send({ body: drawn, table_id: id });', $draw, 'a chosen table draws the card again');
	pg_assert_contains('self.send({ confirm: 1, body: drawn, table_id: tableId, cells: cells });', $draw, 'Create sends the fields');
}

// The strip of /create row names the table; refused cells are named by
// their column.
function test_workspace_command_create_row_strip_and_refusal()
{
	$variants = ws_command_registry()['create']['variants'];
	$row = null;

	foreach ($variants as $variant) {
		if (in_array('satir', $variant['words'], true)) {
			$row = $variant;
		}
	}

	pg_assert_true(is_array($row), 'a strip line for satır');
	pg_assert_same(array('satir', 'row'), $row['words'], 'its words, folded');
	pg_assert_same(array('table'), array_column($row['args'], 'name'), '<table>');
	pg_assert_same('/create row Leads', $row['example'], 'its example');

	foreach ($row['words'] as $word) {
		pg_assert_same('wtable_row', ws_command_create_kinds()[$word] ?? '', $word . ' is a kind the parser reads');
	}

	// "/oluştur satır" typed, the table not yet: the strip already shows
	// the row's own line, never the product's arguments.
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$argument = workspace_command_create_row_test_js($js, 'commandArgument');
	pg_assert_contains("var typing = (!variant && !done.length && word) ? (entry.variants || []).filter(function (item) {\n                return item.words.indexOf(foldText(word)) !== -1;", $argument, 'the kind word being typed picks its line');
	pg_assert_contains("state.shown = { typed: typed + ' ' + word, args: typing.args, example: typing.example };", $argument, 'shown with its arguments and example');
	$strip = workspace_command_create_row_test_js($js, 'drawCommandStrip');
	pg_assert_contains('var entry = shown || argument.entry;', $strip, 'the strip draws the shown line');

	// The card's money field carries its currency as a suffix, as the
	// table screen's row form now does; a checkbox sits on its neighbour's
	// control line.
	$draw = workspace_command_create_row_test_js($js, 'drawRowCreate');
	pg_assert_contains("group.appendChild(el('span', 'pg-input-end', column.hint));", $draw, 'the card\'s currency suffix, the look\'s recipe');
	pg_assert_contains("cell.classList.add('md:pt-7');", $draw, 'the checkbox on the control line');

	// Every control of the three /create cards is a recipe of the look:
	// none of the old Bootstrap buttons, fields or ticks is left.
	foreach (array('drawRecordPreview', 'drawCreatePreview', 'drawRowCreate') as $card) {
		$body = workspace_command_create_row_test_js($js, $card);

		pg_assert_true(strpos($body, 'lookButton(\'pg-button pg-button-fill pg-button-sm\'') !== false, $card . ': its one filled button');
		pg_assert_true(strpos($body, 'lookButton(\'pg-button pg-button-ghost pg-button-sm\', t(\'cancel\'))') !== false, $card . ': Cancel as a ghost');
		pg_assert_false((bool) preg_match('/btn btn-|form-control|input-group|el\(\'input\', \'check\'\)/', $body), $card . ': no old control');
	}

	// A text column keeps its line breaks: a text box, one line high at
	// rest, placed by the card's own class; the recipe is not changed.
	pg_assert_contains("} else if (column.type === 'text') {", $draw, 'a text column has a case of its own');
	pg_assert_contains("input = el('textarea', 'pg-input ws-row-text');", $draw, 'a text column draws a text box');
	pg_assert_contains("var card = el('div', 'ws-preview ws-row-card');", $draw, 'the card carries its placing class');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	$rule = substr($css, (int) strpos($css, '.ws-row-card textarea.pg-input.ws-row-text {'), 400);
	pg_assert_contains('max-height: calc(8lh + 20px);', $rule, 'up to eight lines');
	pg_assert_contains('padding-block: calc((36px - 1lh - 2px) / 2);', $rule, 'one field high at rest');
	pg_assert_contains('field-sizing: content;', $rule, 'growing with its lines');

	pg_assert_contains("lookButton('pg-button pg-button-quiet pg-button-sm', t('qc_all_fields')", workspace_command_create_row_test_js($js, 'drawCreatePreview'), '"All fields" quiet');
	pg_assert_contains("var input = el('input', 'pg-checkbox');", $js, 'the ticks are .pg-checkbox');
	pg_assert_contains("var caption = el('label', 'pg-field-label', label);", $js, 'the labels are .pg-field-label');
	pg_assert_contains("'<span class=\"input-group-text\">' . h((string) \$options['currency']) . '</span></div>'", (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_wtables.php'), 'the screen\'s currency suffix');

	$text = ws_command_row_refusal(array('error' => 'cells_invalid', 'errors' => array(4 => 'not_number')), array(4 => array('name' => 'Amount')));
	pg_assert_true(strpos($text, 'Amount: ') === 0, 'a refused cell names its column');
}
