<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a staff move sent from a page that showed another status: the
 * comparison itself (pg_return_status_seen()), where pg_return_move() makes
 * it (under the lock pg_return_staff_move() holds, before the move), that
 * every form of the return screen posts the status it showed, and why the
 * refund needs no such field.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function returns_stale_move_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The source of one top-level function, up to the next one.
function returns_stale_move_test_body($source, $name)
{
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// True when every needle is found, each one after the one before it.
function returns_stale_move_test_in_order($haystack, $needles)
{
	$at = 0;

	foreach ($needles as $needle) {
		$found = strpos($haystack, $needle, $at);

		if ($found === false) {
			return false;
		}

		$at = $found + strlen($needle);
	}

	return true;
}

// The status a page showed against the one the return is in now. Anything
// that is not a status, on either side, never matches: a form drawn before
// it carried the field is refused like a stale one.
function test_returns_stale_move_status_seen()
{
	pg_assert_true(pg_return_status_seen('received', 'received'), 'the same status');
	pg_assert_true(pg_return_status_seen(' Received ', 'received'), 'written as a status is read');
	pg_assert_true(pg_return_status_seen('refunded', 'refunded'), 'a refund still in flight, as the page showed it');

	pg_assert_false(pg_return_status_seen('refunded', 'received'), 'the page showed the refund, the service refused it meanwhile');
	pg_assert_false(pg_return_status_seen('approved', 'shipped'), 'the customer said the parcel left meanwhile');
	pg_assert_false(pg_return_status_seen('requested', 'approved'), 'somebody else answered meanwhile');
	pg_assert_false(pg_return_status_seen('', 'received'), 'a form without the field');
	pg_assert_false(pg_return_status_seen(null, 'received'), 'no value');
	pg_assert_false(pg_return_status_seen(array('received'), 'received'), 'not text');
	pg_assert_false(pg_return_status_seen('cancelled', 'cancelled'), 'not a return status');
	pg_assert_false(pg_return_status_seen('received', ''), 'a row without a status');
	pg_assert_false(pg_return_status_seen('received', null), 'no row status');
}

// pg_return_move() compares the shown status with the row it reads, before
// it judges the move (so a stale page reads "changed", not "cannot become")
// and before its conditional UPDATE, which stays for the lock-free callers.
function test_returns_stale_move_move_compares_before_the_move()
{
	$move = returns_stale_move_test_body(returns_stale_move_test_source('includes/fn/returns.php'), 'pg_return_move');
	$check = "array_key_exists('status_was', \$options) && !pg_return_status_seen(\$options['status_was'], \$from)";
	$sentence = "lang('The return was changed in the meantime. Open it again.')";

	pg_assert_contains($check, $move, 'compared only when the caller says what its page showed');
	pg_assert_true(returns_stale_move_test_in_order($move, array('SELECT * FROM order_returns', '$from = (string) $return[\'status\'];', $check, $sentence, 'pg_return_can_move(', 'UPDATE order_returns')),
		'after the row is read, refused with the "changed" sentence before the move is judged and written');
	pg_assert_contains("AND (status = '\" . e(\$from) . \"')", $move, 'the conditional UPDATE is kept');
}

// pg_return_staff_move() hands its options to pg_return_move() under the
// order's return lock, so the comparison is made after any refund that held
// the lock has finished or been put back.
function test_returns_stale_move_staff_move_compares_under_the_lock()
{
	$staff = returns_stale_move_test_body(returns_stale_move_test_source('includes/fn/returns.php'), 'pg_return_staff_move');
	$call = 'pg_return_move($return_id, $to, array_merge($options, array(';
	$lock = strpos($staff, 'GET_LOCK(');
	$move = strpos($staff, $call);
	$merged = ($move !== false) ? substr($staff, $move, (int) strpos($staff, ');', $move) - $move) : '';

	pg_assert_true(($lock !== false) && ($move !== false) && ($lock < $move), 'the options reach the move under the lock');
	pg_assert_true(($merged !== '') && (strpos($merged, 'status_was') === false), 'nothing the staff move adds overrides the shown status');
	pg_assert_true(strpos($staff, "unset(\$options['status_was'])") === false, 'the shown status is not dropped on the way');
}

// The return screen posts the status each form showed, printed from the row
// rather than through the liveform, checks it before any action and passes
// it on to the staff move.
function test_returns_stale_move_screen_sends_the_shown_status()
{
	$screen = returns_stale_move_test_source('view_return.php');
	$opening = '<form method="post" action="view_return.php"';
	$forms = 0;
	$at = 0;

	while (($start = strpos($screen, $opening, $at)) !== false) {
		$end = strpos($screen, '</form>', $start);
		$form = ($end === false) ? substr($screen, $start) : substr($screen, $start, $end - $start);
		$forms++;

		pg_assert_contains("' . \$form_keys . '", $form, 'form ' . $forms . ' posts the shared fields');
		pg_assert_false(strpos($form, 'get_token_field()') !== false, 'form ' . $forms . ' has no token of its own beside them');
		$at = $start + strlen($opening);
	}

	pg_assert_true($forms > 0, 'the screen\'s forms were found');
	pg_assert_same(preg_match_all('/<form\b/i', $screen), $forms, 'every form on the screen was checked: one opened another way fails here');

	$keys = strpos($screen, '$form_keys = get_token_field()');
	$keys_source = ($keys === false) ? '' : substr($screen, $keys, (int) strpos($screen, ';', $keys) - $keys);

	pg_assert_contains('name="id" value="\' . $return_id . \'"', $keys_source, 'the return');
	pg_assert_contains('name="status_was" value="\' . h($status) . \'"', $keys_source, 'the status the page shows, escaped');
	pg_assert_false(strpos($screen, "'name' => 'status_was'") !== false, 'never through the liveform: a refused post would fill the stale status back');

	pg_assert_true(returns_stale_move_test_in_order($screen, array(
		"\$shown = (isset(\$_POST['status_was'])",
		"if (!pg_return_status_seen(\$shown, (string) \$return['status'])) {",
		"\$refuse('_error', lang('The return was changed in the meantime. Open it again.'));",
		"if (\$action === 'refund') {",
	)), 'checked before any action, the refund too, with the model\'s sentence');
	pg_assert_true(returns_stale_move_test_in_order($screen, array("\$options = array('status_was' => \$shown);", 'pg_return_staff_move(')),
		'compared again under the lock by the staff move');
}

// The refund takes no shown status: it re-reads the return under the same
// lock and goes on only from received, the one status a refund starts from.
function test_returns_stale_move_refund_requires_received_under_the_lock()
{
	$refund = returns_stale_move_test_body(returns_stale_move_test_source('includes/fn/returns.php'), 'pg_return_refund');

	pg_assert_true(returns_stale_move_test_in_order($refund, array('GET_LOCK(', 'pg_return_load(', "!== 'received'", 'pg_return_move(')),
		'locked, read again, received required, then moved');
	pg_assert_same(array('received'), array_keys(array_filter(pg_return_moves('staff'), function ($targets) {
		return in_array('refunded', $targets, true);
	})), 'refunded starts from received only');
}
