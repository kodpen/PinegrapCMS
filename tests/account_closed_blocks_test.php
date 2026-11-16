<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for My Account's closed blocks: the order history and the bookings
 * open into lists of the designer's own elements (pg_acct_part(), the
 * account.orders and the two booking lists), drawn with the words the
 * sections print; the sections are now built from the same rows
 * (pg_acct_order_history_html(), pg_booking_customer_view_rows() and
 * pg_booking_customer_view_html()) and print what development printed. The
 * canvas twins in assets/js/style_designer.src.js are cut out and run under
 * Node (skipped on a machine without Node).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// prepare_amount() prints the base currency's symbol: the value the other
// tests define, as the constant is set once for the whole run.
if (!defined('BASE_CURRENCY_SYMBOL')) {
	define('BASE_CURRENCY_SYMBOL', '$');
}

function account_blocks_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The body of the named function, up to the next function at the same indentation.
function account_blocks_test_function($source, $name)
{
	$from = strpos($source, '    function ' . $name . '(');
	pg_assert_true($from !== false, 'the designer has ' . $name . '()');
	$to = strpos($source, "\n    function ", $from + 10);
	pg_assert_true($to !== false, $name . '() has an end');

	return substr($source, $from, $to - $from);
}

// Runs a JavaScript expression over the cut twins; null without Node.
function account_blocks_test_run($expr)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}
	$source = account_blocks_test_source();
	$script = "function esc(s) { if (!s && s !== 0) return ''; return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;'); }\n"
		. "function _sdT(s) { return s; }\n"
		. "function createNode(type, props, children) { return { type: type, props: props || {}, children: children || [] }; }\n";
	foreach (array('_sdPartEl', '_sdPartWhen', '_sdPartSentence', '_sdAccountPart', '_sdAccountOrderHistoryHtml', '_sdAccountBookingsHtml') as $fn) {
		$script .= account_blocks_test_function($source, $fn) . "\n";
	}
	$script .= 'console.log(JSON.stringify(' . $expr . '));';
	$file = tempnam(sys_get_temp_dir(), 'pgacct');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// The order history as development printed it, from the orders the history
// reads, the view order page and the date format.
function account_blocks_test_history_as_development_printed($orders, $view, $date_format)
{
	$html = '<div class="list-group pg-sw-order-history">';
	foreach ($orders as $o) {
		$inner = '<span><span class="fw-semibold">#' . h((string)$o['order_number']) . '</span>'
			. '<span class="text-body-secondary small ms-2">' . h(_pg_sw_short_date((int)$o['order_date'], $date_format)) . '</span>'
			. ($o['status'] === 'cancelled' ? '<span class="badge text-bg-secondary ms-2">' . h(lang('Cancelled')) . '</span>' : '')
			. '</span><span>' . prepare_amount($o['total']) . '</span>';
		$cls = 'list-group-item d-flex justify-content-between align-items-center';
		$html .= ($view !== '')
			? '<a href="' . h($view . '?id=' . (int)$o['id']) . '" class="' . $cls . ' list-group-item-action">' . $inner . '</a>'
			: '<div class="' . $cls . '">' . $inner . '</div>';
	}
	return $html . '</div>';
}

// The bookings section as development printed it.
function account_blocks_test_bookings_as_development_printed($rows, $now)
{
	$split = pg_booking_customer_split($rows, $now);

	if (!$split['upcoming'] && !$split['past']) {
		return '';
	}

	$context = pg_booking_message_context(array('id' => 0, 'end_at' => 0, 'status' => 'cancelled'));
	$policy = pg_booking_policy();
	$badges = array('requested' => 'text-bg-warning', 'confirmed' => 'text-bg-success');
	$html = '';

	foreach (array('upcoming' => lang('Upcoming'), 'past' => lang('Past')) as $part => $heading) {
		if (!$split[$part]) {
			continue;
		}

		$html .= '<h6 class="mt-3 mb-2">' . h($heading) . '</h6><div class="list-group pg-sw-bookings">';

		foreach ($split[$part] as $row) {
			$times = pg_booking_message_times((int) $row['start_at'], (int) $row['end_at'], $context['zone'], $context['date_code'], $context['time_code']);
			$status = (string) $row['status'];
			$deposit = pg_booking_deposit_label(pg_booking_deposit_view($row));
			$html .= '<div class="list-group-item">'
				. '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">'
				. '<span class="fw-semibold">' . h((string) $row['service_name']) . '</span>'
				. '<span class="badge ' . (isset($badges[$status]) ? $badges[$status] : 'text-bg-secondary') . '">' . h(pg_booking_customer_status_label($status)) . '</span>'
				. '</div>'
				. '<div class="small text-body-secondary">' . h($times['date'] . ' ' . $times['time'])
				. (((string) $row['staff_name'] !== '') ? ' · ' . h((string) $row['staff_name']) : '')
				. (($deposit !== '') ? ' · ' . h($deposit) : '') . '</div>';

			$url = ($part === 'upcoming') ? pg_booking_manage_url((int) $row['id'], (int) $row['end_at']) : '';

			if (($url !== '') && ((string) $row['manage_token'] !== '')) {
				$rights = pg_booking_manage_rights($row, $policy, $now);
				$html .= '<div class="d-flex flex-wrap gap-2 mt-2">'
					. '<a href="' . h($url) . '" class="btn btn-sm btn-outline-primary" rel="nofollow">'
					. h(($rights['cancel'] || $rights['reschedule']) ? lang('Change or cancel') : lang('View booking')) . '</a>'
					. '<a href="' . h($url . '&ics=1') . '" class="btn btn-sm btn-outline-secondary" rel="nofollow">' . h(lang('Add to calendar')) . '</a>'
					. '</div>';
			}

			$html .= '</div>';
		}

		$html .= '</div>';
	}

	return $html;
}

// A member's orders: one complete, one cancelled with a name to escape.
function account_blocks_test_orders()
{
	return array(
		array('id' => 42, 'order_number' => '1042', 'order_date' => 1800000000, 'total' => 1183.9, 'status' => 'complete'),
		array('id' => 41, 'order_number' => '1041<b>', 'order_date' => 1799000000, 'total' => 150, 'status' => 'cancelled'),
	);
}

// A member's bookings at a fixed time: an upcoming confirmed one with staff,
// an upcoming requested one, a past done one and a cancelled one.
function account_blocks_test_bookings(&$now)
{
	$now = 1800000000;
	$b = function ($id, $service, $status, $start, $staff) {
		return array('id' => $id, 'service_name' => $service, 'status' => $status, 'start_at' => $start, 'end_at' => $start + 2700,
			'staff_name' => $staff, 'manage_token' => 'tok' . $id, 'deposit_amount' => 0, 'deposit_status' => '', 'order_id' => 0, 'paid_at' => 0);
	};
	return array($b(1, 'Cut & Style', 'confirmed', $now + 86400, 'Ana'), $b(2, 'Colour', 'requested', $now + 3600, ''),
		$b(3, 'Cut', 'done', $now - 86400, 'Ana'), $b(4, 'Massage', 'cancelled', $now + 7200, ''));
}

// The visible words of $html on one line, without white space between boxes.
function account_blocks_test_text($html)
{
	$html = preg_replace('#<(?:br|/?(?:div|li|ul|ol|p|h6|a))\b[^>]*>#i', ' ', $html);
	return preg_replace('/\s+/u', '', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// A part of the my account widget drawn with its lists filled from $rows.
function account_blocks_test_draw($section, $rows, $flags)
{
	static $sid = 70;
	$tree = array('type' => 'root', 'props' => array(), 'children' => pg_acct_part($section));
	_eo_apply_visibility_bindings($tree, $flags);
	$scope = pg_sw_finish_open(array('sid' => ++$sid, 'kind' => 'my_account', 'email' => false));
	try {
		$html = trim(_render_tree_node($tree, 0, 0));
		pg_sw_list_publish('account', $rows);
		$html = _pg_member_apply_tokens($html, array('account_id' => '7'));
	} finally {
		pg_sw_finish_close();
	}
	return pg_sw_finish_widget_html($html, $scope);
}

// The sections built from the rows print what development printed.
function test_account_blocks_sections_unchanged()
{
	$orders = account_blocks_test_orders();
	$fmt = pg_sw_default_date_format('date');
	foreach (array('/order', '') as $view) {
		$rows = pg_sw_list_account_rows(0, $orders, $view, $fmt);
		pg_assert_same(account_blocks_test_history_as_development_printed($orders, $view, $fmt),
			pg_acct_order_history_html($rows['account.orders'][0], $view !== '', array_column($orders, 'total')), 'order history, view page "' . $view . '"');
	}
	$now = 0;
	$bookings = account_blocks_test_bookings($now);
	$old = account_blocks_test_bookings_as_development_printed($bookings, $now);
	pg_assert_contains('Cut &amp; Style', $old, 'the fixture reaches the bookings');
	pg_assert_same($old, pg_booking_customer_section_html($bookings, $now), 'bookings, byte for byte');
	pg_assert_same('', pg_booking_customer_section_html(array(), $now), 'no booking, nothing');
}

// The opened parts, drawn with their lists, say what the sections say.
function test_account_blocks_parts_print_the_sections_words()
{
	$orders = account_blocks_test_orders();
	$rows = pg_sw_list_account_rows(7, $orders, '/order', pg_sw_default_date_format('date'));
	$old = pg_acct_order_history_html($rows['account.orders'][7], true, array_column($orders, 'total'));
	$new = account_blocks_test_draw('order_history', $rows, array());
	pg_assert_same(account_blocks_test_text($old), account_blocks_test_text($new), 'order history: the same words');
	pg_assert_contains('href="/order?id=42"', $new, 'a link to the order');
	pg_assert_contains('>1041&lt;b&gt;</span>', $new, 'the number escaped once');
	pg_assert_same(2, substr_count($new, 'list-group-item-action'), 'links while the site has a view order page');
	$plain = account_blocks_test_draw('order_history', pg_sw_list_account_rows(7, $orders, '', pg_sw_default_date_format('date')), array());
	pg_assert_same(0, substr_count($plain, 'list-group-item-action'), 'plain items without one');
	pg_assert_same(2, substr_count($plain, '<div class="list-group-item d-flex justify-content-between align-items-center"'), 'one plain item per order');

	// Bookings with their manage pages (the sandbox has no booking page).
	$view = array(
		'upcoming' => array(
			array('id' => 2, 'service' => 'Colour', 'status' => 'requested', 'status_label' => 'Requested', 'tone' => 'warning', 'details' => 'Mon 10:00 – 11:00',
				'manage_url' => '/b?id=2&t=x', 'manage_label' => 'View booking', 'ics_url' => '/b?id=2&t=x&ics=1'),
			array('id' => 1, 'service' => 'Cut & Style', 'status' => 'confirmed', 'status_label' => 'Confirmed', 'tone' => 'success', 'details' => 'Tue 9:00 · Ana',
				'manage_url' => '/b?id=1&t=y', 'manage_label' => 'Change or cancel', 'ics_url' => '/b?id=1&t=y&ics=1'),
		),
		'past' => array(
			array('id' => 3, 'service' => 'Cut', 'status' => 'done', 'status_label' => 'Done', 'tone' => 'secondary', 'details' => 'Sun 9:00 · Ana',
				'manage_url' => '', 'manage_label' => '', 'ics_url' => ''),
		),
	);
	$old = pg_booking_customer_view_html($view);
	$new = account_blocks_test_draw('bookings', pg_sw_list_account_booking_rows(7, $view), array('has_upcoming_bookings' => true, 'has_past_bookings' => true));
	pg_assert_same(account_blocks_test_text($old), account_blocks_test_text($new), 'bookings: the same words');
	preg_match_all('/class="([^"]*)"/', $old, $m);
	foreach (array_unique($m[1]) as $class) {
		pg_assert_contains('class="' . $class . '"', $new, 'class ' . $class);
	}
	pg_assert_contains('href="/b?id=1&amp;t=y&amp;ics=1"', $new, 'the calendar link escaped once');
	pg_assert_same(4, substr_count($new, 'rel="nofollow"'), 'the manage links keep rel nofollow');
	$upcoming = account_blocks_test_draw('bookings', pg_sw_list_account_booking_rows(7, array('upcoming' => $view['upcoming'], 'past' => array())),
		array('has_upcoming_bookings' => true, 'has_past_bookings' => false));
	pg_assert_same(false, strpos($upcoming, 'Past'), 'no past heading without past bookings');
	pg_assert_same(array(), pg_acct_part('subscriptions'), 'subscriptions stay a section');
	pg_assert_same(array(), pg_acct_part('store_credit'), 'store credit stays a section');
}

// The booking rows carry the view's words; each list under the member.
function test_account_blocks_booking_rows()
{
	$now = 0;
	$view = pg_booking_customer_view_rows(account_blocks_test_bookings($now), $now);
	pg_assert_same(array(2, 1), array_column($view['upcoming'], 'id'), 'soonest first');
	pg_assert_same(array(4, 3), array_column($view['past'], 'id'), 'cancelled ones are past, latest first');
	pg_assert_same(array('warning', 'success'), array_column($view['upcoming'], 'tone'), 'the badge tone by status');
	$rows = pg_sw_list_account_booking_rows(7, $view);
	pg_assert_same(array('account.upcoming_bookings', 'account.past_bookings'), array_keys($rows));
	pg_assert_same(array(7), array_keys($rows['account.upcoming_bookings']));
	pg_assert_same('Cut & Style', $rows['account.upcoming_bookings'][7][1]['__booking_service'], 'raw, escaped once by the pass');
	pg_assert_same(array('secondary', 'secondary'), array_column($rows['account.past_bookings'][7], '__booking_tone'));
}

// The editor builds the same parts and draws the sections as the site.
function test_account_blocks_twins()
{
	$orders = account_blocks_test_orders();
	$rows = pg_sw_list_account_rows(0, $orders, '/order', pg_sw_default_date_format('date'))['account.orders'][0];
	$totals = array_map('prepare_amount', array_column($orders, 'total'));
	$view = array('upcoming' => array(array('service' => 'Cut & Style', 'tone' => 'success', 'status_label' => 'Confirmed', 'details' => 'Tue · Ana',
		'manage_url' => '/b?id=1&t=y', 'manage_label' => 'Change or cancel', 'ics_url' => '/b?id=1&t=y&ics=1')),
		'past' => array(array('service' => 'Cut', 'tone' => 'secondary', 'status_label' => 'Done', 'details' => 'Sun', 'manage_url' => '', 'manage_label' => '', 'ics_url' => '')));
	$drawn = account_blocks_test_run('[_sdAccountPart("order_history"), _sdAccountPart("bookings"), _sdAccountPart("subscriptions"), '
		. '_sdAccountOrderHistoryHtml(' . json_encode($rows) . ', true, ' . json_encode($totals) . '), '
		. '_sdAccountOrderHistoryHtml(' . json_encode($rows) . ', false, ' . json_encode($totals) . '), '
		. '_sdAccountBookingsHtml(' . json_encode($view) . '), _sdAccountBookingsHtml({ upcoming: [], past: [] })]');
	if ($drawn === null) {
		return;
	}
	pg_assert_same(json_decode(json_encode(pg_acct_part('order_history')), true), $drawn[0], 'the order history part');
	pg_assert_same(json_decode(json_encode(pg_acct_part('bookings')), true), $drawn[1], 'the bookings part');
	pg_assert_same(array(), $drawn[2], 'subscriptions do not open');
	pg_assert_same(pg_acct_order_history_html($rows, true, array_column($orders, 'total')), $drawn[3], 'order history, linked');
	pg_assert_same(pg_acct_order_history_html($rows, false, array_column($orders, 'total')), $drawn[4], 'order history, plain');
	pg_assert_same(pg_booking_customer_view_html($view), $drawn[5], 'bookings');
	pg_assert_same('', $drawn[6], 'no booking');
}

// The widget publishes the booking rows with the orders, the starter opens
// both parts, the section panel offers the button.
function test_account_blocks_wiring()
{
	$widgets = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	pg_assert_contains("+ pg_sw_list_account_booking_rows(\$values['account_id'], \$extras['bookings'])", $widgets);
	$account = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_account.php');
	pg_assert_contains("\$out['sections']['bookings'] = pg_booking_customer_view_html(\$out['bookings']);", $account);
	pg_assert_contains("'has_upcoming_bookings' => false, 'has_past_bookings' => false", $account);
	$js = account_blocks_test_source();
	pg_assert_contains("customName: _sdT('Order History') }, _sdAccountPart('order_history')),", $js);
	pg_assert_contains("customName: _sdT('My Bookings') }, _sdAccountPart('bookings')),", $js);
	pg_assert_contains("if (_sdAccountOpens(kind, section)) {", $js);
	pg_assert_contains("_sdOrderPreviewSectionToTree(selectedNode) || _sdAccountSectionToTree(selectedNode)", $js);
	pg_assert_contains("var _acSample = _sdAccountSectionSample(_sec, doc);", $js);
	pg_assert_contains("['has_upcoming_bookings', _sdT('The member has upcoming bookings')],", $js);
}
