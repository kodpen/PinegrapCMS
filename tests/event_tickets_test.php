<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for event tickets (2026.11.0, 11.0.246; includes/fn/event_tickets.php):
 * which lines give tickets, one per unit and the cap, the code and its
 * hash, the writes of make, cancel and restore, nothing with the switch
 * off, the tickets link and mail, and the schema step run twice.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A recorder standing in for pg_event_tickets_query(): keeps each statement
// and its values, and answers that one row changed.
function event_tickets_test_recorder()
{
	$GLOBALS['event_tickets_test_queries'] = array();

	return function ($sql, array $params) {
		$GLOBALS['event_tickets_test_queries'][] = array('sql' => $sql, 'params' => $params);

		return 1;
	};
}

function event_tickets_test_line($overrides = array())
{
	return $overrides + array(
		'order_item_id'     => 501,
		'product_id'        => 77,
		'quantity'          => 3,
		'calendar_event_id' => 12,
		'reservations'      => 1,
		'recurrence_number' => 4,
		'gives_tickets'     => 0,
	);
}

function test_event_tickets_which_lines_give_tickets()
{
	pg_assert_true(pg_event_tickets_line_gives(event_tickets_test_line()), 'an event reservation line');
	pg_assert_false(pg_event_tickets_line_gives(event_tickets_test_line(), false), 'not with the calendars off');
	pg_assert_false(pg_event_tickets_line_gives(event_tickets_test_line(array('reservations' => 0))), 'an event that takes no reservations');
	pg_assert_false(pg_event_tickets_line_gives(event_tickets_test_line(array('calendar_event_id' => null, 'reservations' => null))), 'a plain product');
	pg_assert_true(pg_event_tickets_line_gives(event_tickets_test_line(array('calendar_event_id' => null, 'reservations' => null, 'gives_tickets' => 1))), 'a product marked Gives tickets');
	pg_assert_true(pg_event_tickets_line_gives(event_tickets_test_line(array('calendar_event_id' => null, 'reservations' => null, 'gives_tickets' => '1')), false), 'a marked product with the calendars off');
}

function test_event_tickets_one_per_unit_and_the_cap()
{
	pg_assert_same(3, pg_event_tickets_line_count(event_tickets_test_line(), true), 'one per unit');
	pg_assert_same(100, pg_event_tickets_line_count(event_tickets_test_line(array('quantity' => 250)), true), '100 a line at most');
	pg_assert_same(0, pg_event_tickets_line_count(event_tickets_test_line(array('quantity' => -2)), true), 'none for a negative quantity');

	$record = event_tickets_test_recorder();
	$made = pg_event_tickets_complete_line(event_tickets_test_line(), 9001, true, $record, 'site-key');
	$queries = $GLOBALS['event_tickets_test_queries'];

	pg_assert_same(3, $made, 'three tickets made');
	pg_assert_same(3, count($queries), 'one INSERT per unit');
	pg_assert_contains('INSERT IGNORE INTO event_tickets', $queries[0]['sql'], 'a unit keeps the row it has');
	pg_assert_same(array(1, 2, 3), array_map(function ($q) { return $q['params'][2]; }, $queries), 'units 1 to 3');
	pg_assert_same(array(9001, 501, 1, 77, 12, 4), array_slice($queries[0]['params'], 0, 6), 'order, line, unit, product, event and date');

	$record = event_tickets_test_recorder();
	pg_event_tickets_complete_line(event_tickets_test_line(array('quantity' => 140)), 9001, true, $record, 'site-key');
	pg_assert_same(100, count($GLOBALS['event_tickets_test_queries']), 'the cap holds when a line is larger');

	$record = event_tickets_test_recorder();
	pg_event_tickets_complete_line(event_tickets_test_line(array('calendar_event_id' => null, 'reservations' => null, 'gives_tickets' => 1, 'quantity' => 2)), 9001, true, $record, 'site-key');
	$queries = $GLOBALS['event_tickets_test_queries'];
	pg_assert_same(2, count($queries), 'a marked product gives one per unit');
	pg_assert_same(array(0, 0), array_slice($queries[0]['params'], 4, 2), 'a product ticket has no event or date');
}

function test_event_tickets_code_is_not_the_order_number_and_stored_only_hashed()
{
	$code = pg_event_ticket_code(501, 1, 'aabbccdd', 'site-key');

	pg_assert_true((bool) preg_match('/^[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$/', $code), 'three groups of four: ' . $code);
	pg_assert_same($code, pg_event_ticket_code(501, 1, 'aabbccdd', 'site-key'), 'drawn again from the row');
	pg_assert_true($code !== pg_event_ticket_code(501, 2, 'aabbccdd', 'site-key'), 'each unit has its own');
	pg_assert_true($code !== pg_event_ticket_code(501, 1, 'aabbccde', 'site-key'), 'a new salt is a new code');
	pg_assert_true($code !== pg_event_ticket_code(501, 1, 'aabbccdd', 'other-key'), 'another key, another code');
	pg_assert_same('', pg_event_ticket_code(501, 1, 'aabbccdd', ''), 'none without a key');

	foreach (array('1001', '9001', 'PG-1001') as $order_number) {
		pg_assert_true(strpos(str_replace('-', '', $code), str_replace('-', '', $order_number)) === false, 'the code does not carry the order number ' . $order_number);
	}

	pg_assert_same(hash('sha256', str_replace('-', '', $code)), pg_event_ticket_code_hash($code), 'the hash of the code');
	pg_assert_same(pg_event_ticket_code_hash($code), pg_event_ticket_code_hash(strtolower(str_replace('-', ' ', $code))), 'typed in lower case with spaces');
	pg_assert_same(pg_event_ticket_code_hash('0000-1111-AAAA'), pg_event_ticket_code_hash('oooo-iiii-aaaa'), 'O read as 0 and I as 1');
	pg_assert_same('', pg_event_ticket_code_hash('1001'), 'an order number is not a code');

	$record = event_tickets_test_recorder();
	pg_event_tickets_complete_line(event_tickets_test_line(array('quantity' => 1)), 1001, true, $record, 'site-key');
	$query = $GLOBALS['event_tickets_test_queries'][0];
	$salt = $query['params'][6];
	$stored_code = pg_event_ticket_code(501, 1, $salt, 'site-key');

	pg_assert_same(32, strlen($salt), 'a 32-character salt');
	pg_assert_same(pg_event_ticket_code_hash($stored_code), $query['params'][7], 'the stored hash is the hash of the drawn code');
	pg_assert_false(strpos(implode('|', $query['params']), $stored_code) !== false, 'the code itself is never written');
	pg_assert_false(strpos(implode('|', $query['params']), str_replace('-', '', $stored_code)) !== false, 'nor without its dashes');
}

function test_event_tickets_cancel_and_restore()
{
	$record = event_tickets_test_recorder();

	pg_assert_same(1, pg_event_tickets_order_cancelled(9001, $record), 'the cancel reports the rows it changed');
	$cancel = $GLOBALS['event_tickets_test_queries'][0];
	pg_assert_same(1, count($GLOBALS['event_tickets_test_queries']), 'one statement');
	pg_assert_contains("SET state = 'void'", $cancel['sql'], 'tickets become void');
	pg_assert_contains("(state <> 'void')", $cancel['sql'], 'only those not void already');
	pg_assert_same(array(9001), $cancel['params'], 'for the order');

	$record = event_tickets_test_recorder();
	pg_event_tickets_order_restored(9001, $record);
	$restore = $GLOBALS['event_tickets_test_queries'][0];
	pg_assert_contains("IF(used_at > 0, 'used', 'valid')", $restore['sql'], 'used ones stay used');
	pg_assert_contains("(state = 'void')", $restore['sql'], 'only void ones');

	pg_assert_same('void', pg_event_ticket_state_after('cancel', 'valid', 0), 'valid to void');
	pg_assert_same('void', pg_event_ticket_state_after('cancel', 'used', 1700000000), 'used to void');
	pg_assert_same('valid', pg_event_ticket_state_after('restore', 'void', 0), 'void to valid');
	pg_assert_same('used', pg_event_ticket_state_after('restore', 'void', 1700000000), 'void to used when it was checked in');
	pg_assert_same('valid', pg_event_ticket_state_after('restore', 'valid', 0), 'a restore leaves a valid ticket');

	$record = event_tickets_test_recorder();
	pg_assert_same(0, pg_event_tickets_order_cancelled(0, $record), 'no order, nothing');
	pg_assert_same(array(), $GLOBALS['event_tickets_test_queries'], 'and no statement');
}

function test_event_tickets_own_key_sealed_with_the_site_key()
{
	$key = str_repeat('ab', 32);
	$sealed = pg_event_tickets_key_seal($key, 'site-key');

	pg_assert_true(strpos($sealed, ':') !== false, 'stored as cipher:iv');
	pg_assert_false(strpos($sealed, $key) !== false, 'the key is not stored in the clear');
	pg_assert_same($key, pg_event_tickets_key_open($sealed, 'site-key'), 'opens with the site key');
	pg_assert_same('', pg_event_tickets_key_open($sealed, 'other-key'), 'not with another site key');
	pg_assert_same('', pg_event_tickets_key_open('', 'site-key'), 'none when missing');
	pg_assert_same('', pg_event_tickets_key_open(pg_event_tickets_key_seal('short', 'site-key'), 'site-key'), 'a value of another shape is no key');

	$reseal = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/reseal.php');
	pg_assert_contains("\$config_text('event_tickets', 'event_tickets_key', 'iv')", $reseal, 'a key reset seals it again');

	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/event_tickets.php');
	pg_assert_same(1, substr_count($module, 'pg_event_tickets_key_make();'), 'made in one place only');
	pg_assert_contains("pg_config_text_claim(PG_EVENT_TICKETS_KEY_SETTING, ''", $module, 'only a missing row takes it');
}

function test_event_tickets_deleted_with_the_order()
{
	$record = event_tickets_test_recorder();

	pg_assert_same(1, pg_event_tickets_delete_for_order(9001, $record), 'reports the rows removed');
	pg_assert_same(array(array('sql' => 'DELETE FROM event_tickets WHERE order_id = ?', 'params' => array(9001))), $GLOBALS['event_tickets_test_queries'], 'one DELETE for the order');

	$record = event_tickets_test_recorder();
	pg_assert_same(0, pg_event_tickets_delete_for_order(0, $record), 'no order, nothing');
	pg_assert_same(array(), $GLOBALS['event_tickets_test_queries'], 'and no statement');

	$delete_order = (string) file_get_contents(PG_FUNCTIONS_DIR . '/delete_order.php');
	pg_assert_contains("pg_event_tickets_delete_for_order(\$order['id']);", $delete_order, 'delete_order.php removes them');
}

function test_event_tickets_nothing_with_the_switch_off()
{
	$record = event_tickets_test_recorder();

	pg_assert_same(0, pg_event_tickets_complete_line(event_tickets_test_line(), 9001, false, $record, 'site-key'), 'no ticket for a reservation');
	pg_assert_same(0, pg_event_tickets_complete_line(event_tickets_test_line(array('gives_tickets' => 1)), 9001, false, $record, 'site-key'), 'no ticket for a marked product');
	pg_assert_same(array(), $GLOBALS['event_tickets_test_queries'], 'no statement at all');
	pg_assert_same(0, pg_event_tickets_line_count(event_tickets_test_line(), false), 'the count is 0');

	pg_assert_false(pg_event_tickets_order_mail(9001, '1001', 'buyer@example.com', 'Ayşe', 0), 'no mail without tickets');
}

function test_event_tickets_link_and_mail()
{
	$token = pg_event_tickets_link_token(9001, 'site-key');

	pg_assert_same(64, strlen($token), 'a 64-character token');
	pg_assert_true(pg_event_tickets_link_token_valid(9001, $token, 'site-key'), 'the order\'s token opens it');
	pg_assert_false(pg_event_tickets_link_token_valid(9002, $token, 'site-key'), 'not another order');
	pg_assert_false(pg_event_tickets_link_token_valid(9001, $token, 'other-key'), 'not under another key');
	pg_assert_false(pg_event_tickets_link_token_valid(9001, array(), 'site-key'), 'not a token that is not text');
	pg_assert_same('', pg_event_tickets_link_token(9001, ''), 'none without a key');

	$body = pg_event_tickets_mail_body(array('name' => 'Ayşe <b>', 'order_number' => '1001', 'count' => 3, 'url' => 'https://example.com/tickets.php?o=9001&t=' . $token));

	pg_assert_contains('Ayşe &lt;b&gt;', $body, 'the name escaped');
	pg_assert_contains('https://example.com/tickets.php?o=9001&amp;t=' . $token, $body, 'one link to the tickets page');
	pg_assert_false(stripos($body, '<img') !== false || stripos($body, '<svg') !== false, 'no QR picture in a mail');
}

function test_event_tickets_completion_and_cancel_call_it()
{
	$completion = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/order_completion.php');
	$ecommerce = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php');

	pg_assert_same(1, substr_count($completion, 'pg_event_tickets_complete_line($product, $order_id)'), 'one call per completed line');
	pg_assert_same(1, substr_count($completion, 'pg_event_tickets_order_mail('), 'one mail call');
	pg_assert_same(1, substr_count($ecommerce, 'pg_event_tickets_order_cancelled($order_id)'), 'the cancel voids');
	pg_assert_same(1, substr_count($ecommerce, 'pg_event_tickets_order_restored($order_id)'), 'the restore gives back');
	pg_assert_contains("pg_schema_has('products', 'gives_tickets')", $completion, 'the mark is read only once it is there');
}

// Runs the schema step in a second PHP against stubs (the real runner
// helpers, a fake db layer): twice on a site without the table, once more
// where it all exists. Null when PHP cannot be started again.
function event_tickets_test_run_step()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgtickets');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('INSTALL_OR_UPDATE', true);

$GLOBALS['fake_schema'] = array();
$GLOBALS['fake_writes'] = array();

function e($value) { return addslashes((string) $value); }

function lang($text)
{
	if (is_array($text)) {
		return str_replace('{var:1}', (string) $text['vars'], $text['string']);
	}
	return $text;
}

function db_value($sql)
{
	if (preg_match("/TABLE_NAME = '([^']+)'/", $sql, $m)) {
		return isset($GLOBALS['fake_schema'][$m[1]]) ? 1 : 0;
	}
	throw new Exception('unexpected db_value: ' . $sql);
}

function db_item($sql)
{
	if (preg_match("/^SHOW COLUMNS FROM `([^`]+)` WHERE Field = '([^']+)'$/", $sql, $m)) {
		if (isset($GLOBALS['fake_schema'][$m[1]][$m[2]])) {
			return array('Field' => $m[2], 'Type' => $GLOBALS['fake_schema'][$m[1]][$m[2]], 'Null' => 'NO', 'Default' => '0');
		}
		return array();
	}
	throw new Exception('unexpected db_item: ' . $sql);
}

function db($sql)
{
	$GLOBALS['fake_writes'][] = preg_replace('/\s+/', ' ', $sql);
	if (preg_match('/^CREATE TABLE (\w+) \(/', $sql, $m)) {
		$GLOBALS['fake_schema'][$m[1]] = array('id' => 'int');
		return true;
	}
	if (preg_match('/^ALTER TABLE `([^`]+)` ADD `([^`]+)` (\w+)/', $sql, $m)) {
		$GLOBALS['fake_schema'][$m[1]][$m[2]] = strtolower($m[3]);
		return true;
	}
	throw new Exception('unexpected db: ' . $sql);
}

require $pg_dir . '/includes/migrations/runner.php';
require $pg_dir . '/includes/migrations/2026.11.0.php';

$out = array('runs' => array());

$GLOBALS['fake_schema'] = array('products' => array('id' => 'int'));

foreach (array(1, 2) as $run) {
	$GLOBALS['fake_writes'] = array();
	upgrade_2026_11_0_event_tickets();
	$out['runs'][] = $GLOBALS['fake_writes'];
}

$out['schema'] = $GLOBALS['fake_schema'];

echo json_encode($out);
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $lines), true);

	if (($code !== 0) || !is_array($result) || !isset($result['runs'], $result['schema'])) {
		return array('error' => implode("\n", $lines));
	}

	return $result;
}

// The table and the product's mark are made once; the second run writes
// nothing.
function test_event_tickets_step_run_twice_changes_nothing_the_second_time()
{
	$result = event_tickets_test_run_step();

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the step ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	list($first, $second) = $result['runs'];

	pg_assert_same(2, count($first), 'the table and the column on the first run');
	pg_assert_contains('CREATE TABLE event_tickets (', $first[0], 'the table');
	pg_assert_contains('UNIQUE KEY idx_line_unit (order_item_id, unit)', $first[0], 'one row per line and unit');
	pg_assert_contains('UNIQUE KEY idx_code (code_hash)', $first[0], 'the code\'s hash looked up and unique');
	pg_assert_same('ALTER TABLE `products` ADD `gives_tickets` TINYINT UNSIGNED NOT NULL DEFAULT 0', $first[1], 'the product\'s mark');
	pg_assert_same(array(), $second, 'the second run writes nothing');
	pg_assert_true(isset($result['schema']['event_tickets'], $result['schema']['products']['gives_tickets']), 'both are there');

	$migration = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	pg_assert_same(1, substr_count($migration, "\tupgrade_2026_11_0_event_tickets();"), 'called once');
	pg_assert_true((bool) preg_match('~\tupgrade_2026_11_0_event_tickets\(\); +// 11\.0\.246\n~', $migration), 'under 11.0.246');

	$install = (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');
	pg_assert_contains("\t\t'event_tickets',\n", $install, 'listed in get_tables()');
}
