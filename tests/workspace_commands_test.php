<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the channel commands (includes/workspace/commands.php): how
 * /price and /stock read an amount and a quantity and take the product's tag
 * off the text, how /task reads its people, department, estimate, priority
 * and day words, the names the commands answer to, that the help lists every
 * one of them, and who is offered them when nobody is named.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');

// An amount comes back in minor units whichever way it is written: a comma
// or a point before the cents, thousands grouped by the other mark, a
// currency beside it.
function test_workspace_command_amount_reads_both_decimal_marks()
{
	pg_assert_same(14990, ws_command_parse_amount('149,90'), 'comma');
	pg_assert_same(14990, ws_command_parse_amount('149.90'), 'point');
	pg_assert_same(14990, ws_command_parse_amount('149,9'), 'one decimal');
	pg_assert_same(14900, ws_command_parse_amount('149'), 'whole');
	pg_assert_same(99, ws_command_parse_amount('0,99'), 'cents only');
	pg_assert_same(0, ws_command_parse_amount('0'), 'zero');
	pg_assert_same(124990, ws_command_parse_amount('1.249,90'), 'Turkish grouping');
	pg_assert_same(124990, ws_command_parse_amount('1,249.90'), 'English grouping');
	pg_assert_same(125000, ws_command_parse_amount('1.250'), 'three digits after a point group thousands');
	pg_assert_same(125000, ws_command_parse_amount('1,250'), 'three digits after a comma group thousands');
	pg_assert_same(125000000, ws_command_parse_amount('1.250.000'), 'two groups');
	pg_assert_same(125000050, ws_command_parse_amount('1.250.000,5'), 'groups and cents');
	pg_assert_same(14990, ws_command_parse_amount('149,90 TL'), 'TL after');
	pg_assert_same(14990, ws_command_parse_amount('₺149,90'), 'lira sign');
	pg_assert_same(14990, ws_command_parse_amount('$149.90'), 'dollar sign');
	pg_assert_same(14990, ws_command_parse_amount(' 149.90 usd '), 'code and spaces');
}

// What cannot be read as one amount is refused rather than guessed: the
// preview would otherwise show a number nobody typed.
function test_workspace_command_amount_refuses_what_is_not_an_amount()
{
	pg_assert_same(null, ws_command_parse_amount(''), 'empty');
	pg_assert_same(null, ws_command_parse_amount('abc'), 'words');
	pg_assert_same(null, ws_command_parse_amount('-5'), 'negative');
	pg_assert_same(null, ws_command_parse_amount('1,2345'), 'four digits after the mark');
	pg_assert_same(null, ws_command_parse_amount('12,5,0'), 'the decimal mark twice');
	pg_assert_same(null, ws_command_parse_amount('1.234,567'), 'two marks mixed as groups');
	pg_assert_same(null, ws_command_parse_amount('1,234,5'), 'the grouping mark as the decimal mark');
	pg_assert_same(null, ws_command_parse_amount('12.'), 'a trailing mark');
	pg_assert_same(null, ws_command_parse_amount('1..5'), 'two marks together');
	pg_assert_same(null, ws_command_parse_amount('12 34'), 'two numbers');
	pg_assert_same(null, ws_command_parse_amount('1234567890123'), 'more digits than a price holds');
	pg_assert_same(null, ws_command_parse_amount('12a'), 'a letter after');
}

// 12 sets the stock; +5 and -3 move it.
function test_workspace_command_quantity_absolute_and_relative()
{
	pg_assert_same(array('relative' => false, 'value' => 12), ws_command_parse_quantity('12'), 'absolute');
	pg_assert_same(array('relative' => false, 'value' => 0), ws_command_parse_quantity('0'), 'zero');
	pg_assert_same(array('relative' => true, 'value' => 5), ws_command_parse_quantity('+5'), 'up');
	pg_assert_same(array('relative' => true, 'value' => -3), ws_command_parse_quantity('-3'), 'down');
	pg_assert_same(array('relative' => true, 'value' => 5), ws_command_parse_quantity(' + 5 '), 'spaces');
	pg_assert_same(null, ws_command_parse_quantity(''), 'empty');
	pg_assert_same(null, ws_command_parse_quantity('1,5'), 'a fraction');
	pg_assert_same(null, ws_command_parse_quantity('twelve'), 'words');
	pg_assert_same(null, ws_command_parse_quantity('12 pcs'), 'a unit');
	pg_assert_same(null, ws_command_parse_quantity('12345678'), 'more than seven digits');
	pg_assert_same(null, ws_command_parse_quantity('1 2'), 'two numbers');
}

// The first product tag is the record; the rest of the text is the value.
// A second product stays in the text, so it cannot be read as a value.
function test_workspace_command_record_ref_takes_the_first_tag()
{
	pg_assert_same(array(12, '149,90'), ws_command_record_ref('<#product:12> 149,90', 'product'), 'tag first');
	pg_assert_same(array(12, '149,90'), ws_command_record_ref('149,90 <#product:12>', 'product'), 'tag last');
	pg_assert_same(array(12, '<#product:13> 5'), ws_command_record_ref('<#product:12> <#product:13> 5', 'product'), 'second tag stays');
	pg_assert_same(array(0, '<#order:12> 5'), ws_command_record_ref('<#order:12> 5', 'product'), 'another kind is not a product');
	pg_assert_same(array(0, '149,90'), ws_command_record_ref('149,90', 'product'), 'no tag');
}

// The commands answer to their English and Turkish names.
function test_workspace_command_price_and_stock_names()
{
	pg_assert_same(array('command' => 'price', 'rest' => '<#product:3> 10'), ws_command_split('/price <#product:3> 10'), '/price');
	pg_assert_same(array('command' => 'price', 'rest' => '<#product:3> 10'), ws_command_split('/fiyat <#product:3> 10'), '/fiyat');
	pg_assert_same(array('command' => 'price', 'rest' => ''), ws_command_split('/FİYAT'), '/FİYAT in capitals');
	pg_assert_same(array('command' => 'stock', 'rest' => '<#product:3> +2'), ws_command_split('/stock <#product:3> +2'), '/stock');
	pg_assert_same(array('command' => 'stock', 'rest' => '<#product:3> +2'), ws_command_split('/stok <#product:3> +2'), '/stok');
}

// Without a person every command is offered (the help lists them all); the
// commands that write nothing are offered to anybody.
function test_workspace_command_offered_without_a_person()
{
	pg_assert_true(ws_command_offered(null, 'price'), 'price, nobody named');
	pg_assert_true(ws_command_offered(null, 'stock'), 'stock, nobody named');
	pg_assert_true(ws_command_offered(array('id' => 1, 'role' => 3), 'task'), 'task, anybody');
	pg_assert_true(ws_command_offered(array('id' => 1, 'role' => 3), 'poll'), 'poll, anybody');
}

// Every name a command answers to is in its help entry, so the composer's /
// picker finds the command under each of them, /help included.
function test_workspace_command_every_name_is_in_the_help()
{
	$listed = array();

	foreach (ws_command_help(null) as $entry) {
		foreach ((array) ($entry['names'] ?? array()) as $name) {
			$listed[$name] = true;
		}
	}

	foreach (array_keys(ws_command_names()) as $name) {
		pg_assert_true(isset($listed['/' . $name]), '/' . $name . ' in a help entry');
	}
}

// /task takes the people and the department from their tags, the estimate
// after ~ and the priority after !; what is left is the title.
function test_workspace_command_parse_task_reads_its_parts()
{
	$task = ws_command_parse_task('Logo revizyonu <@user:5> ~2sa !acil');

	pg_assert_same('Logo revizyonu', $task['title'], 'title');
	pg_assert_same(array(5), $task['assignees'], 'the tagged person');
	pg_assert_same(0, $task['department_id'], 'no department');
	pg_assert_same('urgent', $task['priority'], '!acil');
	pg_assert_same(120, $task['estimate_minutes'], '~2sa');
	pg_assert_same(null, $task['due_date'], 'no day');

	$task = ws_command_parse_task('Stand kurulumu <@dept:3>');

	pg_assert_same('Stand kurulumu', $task['title'], 'title beside a department');
	pg_assert_same(3, $task['department_id'], 'the tagged department');
	pg_assert_same(array(), $task['assignees'], 'nobody named');
}

// A weekday is the next one from today, +2g two days on (2026-10-21 is a
// Wednesday).
function test_workspace_command_date_words()
{
	pg_assert_same('2026-10-23', ws_parse_date_word('cuma', '2026-10-21'), 'cuma');
	pg_assert_same('2026-10-23', ws_parse_date_word('+2g', '2026-10-21'), '+2g');
	pg_assert_same('2026-10-23', ws_parse_date_word('FRIDAY', '2026-10-21'), 'FRIDAY in capitals');
}

// The Turkish names are read in any case, with or without their letters.
function test_workspace_command_turkish_names()
{
	pg_assert_same(array('command' => 'task', 'rest' => 'x'), ws_command_split('/Görev x'), '/Görev');
	pg_assert_same(array('command' => 'task', 'rest' => 'x'), ws_command_split('/gorev x'), '/gorev');
	pg_assert_same(array('command' => 'help', 'rest' => ''), ws_command_split('/YARDIM'), '/YARDIM');
	pg_assert_same(array('command' => 'help', 'rest' => ''), ws_command_split('/help'), '/help');
	pg_assert_same(array('gorev', 'görev', 'task', 'g', 't'), ws_command_aliases('task'), 'the names of /task in table order');
}
