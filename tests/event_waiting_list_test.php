<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the waiting list of a full event date and the places a cancelled
 * order gives back (2026.11.0, 11.0.247; includes/fn/stock_notify.php,
 * includes/fn/ecommerce.php): one request per address and date, a product's
 * notice and an event's never reading each other's requests, places back
 * only with tickets on, a request claimed before its mail, the wiring, and
 * the schema step run twice.
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

function event_waiting_list_test_event($overrides = array())
{
	return $overrides + array(
		'id'                        => 12,
		'published'                 => 1,
		'reservations'              => 1,
		'product_id'                => '40',
		'product_enabled'           => 1,
		'end_date_and_time'         => '2099-01-01 10:00:00',
		'inventory'                 => 0,
		'inventory_quantity'        => 0,
		'backorder'                 => 0,
		'limit_reservations'        => 1,
		'number_of_remaining_spots' => 0,
		'recurrence'                => 1,
		'separate_reservations'     => 1,
	);
}

// A full date takes requests; an open one, an ended one or one whose product
// is out of stock does not.
function test_event_waiting_list_state()
{
	$now = strtotime('2026-11-20 12:00:00');

	pg_assert_same('full', pg_stock_notify_event_state(event_waiting_list_test_event(), $now), 'no place left');
	pg_assert_same('open', pg_stock_notify_event_state(event_waiting_list_test_event(array('number_of_remaining_spots' => 2)), $now), 'places left');
	pg_assert_same('open', pg_stock_notify_event_state(event_waiting_list_test_event(array('limit_reservations' => 0)), $now), 'places not limited');
	pg_assert_same('none', pg_stock_notify_event_state(event_waiting_list_test_event(array('end_date_and_time' => '2026-11-19 10:00:00')), $now), 'ended');
	pg_assert_same('none', pg_stock_notify_event_state(event_waiting_list_test_event(array('inventory' => 1)), $now), 'the product out of stock is no place');
	pg_assert_same('none', pg_stock_notify_event_state(event_waiting_list_test_event(array('reservations' => 0)), $now), 'no reservations');
	pg_assert_same('none', pg_stock_notify_event_state(event_waiting_list_test_event(array('product_id' => '')), $now), 'no product');
	pg_assert_same('none', pg_stock_notify_event_state(null, $now), 'no event');
}

// The date places are counted on: the occurrence only with separate spots.
function test_event_waiting_list_date()
{
	pg_assert_same(4, pg_stock_notify_event_date(event_waiting_list_test_event(), 4), 'separate spots');
	pg_assert_same(0, pg_stock_notify_event_date(event_waiting_list_test_event(array('separate_reservations' => 0)), 4), 'shared spots');
	pg_assert_same(0, pg_stock_notify_event_date(event_waiting_list_test_event(array('recurrence' => 0)), 4), 'a single event');
	pg_assert_same(0, pg_stock_notify_event_date(event_waiting_list_test_event(), -3), 'never below 0');
}

// One request per address and date: a second one answers already, and the
// lookup is the date's waiting rows by the address or the account.
function test_event_waiting_list_one_request_per_address_and_date()
{
	pg_assert_same('added', pg_stock_notify_event_request_status(true, 'a@example.com', 'full', false, 0), 'the first');
	pg_assert_same('already', pg_stock_notify_event_request_status(true, 'a@example.com', 'full', true, 0), 'the second');
	pg_assert_same('limit', pg_stock_notify_event_request_status(true, 'a@example.com', 'full', false, PG_STOCK_NOTIFY_PENDING_LIMIT), 'the shared limit');
	pg_assert_same('open', pg_stock_notify_event_request_status(true, 'a@example.com', 'open', false, 0), 'places left: reserve instead');
	pg_assert_same('event', pg_stock_notify_event_request_status(true, 'a@example.com', 'none', false, 0), 'not reservable');
	pg_assert_same('email', pg_stock_notify_event_request_status(true, '', 'full', false, 0), 'no address');
	pg_assert_same('unavailable', pg_stock_notify_event_request_status(false, 'a@example.com', 'full', false, 0), 'tickets off');

	$source = pg_test_function_source('pg_stock_notify_event_request');
	pg_assert_contains("pg_stock_notify_waiting_where('event', \$event_id, \$date)", $source, 'the date\'s waiting rows');
	pg_assert_contains("OR (user_id = '", $source, 'or the account\'s');
	pg_assert_contains('VALUES (0, ', $source, 'kept with product_id 0');
}

// A product's notice never reads an event's requests, nor the reverse.
function test_event_waiting_list_product_and_event_apart()
{
	$product = pg_stock_notify_waiting_where('product', 40);
	$event = pg_stock_notify_waiting_where('event', 12, 4);

	pg_assert_same("(stock_notify.product_id = '40') AND (stock_notify.sent_at = 0)", $product, 'a product: its own id');
	pg_assert_same("(stock_notify.product_id = 0) AND (stock_notify.calendar_event_id = '12') AND (stock_notify.recurrence_number = '4') AND (stock_notify.sent_at = 0)", $event, 'an event: product_id 0, the event and its date');
	pg_assert_same('', pg_stock_notify_waiting_where('product', 0), 'product 0 would be the events\' rows');
	pg_assert_same('', pg_stock_notify_waiting_where('event', 0, 0), 'no event');
	pg_assert_same('', pg_stock_notify_waiting_where('other', 5), 'anything else');

	pg_assert_contains("pg_stock_notify_waiting_where('product', \$product_id)", pg_test_function_source('pg_stock_notify_send'), 'the product\'s sender');
	pg_assert_contains("pg_stock_notify_waiting_where('product', \$product_id)", pg_test_function_source('pg_stock_notify_heard'), 'the product\'s listener');
	pg_assert_contains("pg_stock_notify_waiting_where('event', \$event_id, \$date)", pg_test_function_source('pg_stock_notify_event_send'), 'the event\'s sender');
}

// Two frees at once mail a request once: each row is claimed by a
// conditional UPDATE before its mail, and only the claim's winner mails.
function test_event_waiting_list_claimed_before_the_mail()
{
	pg_assert_contains('AND (sent_at = 0)', pg_test_function_source('pg_stock_notify_claim'), 'claims only an unsent row');
	pg_assert_contains('mysqli_affected_rows', pg_test_function_source('pg_stock_notify_claim'), 'tells who won');

	$source = pg_test_function_source('pg_stock_notify_event_send');
	$claim = strpos($source, 'pg_stock_notify_claim(');
	$mail = strpos($source, 'email(');

	pg_assert_true(($claim !== false) && ($mail !== false) && ($claim < $mail), 'claims before mailing');
	pg_assert_contains('!== 1', $source, 'mails only the row it claimed');
	pg_assert_contains("!== 'open'", $source, 'reads the places again first');

	$freed = pg_test_function_source('pg_stock_notify_event_freed');
	pg_assert_contains('catch (Throwable', $freed, 'a failure never breaks the cancel');
}

// Places come back on a cancel only with tickets on, summed per date.
function test_event_waiting_list_places_back_only_with_tickets_on()
{
	$lines = array(
		array('calendar_event_id' => 12, 'recurrence_number' => 4, 'quantity' => 2, 'reservations' => 1, 'limit_reservations' => 1, 'recurrence' => 1, 'separate_reservations' => 1),
		array('calendar_event_id' => 12, 'recurrence_number' => 4, 'quantity' => 1, 'reservations' => 1, 'limit_reservations' => 1, 'recurrence' => 1, 'separate_reservations' => 1),
		array('calendar_event_id' => 13, 'recurrence_number' => 7, 'quantity' => 3, 'reservations' => 1, 'limit_reservations' => 1, 'recurrence' => 1, 'separate_reservations' => 0),
		array('calendar_event_id' => 14, 'recurrence_number' => 0, 'quantity' => 5, 'reservations' => 1, 'limit_reservations' => 0, 'recurrence' => 0, 'separate_reservations' => 0),
		array('calendar_event_id' => 0, 'recurrence_number' => 0, 'quantity' => 5, 'reservations' => null, 'limit_reservations' => null),
	);

	pg_assert_same(array(), pg_order_event_places($lines, false), 'tickets off: nothing returns');
	pg_assert_same(array(), pg_order_event_places($lines, true, false), 'calendars off: nothing returns');
	pg_assert_same(array(array(12, 4, 3), array(13, 0, 3)), pg_order_event_places($lines, true), 'limited places per date, shared spots on date 0');

	pg_assert_same("UPDATE remaining_reservation_spots SET number_of_remaining_spots = number_of_remaining_spots + 3 WHERE (calendar_event_id = '12') AND (recurrence_number = '4')",
		pg_order_event_places_sql('give', 12, 4, 3), 'a cancel gives back');
	pg_assert_contains('IF(number_of_remaining_spots > 3, number_of_remaining_spots - 3, 0)', pg_order_event_places_sql('take', 12, 4, 3), 'a restore takes, never below 0');
	pg_assert_same('', pg_order_event_places_sql('give', 12, 4, 0), 'no quantity');
	pg_assert_same('', pg_order_event_places_sql('drop', 12, 4, 3), 'anything else');

	pg_assert_contains('pg_event_tickets_on()', pg_test_function_source('pg_order_event_places_move'), 'the switch is asked');
}

// The cancel, the restore, the event screen and the reserve page are wired.
function test_event_waiting_list_wiring()
{
	pg_assert_contains('pg_order_event_places_cancelled($order_id)', pg_test_function_source('process_order_cancellation'), 'the cancel gives places back');
	pg_assert_contains('pg_order_event_places_restored($order_id)', pg_test_function_source('pg_order_restore_cancelled'), 'the restore takes them again');
	pg_assert_contains('pg_stock_notify_event_freed(', pg_test_function_source('pg_order_event_places_cancelled'), 'the freed dates are mailed');

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_calendar_event.php');
	pg_assert_contains('pg_stock_notify_event_freed(', $edit, 'raised spots mail those waiting');

	$reserve = (string) file_get_contents(PG_FUNCTIONS_DIR . '/reserve_calendar_event.php');
	$waiting = strpos($reserve, "get_field_value('waiting_list')");
	$access = strpos($reserve, 'check_view_access($folder_id)');
	$cart = strpos($reserve, 'add_order_item(');
	pg_assert_true(($waiting !== false) && ($access !== false) && ($access < $waiting) && ($waiting < $cart), 'the request after the page checks, before the cart');
	pg_assert_contains('pg_stock_notify_throttled()', $reserve, 'rate limited');

	pg_assert_contains('pg_stock_notify_event_line(', (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_calendar_event_view_screen_content.php'), 'the legacy event page');
	pg_assert_contains('pg_stock_notify_event_line(', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php'), 'the designed event page');
}

// The line posts the waiting list flag and an address, escaped.
function test_event_waiting_list_form()
{
	$html = pg_stock_notify_event_form_html('/r.php', '<input type="hidden" name="token" value="t">', '/event?x="1"', 3, 12, 4, 'a"b@example.com');

	pg_assert_contains('name="waiting_list" value="1"', $html, 'the flag');
	pg_assert_contains('name="email_address" value="a&quot;b@example.com"', $html, 'the address escaped');
	pg_assert_contains('value="/event?x=&quot;1&quot;"', $html, 'the page escaped');
	pg_assert_contains('name="recurrence_number" value="4"', $html, 'the date');
}

// The mail names the event and its date, escaped, and holds no place.
function test_event_waiting_list_mail()
{
	$mail = pg_stock_notify_event_mail("Con<cert>\n", '1 May', 'https://example.com/');

	pg_assert_contains('Con&lt;cert&gt; (1 May)', $mail['body'], 'escaped in the body');
	pg_assert_true(strpos($mail['subject'], "\n") === false, 'one line subject');
	pg_assert_contains('https://example.com/', $mail['body'], 'the site');
}

// Runs the schema step in a second PHP against stubs: twice where the table
// has neither column; the second run writes nothing.
function test_event_waiting_list_step_run_twice()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwaiting');

	if ($file === false) {
		return;
	}

	$script = <<<'PHP'
define('INSTALL_OR_UPDATE', true);

$GLOBALS['fake_schema'] = array('stock_notify' => array('id' => 'int'));
$GLOBALS['fake_index'] = array();

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
		return isset($GLOBALS['fake_schema'][$m[1]][$m[2]]) ? array('Field' => $m[2]) : array();
	}
	if (preg_match("/^SHOW INDEX FROM `([^`]+)` WHERE Key_name = '([^']+)'$/", $sql, $m)) {
		return isset($GLOBALS['fake_index'][$m[1]][$m[2]]) ? array('Key_name' => $m[2]) : array();
	}
	throw new Exception('unexpected db_item: ' . $sql);
}

function db($sql)
{
	$GLOBALS['fake_writes'][] = preg_replace('/\s+/', ' ', $sql);
	if (preg_match('/^ALTER TABLE `([^`]+)` ADD `([^`]+)` (\w+)/', $sql, $m)) {
		$GLOBALS['fake_schema'][$m[1]][$m[2]] = strtolower($m[3]);
		return true;
	}
	if (preg_match('/^ALTER TABLE `([^`]+)` ADD INDEX (\w+)/', $sql, $m)) {
		$GLOBALS['fake_index'][$m[1]][$m[2]] = true;
		return true;
	}
	throw new Exception('unexpected db: ' . $sql);
}

require $pg_dir . '/includes/migrations/runner.php';
require $pg_dir . '/includes/migrations/2026.11.0.php';

$out = array('runs' => array());

foreach (array(1, 2) as $run) {
	$GLOBALS['fake_writes'] = array();
	upgrade_2026_11_0_event_waiting_list();
	$out['runs'][] = $GLOBALS['fake_writes'];
}

$out['schema'] = $GLOBALS['fake_schema'];

echo json_encode($out);
PHP;

	file_put_contents($file, '<?php' . "\n" . '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n" . $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $lines), true);

	pg_assert_true(($code === 0) && is_array($result) && isset($result['runs']), 'the step ran: ' . implode("\n", $lines));

	if (!is_array($result) || !isset($result['runs'])) {
		return;
	}

	list($first, $second) = $result['runs'];

	pg_assert_same(3, count($first), 'two columns and the index on the first run');
	pg_assert_contains('ADD `calendar_event_id` INT UNSIGNED NOT NULL DEFAULT 0', $first[0], 'the event');
	pg_assert_contains('ADD `recurrence_number` INT UNSIGNED NOT NULL DEFAULT 0', $first[1], 'its date');
	pg_assert_contains('INDEX idx_event_sent (calendar_event_id, recurrence_number, sent_at)', $first[2], 'the index');
	pg_assert_same(array(), $second, 'nothing the second time');
}
