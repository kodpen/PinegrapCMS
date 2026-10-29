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
 * suggestions it offers, and how a mistyped /word is told apart from a path;
 * the registry as GET /workspace/commands lists it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bulk.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bulk_sources.php');

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

	pg_assert_same(array('task', 'assign', 'done', 'postpone', 'status', 'timer', 'schedule', 'away', 'meeting', 'decision', 'note', 'plan', 'poll', 'price', 'stock', 'create', 'product', 'help'), $keys, 'the commands, in order, with no assistant set up');
}

// Every argument is typed with a known kind, says whether it may be left
// out, and names a suggestion source the composer knows (or none).
function test_workspace_command_registry_arguments_are_typed()
{
	$sources = array('', 'people', 'people_departments', 'tasks', 'records', 'products', 'dates', 'estimates', 'priorities', 'statuses', 'kinds');

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

		$statuses = array();

		foreach ($values['statuses'] as $item) {
			$statuses[] = ws_parse_status_word($item['value']);
		}

		pg_assert_same(array('todo', 'doing', 'waiting', 'done', 'cancelled'), $statuses, $language . ' statuses, every one read');

		$kinds = array();

		foreach ($values['kinds'] as $item) {
			$kinds[] = ws_command_parse_create($item['value'] . ' x')['kind'];
		}

		pg_assert_same(array('product', 'product_group'), $kinds, $language . ' kinds, every one read');
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

// /create (/oluştur) answers to its names in any case, with or without the
// Turkish letters, and is offered with the product right as /product is.
function test_workspace_command_create_names()
{
	pg_assert_same(array('command' => 'create', 'rest' => 'ürün Mavi kupa'), ws_command_split('/oluştur ürün Mavi kupa'), '/oluştur');
	pg_assert_same(array('command' => 'create', 'rest' => 'grup x'), ws_command_split('/OLUŞTUR grup x'), '/OLUŞTUR in capitals');
	pg_assert_same(array('command' => 'create', 'rest' => 'grup x'), ws_command_split('/olustur grup x'), '/olustur');
	pg_assert_same(array('command' => 'create', 'rest' => 'group x'), ws_command_split('/create group x'), '/create');
	pg_assert_same(array('oluştur', 'olustur', 'create'), ws_command_aliases('create'), 'the names of /create');
	pg_assert_true(ws_command_offered(null, 'create'), 'listed when nobody is named');

	$entry = null;

	foreach (ws_commands(null) as $item) {
		if ($item['key'] === 'create') {
			$entry = $item;
		}
	}

	pg_assert_same(array('record_kind', 'kinds'), array($entry['args'][0]['kind'], $entry['args'][0]['suggest']), 'the kind comes first, from the kinds list');
	pg_assert_true($entry['args'][1]['multiple'], 'the name takes the rest of the line');
}

// The first word after /create names the kind, read folded; the rest is the
// kind's own line. A word that names no kind leaves the kind empty.
function test_workspace_command_parse_create()
{
	pg_assert_same(array('kind' => 'product', 'rest' => 'Mavi kupa 149,90'), ws_command_parse_create('ürün Mavi kupa 149,90'), 'ürün');
	pg_assert_same(array('kind' => 'product', 'rest' => 'x'), ws_command_parse_create('URUN x'), 'URUN');
	pg_assert_same(array('kind' => 'product', 'rest' => 'x'), ws_command_parse_create('Product   x'), 'Product');
	pg_assert_same(array('kind' => 'product_group', 'rest' => 'Kupalar ve bardaklar'), ws_command_parse_create('grup Kupalar ve bardaklar'), 'grup');
	pg_assert_same(array('kind' => 'product_group', 'rest' => ''), ws_command_parse_create('group'), 'a kind and nothing after it');
	pg_assert_same(array('kind' => '', 'rest' => 'Mavi kupa'), ws_command_parse_create('Mavi kupa'), 'no kind');
	pg_assert_same(array('kind' => '', 'rest' => ''), ws_command_parse_create(''), 'nothing');
}

// A /create group card makes a group named by the rest of the line, written
// as its short description too; /create product reads as /product does.
function test_workspace_command_group_fields()
{
	pg_assert_same(array('name' => 'Kupalar ve bardaklar', 'short_description' => 'Kupalar ve bardaklar'), ws_command_group_fields(" Kupalar  ve\tbardaklar "), 'the rest of the line');
	pg_assert_same(array('name' => 'Kupa 149,90', 'short_description' => 'Kupa 149,90'), ws_command_group_fields('Kupa 149,90'), 'a group takes no price');
	pg_assert_same(array('name' => '', 'short_description' => ''), ws_command_group_fields(''), 'nothing');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');

	pg_assert_contains("return ws_command_product(\$viewer, \$channel, \$parsed['rest'], \$options);", $source, '/create product is /product');
	pg_assert_contains("return ws_command_create_card(\$viewer, \$channel, 'product_group', \$fields, \$options);", $source, 'a group goes through the same card');
	pg_assert_contains("return ws_command_create_card(\$viewer, \$channel, 'product', ws_command_product_fields(\$parsed), \$options);", $source, 'and so does a product');
}

// Several lines under /create product (or /product) are one bulk card of
// drafts, laid out before the database is asked anything: a row with no
// name, or more rows than a card takes, is refused there, the latter with
// the import named. One line stays the single preview.
function test_workspace_command_product_several_lines()
{
	$channel = array('id' => 1);

	$answer = ws_command_create(null, $channel, "ürün Mavi kupa 129,90\n;5", array());
	pg_assert_same(array('type' => 'error', 'error' => 'Row 2 has no name.'), $answer, '/create product with several lines is a list');

	$answer = ws_command_product(null, $channel, "Mavi kupa 129,90\n;5", array());
	pg_assert_same(array('type' => 'error', 'error' => 'Row 2 has no name.'), $answer, '/product too');

	$lines = array();

	for ($i = 1; $i <= WS_BULK_CREATE_MAX + 1; $i++) {
		$lines[] = 'Ürün ' . $i;
	}

	$answer = ws_command_create(null, $channel, "ürün\n" . implode("\n", $lines), array());
	pg_assert_same('error', $answer['type'], 'over one card is refused');
	pg_assert_contains('import_products.php', $answer['error'], 'with the import named');

	pg_assert_false(ws_bulk_text_is_list("Mavi kupa 129,90\n"), 'one line is not a list');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
	$product = substr($source, strpos($source, 'function ws_command_product('));
	pg_assert_true(strpos($product, 'ws_bulk_text_is_list($text)') < strpos($product, 'ws_command_create_card('), 'a list is told apart before the single card');
	pg_assert_contains("\$options + array('typed' => (string) \$body)", $source, 'the typed text goes along as the card\'s message');
	pg_assert_contains('ws_bulk_create_card($viewer, (int) $channel[\'id\'], $laid, (string) ($options[\'typed\'] ?? $text))', $source, 'the card hangs under the command\'s own message');
}

// /create product (or /product) with nothing after it answers a file:
// replying to a CSV or spreadsheet, or sent with one. The answered message
// must be of this channel; the rights are the card's own (read the file,
// post here, make products). Text after the command stays a product.
function test_workspace_command_product_from_a_file()
{
	pg_assert_true(ws_command_is_product_file('/oluştur ürün'), '/oluştur ürün');
	pg_assert_true(ws_command_is_product_file(' /create Product '), '/create product');
	pg_assert_true(ws_command_is_product_file('/ürün') && ws_command_is_product_file('/product'), 'the alias');
	pg_assert_false(ws_command_is_product_file('/oluştur ürün Mavi kupa'), 'a product named after it');
	pg_assert_false(ws_command_is_product_file('/oluştur grup') || ws_command_is_product_file('/oluştur'), 'another kind, or none');
	pg_assert_false(ws_command_is_product_file('ürün') || ws_command_is_product_file('//ürün') || ws_command_is_product_file(''), 'no command');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
	$product = substr($source, strpos($source, 'function ws_command_product('));
	$product = substr($product, 0, strpos($product, "\n}\n"));
	pg_assert_true(strpos($product, "(trim((string) \$text) === '') && ((int) (\$options['parent_id'] ?? 0) > 0)") < strpos($product, 'ws_command_parse_product('), 'an empty answer reads the file before a name is asked for');

	$file = substr($source, strpos($source, 'function ws_command_product_file('));
	pg_assert_contains("((int) \$message['channel_id'] !== (int) \$channel['id'])", $file, 'a file of this channel only');
	pg_assert_contains('ws_bulk_create_propose($viewer, (int) $channel[\'id\']', $file, 'through the file\'s card');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$send = substr($actions, strpos($actions, "case 'ws_send':"), 2000);
	pg_assert_contains("'parent_id' => (int) (\$request['parent_id'] ?? 0),", substr($send, strpos($send, 'ws_command_run('), 600), 'the reply reaches the command');

	$attach = substr($actions, strpos($actions, "case 'ws_attach':"), 3500);
	pg_assert_true(strpos($attach, '!ws_bulk_file_is_table(') < strpos($attach, 'ws_message_send_file('), 'a file that is no table is refused before it is posted');
	pg_assert_true(strpos($attach, "!ws_change_allowed(\$viewer, 'product', 0)") < strpos($attach, 'ws_message_send_file('), 'and so is a person without the product right');
	pg_assert_contains("ws_command_product_file(\$viewer, \$channel, (int) \$result['message_id'])", $attach, 'the posted file is the source');
}

// /create group makes one group: several lines are refused with an error,
// not read as several groups nor as one name.
function test_workspace_command_group_one_line()
{
	$answer = ws_command_create(null, array('id' => 1), "grup Kupalar\nBardaklar", array());
	pg_assert_same(array('type' => 'error', 'error' => '/create group makes one group: write its name on one line.'), $answer, 'several lines');

	pg_assert_false(ws_bulk_text_is_list("Kupalar\n\n"), 'one line and empty ones is a single name');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
	$group = substr($source, strpos($source, 'function ws_command_group('));
	pg_assert_true(strpos($group, 'ws_bulk_text_is_list($text)') < strpos($group, 'ws_command_group_fields($text)'), 'told before the lines are joined into a name');
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

// "next week" in two words, and its Turkish twins, is seven days on, the
// same as "haftaya" (2026-10-21 is a Wednesday).
function test_workspace_command_parse_task_reads_next_week()
{
	foreach (array('next week', 'Next Week', 'gelecek hafta', 'önümüzdeki hafta', 'onumuzdeki hafta', 'haftaya') as $words) {
		$task = ws_command_parse_task('Rapor ' . $words, '2026-10-21');

		pg_assert_same('2026-10-28', $task['due_date'], $words);
		pg_assert_same('Rapor', $task['title'], $words . ' leaves the title');
	}

	$task = ws_command_parse_task('Next steps', '2026-10-21');

	pg_assert_same('Next steps', $task['title'], '"next" alone is a word of the title');
	pg_assert_same(null, $task['due_date'], '"next" alone is no day');
}

// A day word in the dative is the same day: "cumaya", "yarına", a date with
// its suffix after an apostrophe.
function test_workspace_command_date_words_in_the_dative()
{
	$cases = array(
		'pazartesiye' => '2026-10-26', 'salıya' => '2026-10-27', 'saliya' => '2026-10-27',
		'çarşambaya' => '2026-10-21', 'perşembeye' => '2026-10-22', 'persembeye' => '2026-10-22',
		'cumaya' => '2026-10-23', 'cumartesiye' => '2026-10-24', 'pazara' => '2026-10-25',
		'yarına' => '2026-10-22', 'yarina' => '2026-10-22', 'bugüne' => '2026-10-21',
		"25.10'a" => '2026-10-25', "3.11'e" => '2026-11-03', "3.11’e" => '2026-11-03',
		"cuma'ya" => '2026-10-23',
	);

	foreach ($cases as $word => $date) {
		pg_assert_same($date, ws_parse_date_word($word, '2026-10-21'), $word);
	}

	pg_assert_same('', ws_parse_date_word('pazarlık', '2026-10-21'), 'a word that only starts like a day');
	pg_assert_same('', ws_parse_date_word("ali'ye", '2026-10-21'), 'a name with a suffix');
}

// A time written as hour:minute is the due time; alone it is today's,
// "saat" or "at" before it is not a word of the title.
function test_workspace_command_parse_task_reads_a_time()
{
	$task = ws_command_parse_task('Toplantı cuma 14:00', '2026-10-21');

	pg_assert_same('Toplantı', $task['title'], 'title');
	pg_assert_same('2026-10-23', $task['due_date'], 'the day');
	pg_assert_same('14:00', $task['due_time'], 'the time');

	$task = ws_command_parse_task('Ara saat 9:30', '2026-10-21');

	pg_assert_same('Ara', $task['title'], '"saat" before the time');
	pg_assert_same('2026-10-21', $task['due_date'], 'a time alone is today');
	pg_assert_same('09:30', $task['due_time'], 'one-digit hour');

	$task = ws_command_parse_task("Call at 16:45 tomorrow", '2026-10-21');

	pg_assert_same('Call', $task['title'], '"at" before the time');
	pg_assert_same('2026-10-22', $task['due_date'], 'tomorrow');
	pg_assert_same('16:45', $task['due_time'], 'the time before the day');

	$task = ws_command_parse_task("Sunum 14:00'te", '2026-10-21');

	pg_assert_same('14:00', $task['due_time'], 'a suffix after the time');

	$task = ws_command_parse_task('Skor 25:61', '2026-10-21');

	pg_assert_same('Skor 25:61', $task['title'], 'no such time');
	pg_assert_same(null, $task['due_time'], 'no time');
	pg_assert_same(null, $task['due_date'], 'no day');

	$task = ws_command_parse_task('Rapor', '2026-10-21');

	pg_assert_same(null, $task['due_time'], 'nothing written');
}

// "1.5 saat" is how long, not the first of May: a number before an hour or
// minute word is the estimate.
function test_workspace_command_parse_task_number_before_a_unit_is_no_date()
{
	$task = ws_command_parse_task('Toplantı 1.5 saat', '2026-10-21');

	pg_assert_same(null, $task['due_date'], '1.5 saat is no date');
	pg_assert_same(90, $task['estimate_minutes'], '1.5 saat');
	pg_assert_same('Toplantı', $task['title'], 'title');

	$task = ws_command_parse_task('Kontrol 45 dk', '2026-10-21');

	pg_assert_same(45, $task['estimate_minutes'], '45 dk');

	$task = ws_command_parse_task('Review 2 hours friday', '2026-10-21');

	pg_assert_same(120, $task['estimate_minutes'], '2 hours');
	pg_assert_same('2026-10-23', $task['due_date'], 'the day beside it');
	pg_assert_same('Review', $task['title'], 'title');

	$task = ws_command_parse_task('Teslim 1.5', '2026-10-21');

	pg_assert_same('2026-05-01', ws_parse_date_word('1.5', '2026-04-21'), 'a date without a unit is still a date');
	pg_assert_same('Teslim', $task['title'], 'the date leaves the title');
}

// A typed @word that is no tag is kept in the title and reported, so the
// live line can warn that nobody was picked.
function test_workspace_command_parse_task_reports_typed_people()
{
	$task = ws_command_parse_task('Logo @ayse <@user:5> @Mehmet. cuma', '2026-10-21');

	pg_assert_same(array('ayse', 'Mehmet'), $task['typed_people'], 'the typed names');
	pg_assert_same(array(5), $task['assignees'], 'the tag still assigns');
	pg_assert_same('Logo @ayse @Mehmet.', $task['title'], 'kept in the title');

	$task = ws_command_parse_task('Mail info@example.com', '2026-10-21');

	pg_assert_same(array(), $task['typed_people'], 'an address is no person');
}

// A typed @name assigns when it is exactly one team member's username, in
// any case and with or without the Turkish letters; it then leaves the
// title. A name nobody has, or several people share, assigns nobody and
// stays in the title for the warning.
function test_workspace_command_typed_people_assign_on_one_exact_username()
{
	$team = array(
		array('id' => 7, 'username' => 'arzu'),
		array('id' => 8, 'username' => 'ahmet'),
		array('id' => 9, 'username' => 'Ayşe'),
		array('id' => 10, 'username' => 'deniz'),
		array('id' => 11, 'username' => 'DENIZ'),
	);

	$task = ws_command_typed_assignees(ws_command_parse_task('Tedarikçiyi ara @arzu', '2026-10-21'), $team);

	pg_assert_same(array(7), $task['assignees'], 'one exact username');
	pg_assert_same('Tedarikçiyi ara', $task['title'], 'the name leaves the title');
	pg_assert_same(array(), $task['typed_unknown'], 'nothing unknown');
	pg_assert_same(array(), $task['typed_ambiguous'], 'nothing ambiguous');

	$task = ws_command_typed_assignees(ws_command_parse_task('Logo <@user:5> @AYSE, cuma', '2026-10-21'), $team);

	pg_assert_same(array(5, 9), $task['assignees'], 'beside a picked person, letters and case folded');
	pg_assert_same('Logo', $task['title'], 'punctuation after the name goes with it');

	$task = ws_command_typed_assignees(ws_command_parse_task('Ara @a @deniz @zeynep', '2026-10-21'), $team);

	pg_assert_same(array(), $task['assignees'], 'nobody from a prefix, a shared name or an unknown one');
	pg_assert_same(array('a', 'zeynep'), $task['typed_unknown'], 'no such username, a prefix included');
	pg_assert_same(array('deniz'), $task['typed_ambiguous'], 'two people answer to it');
	pg_assert_same('Ara @a @deniz @zeynep', $task['title'], 'unassigned names stay in the title');

	$task = ws_command_typed_assignees(ws_command_parse_task('Ara @arzu @arzu', '2026-10-21'), $team);

	pg_assert_same(array(7), $task['assignees'], 'written twice, assigned once');
	pg_assert_same('Ara', $task['title'], 'both leave the title');
}

// /status (/durum) answers to its names, reads the task and the status word
// after it in either language, and is the only command besides /task with
// a live line.
function test_workspace_command_status_reads_task_and_word()
{
	pg_assert_same(array('command' => 'status', 'rest' => 'G-42 bekliyor'), ws_command_split('/durum G-42 bekliyor'), '/durum');
	pg_assert_same(array('command' => 'status', 'rest' => 'T-42 done'), ws_command_split('/STATUS T-42 done'), '/STATUS in capitals');

	pg_assert_same(array('task_id' => 42, 'word' => 'Sürüyor', 'status' => 'doing'), ws_command_parse_status('G-42 Sürüyor'), 'a Turkish word in capitals');
	pg_assert_same(array('task_id' => 42, 'word' => 'suruyor', 'status' => 'doing'), ws_command_parse_status('<#task:42> suruyor'), 'a task tag, no Turkish letters');
	pg_assert_same(array('task_id' => 7, 'word' => 'cancelled', 'status' => 'cancelled'), ws_command_parse_status('#7 cancelled now'), 'only the first word');
	pg_assert_same(array('task_id' => 42, 'word' => '', 'status' => ''), ws_command_parse_status('T-42'), 'no word');
	pg_assert_same(array('task_id' => 42, 'word' => 'later', 'status' => ''), ws_command_parse_status('T-42 later'), 'a word that is no status');
	pg_assert_same('', ws_parse_status_word(''), 'nothing');

	$live = array();

	foreach (ws_commands(null) as $entry) {
		if ($entry['live']) {
			$live[] = $entry['key'];
		}
	}

	pg_assert_same(array('task', 'status', 'timer', 'schedule', 'away', 'meeting'), $live, 'the commands with a live line');
}

// /schedule (/zamanla) answers to its names and reads when at the start of
// the line: a time from now, a day, a time of day or both; the rest is the
// message. 2026-10-21 is a Wednesday; the line is read at 10:15.
function test_workspace_command_schedule_reads_when_and_message()
{
	$now = strtotime('2026-10-21 10:15:20');

	pg_assert_same(array('command' => 'schedule', 'rest' => 'yarın 09:00 Toplantı'), ws_command_split('/zamanla yarın 09:00 Toplantı'), '/zamanla');
	pg_assert_same(array('command' => 'schedule', 'rest' => 'friday x'), ws_command_split('/SCHEDULE friday x'), '/SCHEDULE in capitals');

	$at = function ($date, $time, $body) {
		return array('date' => $date, 'time' => $time, 'at' => strtotime($date . ' ' . $time . ':00'), 'body' => $body);
	};

	pg_assert_same($at('2026-10-22', '09:00', 'Toplantı on dakika sonra'), ws_command_parse_schedule('yarın 09:00 Toplantı on dakika sonra', $now), 'a day and a time');
	pg_assert_same($at('2026-10-23', '14:30', 'Ship it'), ws_command_parse_schedule('friday at 14:30 Ship it', $now), '"at" before the time');
	pg_assert_same($at('2026-10-23', '14:30', 'Gönder'), ws_command_parse_schedule("cuma saat 14:30'da Gönder", $now), '"saat" and a suffix');
	pg_assert_same($at('2026-10-23', '09:00', 'Rapor'), ws_command_parse_schedule('cuma Rapor', $now), 'a day alone is at 09:00');
	pg_assert_same($at('2026-10-21', '16:00', 'Çay'), ws_command_parse_schedule('16:00 Çay', $now), 'a time alone, still ahead today');
	pg_assert_same($at('2026-10-22', '08:00', 'Günaydın cuma'), ws_command_parse_schedule('08:00 Günaydın cuma', $now), 'a time gone by is tomorrow; a day after it is the message');
	pg_assert_same($at('2026-10-21', '10:45', 'Ara bitti'), ws_command_parse_schedule('+30dk Ara bitti', $now), '+30dk');
	pg_assert_same($at('2026-10-21', '12:15', 'Lunch'), ws_command_parse_schedule('+2h Lunch', $now), '+2h is two hours, not a week');
	pg_assert_same($at('2026-10-23', '09:00', '<@user:5> rapor'), ws_command_parse_schedule('+2g <@user:5> rapor', $now), '+2g is a day');
	pg_assert_same(array('date' => '', 'time' => '', 'at' => 0, 'body' => 'Merhaba dünya'), ws_command_parse_schedule('Merhaba dünya', $now), 'no time read');
	pg_assert_same($at('2026-10-22', '09:00', ''), ws_command_parse_schedule('yarın', $now), 'no message');
}

// A time from now is read only with a minute or hour unit after +.
function test_workspace_command_schedule_offset()
{
	pg_assert_same(30, ws_parse_schedule_offset('+30dk'), '+30dk');
	pg_assert_same(45, ws_parse_schedule_offset('+45m'), '+45m');
	pg_assert_same(120, ws_parse_schedule_offset('+2SA'), '+2SA');
	pg_assert_same(60, ws_parse_schedule_offset('+1hour'), '+1hour');
	pg_assert_same(0, ws_parse_schedule_offset('+2g'), 'a day is no offset');
	pg_assert_same(0, ws_parse_schedule_offset('30dk'), 'no plus');
	pg_assert_same(0, ws_parse_schedule_offset('+0dk'), 'nothing');
}

// The message /schedule posts is the text as written after its time: line
// breaks, a list, a quote and a code block stay, as they do when the clock
// beside the send button schedules it. 2026-10-21 is a Wednesday.
function test_workspace_command_schedule_keeps_the_message_as_written()
{
	$now = strtotime('2026-10-21 10:15:20');
	$message = "Günaydın!\n\nGündem:\n- Satış\n- Stok\n> Önce rakamlar";

	pg_assert_same($message, ws_command_parse_schedule('yarın 09:00 ' . $message, $now)['body'], 'a day and a time');
	pg_assert_same($message, ws_command_parse_schedule("cuma saat 14:30\n" . $message, $now)['body'], '"saat", then the message on the next line');
	pg_assert_same($message, ws_command_parse_schedule('+30dk ' . $message, $now)['body'], 'a time from now');
	pg_assert_same("Kod:\n```\nfoo  bar\n```", ws_command_parse_schedule("16:00 Kod:\n```\nfoo  bar\n```", $now)['body'], 'a code block keeps its spaces');
}

// /timer (/süre) answers to its names and tells a stop word from a task.
function test_workspace_command_timer_names_and_stop_words()
{
	pg_assert_same(array('command' => 'timer', 'rest' => 'G-42'), ws_command_split('/süre G-42'), '/süre');
	pg_assert_same(array('command' => 'timer', 'rest' => 'dur'), ws_command_split('/SURE dur'), '/SURE without Turkish letters');
	pg_assert_same(array('command' => 'timer', 'rest' => 'stop'), ws_command_split('/timer stop'), '/timer');

	foreach (array('dur', 'Durdur', 'bitir', 'STOP', 'end.') as $word) {
		pg_assert_true(ws_command_timer_stop_word($word), $word . ' stops');
	}

	foreach (array('', 'G-42', '42', 'durum') as $word) {
		pg_assert_true(!ws_command_timer_stop_word($word), '"' . $word . '" does not stop');
	}
}

// /away (/izin) reads the first day, a last day when one follows, and the
// rest as the title. 2026-10-21 is a Wednesday.
function test_workspace_command_away_reads_its_days()
{
	pg_assert_same(array('command' => 'away', 'rest' => 'cuma'), ws_command_split('/izin cuma'), '/izin');

	$away = function ($start, $end, $title) {
		return array('kind' => 'leave', 'scope' => 'people', 'start' => $start, 'end' => $end, 'title' => $title);
	};

	pg_assert_same($away('2026-10-23', '2026-10-26', 'Aile ziyareti'), ws_command_away_data('cuma pazartesi Aile ziyareti', '2026-10-21'), 'two days and a note');
	pg_assert_same($away('2026-10-22', '2026-10-22', ''), ws_command_away_data('yarın', '2026-10-21'), 'one day');
	pg_assert_same($away('2026-11-02', '2026-11-06', ''), ws_command_away_data('2.11 6.11', '2026-10-21'), 'two dates');
	pg_assert_same($away('', '', 'Tatil'), ws_command_away_data('Tatil', '2026-10-21'), 'no day');
}

// /away reads its last day from its first, so two weekdays are a range that
// starts on the first whatever day the line is typed on. 2026-10-26 is a
// Monday, 2026-10-27 a Tuesday, 2026-10-31 a Saturday.
function test_workspace_command_away_reads_its_last_day_from_its_first()
{
	$away = function ($start, $end, $title) {
		return array('kind' => 'leave', 'scope' => 'people', 'start' => $start, 'end' => $end, 'title' => $title);
	};

	pg_assert_same($away('2026-10-30', '2026-11-02', 'Family visit'), ws_command_away_data('friday monday Family visit', '2026-10-26'), 'friday monday, typed on a Monday');
	pg_assert_same($away('2026-11-02', '2026-11-06', ''), ws_command_away_data('pazartesi cuma', '2026-10-27'), 'pazartesi cuma, typed on a Tuesday');
	pg_assert_same($away('2026-11-06', '2026-11-09', ''), ws_command_away_data('cuma pazartesi', '2026-10-31'), 'cuma pazartesi, typed on a Saturday');
	pg_assert_same($away('2026-12-28', '2027-01-04', ''), ws_command_away_data('28.12 4.1', '2026-10-27'), 'a date without a year before the first day is in the next year');
}

// /meeting (/toplantı) reads when it starts as /schedule does, then an end
// or a length (an hour by default), the people tagged and the title.
function test_workspace_command_meeting_reads_its_parts()
{
	$now = strtotime('2026-10-21 10:15:00');

	pg_assert_same(array('command' => 'meeting', 'rest' => 'yarın 14:00 x'), ws_command_split('/Toplantı yarın 14:00 x'), '/Toplantı');
	pg_assert_same(array('command' => 'meeting', 'rest' => 'x'), ws_command_split('/toplanti x'), '/toplanti');

	$meeting = function ($people, $title, $start, $start_time, $end, $end_time) {
		return array('kind' => 'meeting', 'scope' => 'people', 'people' => $people, 'title' => $title, 'start' => $start, 'end' => $end, 'start_time' => $start_time, 'end_time' => $end_time);
	};

	pg_assert_same($meeting(array(5, 7), 'Açılış toplantısı', '2026-10-22', '14:00', '2026-10-22', '15:30'), ws_command_meeting_data('yarın 14:00 ~1.5sa Açılış <@user:5> toplantısı <@user:7> <@user:5>', $now), 'a day, a time, a length, people');
	pg_assert_same($meeting(array(), 'Plan', '2026-10-23', '09:30', '2026-10-23', '11:00'), ws_command_meeting_data('cuma 09:30 11:00 Plan', $now), 'an end time');
	pg_assert_same($meeting(array(), 'Kahve', '2026-10-21', '16:00', '2026-10-21', '17:00'), ws_command_meeting_data('16:00 Kahve', $now), 'an hour by default');
	pg_assert_same($meeting(array(), 'Gece', '2026-10-21', '23:30', '2026-10-22', '00:30'), ws_command_meeting_data('bugün 23:30 Gece', $now), 'past midnight');
	pg_assert_same($meeting(array(), 'Plan', '', '', '', ''), ws_command_meeting_data('Plan', $now), 'no start');

	$viewer = array('id' => 3);
	$data = ws_command_meeting_people($viewer, ws_command_meeting_data('16:00 Kahve <@user:5>', $now));

	pg_assert_same(array(3, 5), $data['people'], 'the writer is on the meeting');
}


// GET /workspace/commands lists the registry: a read under workspace:read,
// no parameters, no MCP tool, answering a list of WorkspaceCommand.
function test_workspace_commands_api_route_is_a_read_and_no_tool()
{
	$route = null;

	foreach (ws_api_routes() as $item) {
		if ($item['id'] === 'workspace.commands.list') {
			$route = $item;
		}
	}

	pg_assert_true(is_array($route), 'workspace.commands.list is declared');
	pg_assert_same('GET', $route['method']);
	pg_assert_same('/workspace/commands', $route['path']);
	pg_assert_same('workspace:read', $route['scope']);
	pg_assert_same('ws_api_commands_list', $route['handler']);
	pg_assert_same(array('list' => 'WorkspaceCommand'), $route['returns']);
	pg_assert_same(array(), $route['params']);
	pg_assert_same(false, $route['mcp'] ?? null, 'carries \'mcp\' => false');
	pg_assert_false(pg_mcp_route_is_offered($route), 'no MCP tool');
	pg_assert_false(!empty($route['dry_run']), 'a read does not rehearse');

	$objects = ws_openapi_objects();
	pg_assert_same('ws_api_command_schema', $objects['WorkspaceCommand'] ?? null, 'WorkspaceCommand is registered');
	pg_assert_same('ws_api_command_argument_schema', $objects['WorkspaceCommandArgument'] ?? null, 'WorkspaceCommandArgument is registered');
}

// A registry entry is answered with the fields its schema declares, in that
// order and with those types, and its arguments the same way.
function test_workspace_commands_api_present_matches_its_schema()
{
	$entries = ws_commands(null);
	$task = null;

	foreach ($entries as $entry) {
		if ($entry['key'] === 'task') {
			$task = $entry;
		}
	}

	pg_assert_true(is_array($task), 'the registry holds /task');

	$present = ws_api_command_present($task);

	pg_assert_same(array_keys(ws_api_command_schema()), array_keys($present), 'command fields');
	pg_assert_same('task', $present['key']);
	pg_assert_same($task['names'], $present['names']);
	pg_assert_same($task['usage'], $present['usage']);
	pg_assert_same(true, $present['live']);
	pg_assert_same(count($task['args']), count($present['args']), 'every argument');

	$people = $present['args'][1];

	pg_assert_same(array_keys(ws_api_command_argument_schema()), array_keys($people), 'argument fields');
	pg_assert_same(array('name' => 'people', 'kind' => 'people', 'label' => $task['args'][1]['label'], 'optional' => true,
		'suggest' => 'people_departments', 'multiple' => true, 'anywhere' => true), $people, '/task people');

	// A suggestion source left empty in the registry is null in the answer.
	$help = null;
	$poll = null;

	foreach ($entries as $entry) {
		if ($entry['key'] === 'help') {
			$help = ws_api_command_present($entry);
		}
		if ($entry['key'] === 'poll') {
			$poll = ws_api_command_present($entry);
		}
	}

	pg_assert_same(array(), $help['args'], '/help takes none');
	pg_assert_same(false, $help['live'], '/help has no live line');
	pg_assert_same(null, $poll['args'][0]['suggest'], 'no suggestion source');
}

// The list is the viewer's registry in its order: a command the person may
// not use is never listed.
function test_workspace_commands_api_list_keeps_the_registry_order()
{
	$keys = array_map(function ($entry) {
		return $entry['key'];
	}, ws_api_commands_present(ws_commands(null)));

	pg_assert_same(array_map(function ($entry) {
		return $entry['key'];
	}, ws_commands(null)), $keys, 'registry order');
	pg_assert_same(array(), ws_api_commands_present(array()), 'nothing offered, nothing listed');
}
