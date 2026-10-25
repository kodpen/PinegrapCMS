<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the channel commands (includes/workspace/commands.php): how
 * /price and /stock read an amount and a quantity and take the product's tag
 * off the text, how /task reads its people, department, estimate, priority
 * and day words, the names the commands answer to, that the help lists every
 * one of them, and who is offered them when nobody is named; the registry
 * (ws_commands()) the picker, /help and the argument popup read, the
 * suggestions it offers, and how a mistyped /word is told apart from a path.
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

	foreach (array_keys(ws_command_table()) as $name) {
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

// The registry holds every name the parser answers to, each under exactly
// one command, so the picker, /help and the parser cannot disagree.
function test_workspace_command_registry_holds_every_name_once()
{
	$owners = array();

	foreach (ws_commands(null) as $entry) {
		foreach ((array) $entry['names'] as $name) {
			$owners[$name][] = $entry['key'];
		}
	}

	foreach (ws_command_table() as $name => $command) {
		pg_assert_same(array($command), $owners['/' . $name] ?? array(), '/' . $name . ' belongs to /' . $command . ' only');
	}

	$keys = array_map(function ($entry) {
		return $entry['key'];
	}, ws_commands(null));

	pg_assert_same(array('task', 'assign', 'done', 'postpone', 'decision', 'note', 'plan', 'poll', 'price', 'stock', 'product', 'help'), $keys, 'the commands, in order, with no assistant set up');
}

// Every argument is typed with a known kind, says whether it may be left
// out, and names a suggestion source the composer knows (or none).
function test_workspace_command_registry_arguments_are_typed()
{
	$sources = array('', 'people', 'people_departments', 'tasks', 'records', 'products', 'dates', 'estimates', 'priorities');

	foreach (ws_commands(null) as $entry) {
		pg_assert_true(is_string($entry['description']) && ($entry['description'] !== ''), '/' . $entry['key'] . ' has a description');
		pg_assert_true(strpos($entry['usage'], $entry['name']) === 0, '/' . $entry['key'] . ' usage starts with its name');
		pg_assert_true(in_array($entry['name'], $entry['names'], true), '/' . $entry['key'] . ' shown name is one of its names');

		foreach ($entry['args'] as $arg) {
			$where = '/' . $entry['key'] . ' ' . $arg['name'];

			pg_assert_true(in_array($arg['kind'], ws_command_argument_kinds(), true), $where . ' kind ' . $arg['kind'] . ' is known');
			pg_assert_true(is_bool($arg['optional']), $where . ' optional is a flag');
			pg_assert_true(in_array($arg['suggest'], $sources, true), $where . ' suggests from a known source');
			pg_assert_true(is_string($arg['label']) && ($arg['label'] !== ''), $where . ' has a label');
		}
	}
}

// The help keeps the shape its callers read (the /help list, the composer's
// picker, the command palette), and lists /help itself.
function test_workspace_command_help_keeps_its_shape()
{
	$keys = array();

	foreach (ws_command_help(null) as $entry) {
		pg_assert_true(is_string($entry['command']) && is_array($entry['names']) && is_string($entry['example']) && is_string($entry['description']), 'help entry shape ' . $entry['command']);
		$keys[] = $entry['key'] ?? '';
	}

	pg_assert_true(in_array('help', $keys, true), '/help is listed');
}

// A slash and a word that is no command is a mistyped command; a path, an
// address, two slashes or a slash and a space are text.
function test_workspace_command_unknown_word_is_told_apart_from_text()
{
	pg_assert_same('gorevx', ws_command_unknown('/gorevx'), '/gorevx');
	pg_assert_same('todo', ws_command_unknown('/todo'), '/todo');
	pg_assert_same('todo', ws_command_unknown('  /todo yarın bak'), '/todo with words after it');
	pg_assert_same('', ws_command_unknown('/var/www dosyası'), 'a path');
	pg_assert_same('', ws_command_unknown('//not bir komut'), 'two slashes');
	pg_assert_same('', ws_command_unknown('http://x'), 'an address');
	pg_assert_same('', ws_command_unknown('/ 5'), 'a slash and a space');
	pg_assert_same('', ws_command_unknown('/görev Logo'), 'a command');
	pg_assert_same('', ws_command_unknown('/FİYAT'), 'a command in capitals');
	pg_assert_same('', ws_command_unknown('/claude özetle'), '/claude is the assistant\'s');
	pg_assert_same('', ws_command_unknown('/ai'), '/ai is the assistant\'s');
	pg_assert_same('', ws_command_unknown('/2026 hedefleri'), 'digits are no word');
	pg_assert_same('', ws_command_unknown('merhaba /todo'), 'not at the start');
	pg_assert_same(null, ws_command_split('/gorevx'), 'the parser takes no unknown word');
}

// The hint names the closest commands, Turkish letters folded.
function test_workspace_command_closest_names()
{
	$closest = ws_command_closest('grev');

	pg_assert_same('/görev', $closest[0] ?? '', '/grev is closest to /görev');
	pg_assert_same('/görev', ws_command_closest('gorevx')[0] ?? '', '/gorevx');
	pg_assert_same('/yardım', ws_command_closest('YARDM')[0] ?? '', '/YARDM');
	pg_assert_same('/fiyat', ws_command_closest('fiyta')[0] ?? '', '/fiyta');
	pg_assert_same('/ertele', ws_command_closest('erte')[0] ?? '', 'the start of a name');
	pg_assert_same(array(), ws_command_closest('xyzzyq'), 'nothing close');
	pg_assert_true(count(ws_command_closest('no')) <= 3, 'at most three');
}

// Two slashes before a word post it as text, one slash taken off; text
// that only starts with two slashes is left as it is.
function test_workspace_command_double_slash_posts_the_text()
{
	pg_assert_same('/not bir komut', ws_command_unescape('//not bir komut'), '//not');
	pg_assert_same('/gorevx', ws_command_unescape('//gorevx'), '//gorevx');
	pg_assert_same('// TODO', ws_command_unescape('// TODO'), 'a code comment');
	pg_assert_same('///x', ws_command_unescape('///x'), 'three slashes');
	pg_assert_same('//claude hi', ws_command_unescape('//claude hi'), 'the assistant\'s word');
	pg_assert_same('/görev x', ws_command_unescape('/görev x'), 'one slash');
	pg_assert_same('http://x', ws_command_unescape('http://x'), 'an address');
}

// Every word the argument popup suggests is one the parser reads, in either
// language.
function test_workspace_command_suggestions_are_read_by_the_parser()
{
	foreach (array('tr', 'en') as $language) {
		$values = ws_command_suggestions($language);

		pg_assert_true(count($values['dates']) >= 5, $language . ' dates');

		foreach ($values['dates'] as $item) {
			pg_assert_true(ws_parse_date_word($item['value'], '2026-10-21') !== '', $language . ' date ' . $item['value']);
		}

		foreach ($values['estimates'] as $item) {
			pg_assert_true(ws_parse_estimate($item['value']) > 0, $language . ' estimate ' . $item['value']);
		}

		foreach ($values['priorities'] as $item) {
			pg_assert_true(ws_parse_priority($item['value']) !== '', $language . ' priority ' . $item['value']);
		}
	}

	pg_assert_same('bugün', ws_command_suggestions('tr')['dates'][0]['value'], 'Turkish first day');
	pg_assert_same('today', ws_command_suggestions('en')['dates'][0]['value'], 'English first day');
	pg_assert_same('~30dk', ws_command_suggestions('tr')['estimates'][0]['value'], 'Turkish first estimate');
	pg_assert_same('!acil', ws_command_suggestions('tr')['priorities'][0]['value'], 'Turkish first priority');
}

// /product (/ürün) answers to its names in any case, with or without the
// Turkish letters; they live beside the table of the other commands, which
// keeps its names.
function test_workspace_command_product_names()
{
	pg_assert_same(array('command' => 'product', 'rest' => 'Mavi kupa 149,90'), ws_command_split('/ürün Mavi kupa 149,90'), '/ürün');
	pg_assert_same(array('command' => 'product', 'rest' => 'x'), ws_command_split('/ÜRÜN x'), '/ÜRÜN in capitals');
	pg_assert_same(array('command' => 'product', 'rest' => 'x'), ws_command_split('/urun x'), '/urun');
	pg_assert_same(array('command' => 'product', 'rest' => 'x'), ws_command_split('/product x'), '/product');
	pg_assert_same(array('urun', 'ürün', 'product'), ws_command_aliases('product'), 'the names of /product');
	pg_assert_same(array(), array_keys(ws_command_names(), 'product', true), 'ws_command_names() keeps its own list');
	pg_assert_same('', ws_command_unknown('/ürün Mavi kupa'), '/ürün is no unknown word');
	pg_assert_same('/ürün', ws_command_closest('urn')[0] ?? '', '/urn is closest to /ürün');
	pg_assert_true(ws_command_offered(null, 'product'), 'listed when nobody is named');
}

// The words after /product: the name, then a price as the last word (with
// its currency beside it, or not) and a product group's tag anywhere.
function test_workspace_command_parse_product()
{
	pg_assert_same(array('name' => 'Mavi kupa', 'price' => 14990, 'group_id' => 0), ws_command_parse_product('Mavi kupa 149,90'), 'a name and a price');
	pg_assert_same(array('name' => 'Mavi kupa', 'price' => 14990, 'group_id' => 7), ws_command_parse_product('Mavi kupa 149,90 <#product_group:7>'), 'a group after the price');
	pg_assert_same(array('name' => 'Mavi kupa', 'price' => 14990, 'group_id' => 7), ws_command_parse_product('<#product_group:7> Mavi kupa 149,90'), 'a group first');
	pg_assert_same(array('name' => 'Mavi kupa', 'price' => 14990, 'group_id' => 0), ws_command_parse_product('Mavi kupa 149,90 TL'), 'a currency after the amount');
	pg_assert_same(array('name' => 'Mavi kupa', 'price' => null, 'group_id' => 0), ws_command_parse_product('Mavi kupa'), 'no price');
	pg_assert_same(array('name' => 'KUPA-01', 'price' => null, 'group_id' => 0), ws_command_parse_product('KUPA-01'), 'one word is the name, even a code');
	pg_assert_same(array('name' => '500', 'price' => null, 'group_id' => 0), ws_command_parse_product('500'), 'a number alone is the name');
	pg_assert_same(array('name' => 'Kupa 500', 'price' => 14990, 'group_id' => 0), ws_command_parse_product('Kupa 500 149,90'), 'a number in the name before the price');
	pg_assert_same(array('name' => 'Kupa ₺', 'price' => null, 'group_id' => 0), ws_command_parse_product('Kupa ₺'), 'a currency with no amount');
	pg_assert_same(array('name' => '', 'price' => null, 'group_id' => 7), ws_command_parse_product('<#product_group:7>'), 'a group and no name');
	pg_assert_same(array('name' => '', 'price' => null, 'group_id' => 0), ws_command_parse_product(''), 'nothing');
}

// What a /product card creates: the name as the product's name and as the
// words the store shows, the price and the group when written.
function test_workspace_command_product_fields()
{
	pg_assert_same(
		array('name' => 'Mavi kupa', 'short_description' => 'Mavi kupa', 'price' => 14990, 'group_ids' => array(7)),
		ws_command_product_fields(array('name' => 'Mavi kupa', 'price' => 14990, 'group_id' => 7)),
		'every part'
	);
	pg_assert_same(
		array('name' => 'KUPA-01', 'short_description' => 'KUPA-01'),
		ws_command_product_fields(array('name' => 'KUPA-01', 'price' => null, 'group_id' => 0)),
		'a name only'
	);
	pg_assert_same(
		array('name' => 'Bedava', 'short_description' => 'Bedava', 'price' => 0),
		ws_command_product_fields(array('name' => 'Bedava', 'price' => 0, 'group_id' => 0)),
		'a price of nothing is written'
	);
}
