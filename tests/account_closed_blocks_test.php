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
	foreach (array('_sdPartEl', '_sdPartWhen', '_sdPartSentence', '_sdAccountPart', '_sdAccountOrderHistoryHtml', '_sdAccountBookingsHtml',
		'_sdAccountSubscriptionsHtml', '_sdAccountSubscriptionActionsHtml', '_sdAccountCreditCardsHtml', '_sdAccountStoreCreditHtml') as $fn) {
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
function account_blocks_test_draw($section, $rows, $flags, $sections = array(), $values = array())
{
	static $sid = 70;
	$tree = array('type' => 'root', 'props' => array(), 'children' => pg_acct_part($section));
	pg_cf_apply_section_bindings($tree, $sections);
	_eo_apply_visibility_bindings($tree, $flags);
	$scope = pg_sw_finish_open(array('sid' => ++$sid, 'kind' => 'my_account', 'email' => false));
	try {
		$html = trim(_render_tree_node($tree, 0, 0));
		pg_sw_list_publish('account', $rows);
		$html = _pg_member_apply_tokens($html, array('account_id' => '7') + $values);
	} finally {
		pg_sw_finish_close();
	}
	return pg_sw_finish_widget_html($html, $scope);
}

// The subscriptions section as development printed it.
function account_blocks_test_subscriptions_as_development_printed($rows, $action_url, $send_to, $token)
{
	if (!$rows) {
		return '';
	}

	$date_format = pg_sw_default_date_format('date');
	$button_labels = array(
		'pause'       => lang('Pause'),
		'resume'      => lang('Resume'),
		'update_card' => lang('Update card'),
	);
	$html = '<div class="list-group pg-sw-subscriptions">';

	foreach ($rows as $row) {
		$id = (int) $row['id'];
		$amount = (int) $row['amount'];
		$next = (int) $row['next_charge_at'];
		$status = (string) $row['status'];
		$event = pg_subscription_customer_event_label((string) $row['last_kind']);
		$confirm = pg_subscription_customer_confirm_url($row, array('kind' => $row['renewal_kind'], 'detail' => $row['renewal_detail']));

		$html .= '<div class="list-group-item">'
			. '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">'
			. '<span class="fw-semibold">' . h((string) $row['product_name']) . '</span>'
			. '<span>' . h(trim(pg_format_number($amount / 100, 2) . ' ' . (string) $row['currency'])) . '</span>'
			. '</div>'
			. '<div class="small text-body-secondary">'
			. '<span class="badge ' . (($status === 'past_due') ? 'text-bg-warning' : (($status === 'active') ? 'text-bg-success' : 'text-bg-secondary')) . ' me-2">'
			. h(pg_subscription_customer_status_label($status)) . '</span>';

		if (($next > 0) && in_array($status, array('active', 'past_due', 'paused'), true)) {
			$html .= '<span class="me-2">' . h(lang('Next charge:')) . ' ' . h(_pg_sw_short_date($next, $date_format)) . '</span>';
		} elseif (($status === 'cancelled') && ($next > 0) && ((int) $row['retry_count'] === 0)) {
			$html .= '<span class="me-2">' . h(lang('Runs until:')) . ' ' . h(_pg_sw_short_date($next, $date_format)) . '</span>';
		}

		$pending = $row['price_pending'] ?? null;

		if (is_array($pending) && in_array($status, array('active', 'past_due', 'paused'), true)) {
			$html .= '<span class="me-2">' . h(lang(array(
				'string' => 'New amount from {var:1}: {var:2}',
				'vars'   => array(_pg_sw_short_date((int) strtotime($pending['date']), $date_format), trim(pg_format_number($pending['amount'] / 100, 2) . ' ' . (string) $row['currency'])),
			))) . '</span>';
		}

		if ((string) $row['card_last4'] !== '') {
			$html .= '<span class="me-2">' . h(lang(array('string' => 'Card ending {var:1}', 'vars' => array((string) $row['card_last4'])))) . '</span>';
		}

		if ($event !== '') {
			$html .= '<span>' . h(lang('Last event:')) . ' ' . h($event)
				. (((int) $row['last_at'] > 0) ? ' (' . h(_pg_sw_short_date((int) $row['last_at'], $date_format)) . ')' : '') . '</span>';
		}

		$html .= '</div>';

		if ($confirm !== '') {
			$html .= '<div class="mt-2"><a href="' . h($confirm) . '" class="btn btn-sm btn-warning" rel="noopener noreferrer">'
				. h(lang('Confirm the payment')) . '</a></div>';
		}

		$actions = pg_subscription_customer_actions($row);

		if ($actions) {
			$html .= '<div class="d-flex flex-wrap gap-2 mt-2">';

			foreach ($actions as $action) {
				$fields = '<input type="hidden" name="token" value="' . h($token) . '">'
					. '<input type="hidden" name="id" value="' . $id . '">'
					. '<input type="hidden" name="action" value="' . h($action) . '">'
					. '<input type="hidden" name="send_to" value="' . h($send_to) . '">';

				if ($action === 'cancel') {
					// Asked twice without a script: the button that cancels
					// shows only once the customer opened this.
					$html .= '<details class="pg-sw-subscription-cancel"><summary class="btn btn-sm btn-outline-danger">' . h(lang('Cancel subscription')) . '</summary>'
						. '<form method="post" action="' . h($action_url) . '" class="mt-2">' . $fields
						. '<p class="small mb-2">' . h(($status === 'active')
							? lang('No further charges will be made. The subscription runs until the end of the period already paid.')
							: lang('No further charges will be made and the subscription ends.')) . '</p>'
						. '<button type="submit" class="btn btn-sm btn-danger">' . h(lang('Yes, cancel')) . '</button>'
						. '</form></details>';
				} else {
					$html .= '<form method="post" action="' . h($action_url) . '">' . $fields
						. '<button type="submit" class="btn btn-sm btn-outline-secondary">' . h($button_labels[$action]) . '</button>'
						. '</form>';
				}
			}

			$html .= '</div>';
		}

		$html .= '</div>';
	}

	return $html . '</div>';
}

// The store credit section as development printed it.
function account_blocks_test_credit_as_development_printed(array $view)
{
	if (empty($view['shown'])) {
		return '';
	}

	$badges = array('open' => 'text-bg-success', 'used' => 'text-bg-secondary', 'expired' => 'text-bg-warning');

	$html = '<div class="pg-sw-store-credit">'
		. '<p class="mb-3">' . h(lang('Balance')) . ': <span class="fw-semibold">' . h($view['balance_info']) . '</span></p>';

	$convert = $view['convert'];

	if (!empty($convert['shown'])) {
		$html .= '<form action="' . h($convert['url']) . '" method="post" class="mb-3">'
			. $convert['token_field']
			. '<input type="hidden" name="send_to" value="' . h($convert['send_to']) . '">'
			. '<label for="pg_sw_reward_points_convert" class="form-label">'
			. h(lang(array('string' => 'Reward points: {var:1}', 'vars' => array(pg_format_number($convert['points'], 0))))) . '</label>'
			. '<div class="input-group" style="max-width: 24rem">'
			. '<input type="number" id="pg_sw_reward_points_convert" name="points" class="form-control" min="' . (int) $convert['minimum'] . '" max="' . (int) $convert['points'] . '" step="1" value="' . (int) $convert['points'] . '" required>'
			. '<button type="submit" class="btn btn-outline-primary">' . h(lang('Convert to store credit')) . '</button>'
			. '</div>'
			. '<div class="form-text">' . h(lang(array('string' => 'All your points are worth {var:1}.', 'vars' => array(html_entity_decode($convert['value_info'], ENT_QUOTES, 'UTF-8'))))) . '</div>'
			. '</form>';
	}

	if (!empty($view['cards'])) {
		$html .= '<div class="list-group">';

		foreach ($view['cards'] as $card) {
			$html .= '<div class="list-group-item">'
				. '<div class="d-flex justify-content-between align-items-start gap-2">'
				. '<span><span class="fw-semibold">' . h($card['kind_label']) . '</span>'
				. ' <span class="badge ' . $badges[$card['state']] . ' ms-1">' . h($card['state_label']) . '</span>'
				. (($card['reason'] !== '') ? '<span class="d-block small text-body-secondary text-break">' . h($card['reason']) . '</span>' : '')
				. '</span>'
				. '<span class="text-end"><span class="fw-semibold">' . h($card['balance_info']) . '</span>'
				. '<span class="d-block small text-body-secondary">' . h(lang(array('string' => 'of {var:1}', 'vars' => array($card['amount_info'])))) . '</span></span>'
				. '</div>'
				. (($card['last_day_info'] !== '') ? '<div class="small text-body-secondary">' . h(lang(array('string' => 'Last day: {var:1}', 'vars' => array($card['last_day_info'])))) . '</div>' : '');

			if (!empty($card['uses'])) {
				$html .= '<ul class="list-unstyled small mb-0 mt-1">';

				foreach ($card['uses'] as $use) {
					$number = '#' . h($use['order_number']);
					$html .= '<li>' . (($use['view_url'] !== '') ? '<a href="' . h($use['view_url']) . '">' . $number . '</a>' : $number)
						. ' <span class="text-body-secondary">' . h($use['date_info']) . '</span> −' . h($use['amount_info']) . '</li>';
				}

				$html .= '</ul>';
			}

			$html .= '</div>';
		}

		$html .= '</div>';
	}

	return $html . '</div>';
}

// A member's subscriptions: an active one with a price change told and a
// name to escape, a paused one, one waiting for a payment confirmation and
// a cancelled one running to the end of its period.
function account_blocks_test_subscriptions()
{
	$s = function ($id, $status, $next, $extra = array()) {
		return array_merge(array('id' => $id, 'user_id' => 7, 'product_name' => 'Box ' . $id, 'amount' => 14950, 'currency' => 'TRY',
			'status' => $status, 'next_charge_at' => $next, 'retry_count' => 0, 'card_last4' => '', 'last_kind' => '', 'last_at' => 0,
			'renewal_kind' => '', 'renewal_detail' => '', 'price_pending' => null), $extra);
	};
	return array(
		$s(4, 'active', 1800000000, array('product_name' => 'Coffee & Tea', 'card_last4' => '4242', 'last_kind' => 'renewed', 'last_at' => 1799000000,
			'price_pending' => array('date' => '2027-01-01', 'amount' => 15900))),
		$s(3, 'paused', 1800500000, array('last_kind' => 'status_paused', 'last_at' => 1799100000)),
		$s(2, 'past_due', 1800000000, array('retry_count' => 1, 'renewal_kind' => 'confirm_needed', 'renewal_detail' => 'https://pay.example/c?x=1&y=2',
			'last_kind' => 'confirm_needed')),
		$s(1, 'cancelled', 1801000000, array('last_kind' => 'status_cancelled')),
	);
}

// The member's store credit as pg_credit_account_view() gives it: an open
// card spent on two orders (one without a view order page), a used one and
// an expired one; points enough to convert.
function account_blocks_test_credit_view()
{
	$use = function ($no, $url) {
		return array('order_id' => 1, 'order_number' => $no, 'order_date' => 1799000000, 'amount' => 1500,
			'amount_info' => '₺15,00', 'date_info' => '14.10.2026', 'view_url' => $url);
	};
	$card = function ($id, $state, $label, $reason, $last, $uses) {
		return array('id' => $id, 'kind' => 'manual', 'kind_label' => 'Manual', 'reason' => $reason, 'amount' => 5000, 'balance' => 3500,
			'amount_info' => '₺50,00', 'balance_info' => '₺35,00', 'last_day' => '', 'last_day_info' => $last, 'state' => $state,
			'state_label' => $label, 'uses' => $uses);
	};
	return array(
		'shown' => true, 'balance' => 3500, 'balance_info' => '₺35,00',
		'cards' => array(
			$card(6, 'open', 'Open', 'Late <delivery>', '31.12.2026', array($use('1042', '/order?id=1042&a=1'), $use('1041<b>', ''))),
			$card(5, 'used', 'Used', '', '', array()),
			$card(4, 'expired', 'Expired', '', '01.01.2026', array()),
		),
		'convert' => array('enabled' => true, 'shown' => true, 'points' => 1200, 'minimum' => 100, 'url' => '/s/convert_reward_points.php',
			'token_field' => '<input type="hidden" name="token" value="abc">', 'send_to' => '/account', 'value_info' => '&#8378;12,00'),
	);
}

// The subscriptions and store credit sections built from their rows print
// what development printed.
function test_account_blocks_subscription_credit_sections_unchanged()
{
	$rows = account_blocks_test_subscriptions();
	$old = account_blocks_test_subscriptions_as_development_printed($rows, '/s/subscription_action.php', '/account', 'tok"x');
	pg_assert_contains('Coffee &amp; Tea', $old, 'the fixture reaches the subscriptions');
	pg_assert_contains('Confirm the payment', $old, 'and the confirmation link');
	pg_assert_same($old, pg_subscription_customer_section_html($rows, '/s/subscription_action.php', '/account', 'tok"x'), 'subscriptions, byte for byte');
	pg_assert_same('', pg_subscription_customer_section_html(array(), '/s/subscription_action.php', '/account', 'tok'), 'no subscription, nothing');

	$view = account_blocks_test_credit_view();
	$old = account_blocks_test_credit_as_development_printed($view);
	pg_assert_contains('pg_sw_reward_points_convert', $old, 'the fixture reaches the conversion form');
	pg_assert_same($old, pg_credit_account_section_html($view), 'store credit, byte for byte');
	$view['convert']['shown'] = false;
	pg_assert_same(account_blocks_test_credit_as_development_printed($view), pg_credit_account_section_html($view), 'store credit without the form');
	$view['cards'] = array();
	pg_assert_same(account_blocks_test_credit_as_development_printed($view), pg_credit_account_section_html($view), 'store credit without a card');
	pg_assert_same('', pg_credit_account_section_html(array('shown' => false)), 'nothing to show');
}

// The opened subscriptions and store credit say what the sections say; the
// forms are the sections' own, with the form token.
function test_account_blocks_subscription_credit_parts()
{
	$view = pg_subscription_customer_view_rows(account_blocks_test_subscriptions(), '/s/subscription_action.php', '/account', 'tok"x');
	$old = pg_subscription_customer_view_html($view);
	$new = account_blocks_test_draw('subscriptions', pg_sw_list_account_subscription_rows(7, $view), array('has_subscriptions' => true));
	pg_assert_same(account_blocks_test_text($old), account_blocks_test_text($new), 'subscriptions: the same words');
	preg_match_all('/class="([^"]*)"/', $old, $m);
	foreach (array_unique($m[1]) as $class) {
		pg_assert_contains('class="' . $class . '"', $new, 'class ' . $class);
	}
	pg_assert_same(substr_count($old, '<form method="post"'), substr_count($new, '<form method="post"'), 'every action form');
	pg_assert_same(substr_count($old, 'name="token" value="tok&quot;x"'), substr_count($new, 'name="token" value="tok&quot;x"'), 'each with the form token, escaped once');
	pg_assert_contains('href="https://pay.example/c?x=1&amp;y=2"', $new, 'the confirmation link escaped once');
	pg_assert_contains('>Coffee &amp; Tea</span>', $new, 'the name escaped once');
	pg_assert_same('', trim(account_blocks_test_draw('subscriptions', pg_sw_list_account_subscription_rows(7, array()), array('has_subscriptions' => false))),
		'nothing without a subscription');

	$credit = account_blocks_test_credit_view();
	$old = pg_credit_account_section_html($credit);
	$rows = pg_sw_list_account_credit_rows(7, $credit['cards']);
	$flags = array('has_store_credit' => true, 'has_store_credit_cards' => true);
	$new = account_blocks_test_draw('store_credit', $rows, $flags, array('store_credit_convert' => pg_credit_account_convert_html($credit['convert'])),
		array('__store_credit_balance' => h($credit['balance_info'])));
	pg_assert_same(account_blocks_test_text($old), account_blocks_test_text($new), 'store credit: the same words');
	preg_match_all('/class="([^"]*)"/', $old, $m);
	foreach (array_unique($m[1]) as $class) {
		// A use's date and amount add ms-1 (the canvas draws no white space).
		pg_assert_contains('class="' . $class, $new, 'class ' . $class);
	}
	pg_assert_contains('<input type="hidden" name="token" value="abc">', $new, 'the conversion form keeps its token');
	pg_assert_contains('href="/order?id=1042&amp;a=1"', $new, 'the order link escaped once');
	pg_assert_contains('Late &lt;delivery&gt;', $new, 'the reason escaped once');
	$credit['convert']['shown'] = false;
	$credit['cards'] = array();
	$old = pg_credit_account_section_html($credit);
	$new = account_blocks_test_draw('store_credit', pg_sw_list_account_credit_rows(7, array()), array('has_store_credit' => true, 'has_store_credit_cards' => false),
		array('store_credit_convert' => ''), array('__store_credit_balance' => h($credit['balance_info'])));
	pg_assert_same(account_blocks_test_text($old), account_blocks_test_text($new), 'balance alone');
	pg_assert_same(false, strpos($new, '<form'), 'no form without points enough');
}

// The list rows carry the views' words, raw; the uses under their card.
function test_account_blocks_subscription_credit_rows()
{
	$view = pg_subscription_customer_view_rows(account_blocks_test_subscriptions(), '/s/subscription_action.php', '/account', 'tok');
	pg_assert_same(array('success', 'secondary', 'warning', 'secondary'), array_column($view, 'tone'), 'the badge tone by status');
	pg_assert_same('149.50 TRY', $view[0]['amount'], 'the amount from minor units');
	pg_assert_same('', $view[3]['actions_html'], 'a cancelled row offers nothing');
	pg_assert_contains('value="resume"', $view[1]['actions_html'], 'a paused row resumes');
	$rows = pg_sw_list_account_subscription_rows(7, $view)['account.subscriptions'][7];
	pg_assert_same('Coffee & Tea', $rows[0]['__subscription_product'], 'raw, escaped once by the pass');
	pg_assert_same(true, isset(pg_sw_raw_token_keys()['__subscription_actions_html']), 'the forms are markup');
	$credit = pg_sw_list_account_credit_rows(7, account_blocks_test_credit_view()['cards']);
	pg_assert_same(array('success', 'secondary', 'warning'), array_column($credit['account.store_credit'][7], '__credit_tone'));
	pg_assert_same(array('1', '', ''), array_column($credit['account.store_credit'][7], '__credit_has_uses'));
	pg_assert_same(array(6, 5, 4), array_keys($credit['account.store_credit_uses']), 'the uses keyed by card');
	pg_assert_same(array('1042', '1041<b>'), array_column($credit['account.store_credit_uses'][6], '__credit_use_order_no'));
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
	pg_assert_same(array(), pg_acct_part('store_credit_convert'), 'the conversion form stays a section');
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
	pg_assert_same(json_decode(json_encode(pg_acct_part('subscriptions')), true), $drawn[2], 'the subscriptions part');
	pg_assert_same(pg_acct_order_history_html($rows, true, array_column($orders, 'total')), $drawn[3], 'order history, linked');
	pg_assert_same(pg_acct_order_history_html($rows, false, array_column($orders, 'total')), $drawn[4], 'order history, plain');
	pg_assert_same(pg_booking_customer_view_html($view), $drawn[5], 'bookings');
	pg_assert_same('', $drawn[6], 'no booking');

	$subs = pg_subscription_customer_view_rows(account_blocks_test_subscriptions(), '/s/subscription_action.php', '/account', 'tok');
	$credit = account_blocks_test_credit_view();
	$drawn = account_blocks_test_run('[_sdAccountPart("store_credit"), _sdAccountPart("store_credit_convert"), '
		. '_sdAccountSubscriptionsHtml(' . json_encode($subs) . '), _sdAccountSubscriptionsHtml([]), '
		. '_sdAccountStoreCreditHtml(' . json_encode($credit['balance_info']) . ', ' . json_encode(pg_credit_account_convert_html($credit['convert'])) . ', '
		. json_encode($credit['cards']) . '), _sdAccountStoreCreditHtml("x", "", []), '
		. '["active", "paused", "past_due", "cancelled"].map(_sdAccountSubscriptionActionsHtml)]');
	pg_assert_same(json_decode(json_encode(pg_acct_part('store_credit')), true), $drawn[0], 'the store credit part');
	pg_assert_same(array(), $drawn[1], 'the conversion form stays the section\'s');
	pg_assert_same(pg_subscription_customer_view_html($subs), $drawn[2], 'subscriptions');
	pg_assert_same('', $drawn[3], 'no subscription');
	pg_assert_same(pg_credit_account_section_html($credit), $drawn[4], 'store credit');
	pg_assert_same(pg_credit_account_section_html(array('shown' => true, 'balance_info' => 'x', 'cards' => array(), 'convert' => array())), $drawn[5], 'balance alone');
	// The canvas draws the forms without their hidden fields.
	foreach (array('active', 'paused', 'past_due', 'cancelled') as $i => $status) {
		$row = array('id' => 1, 'status' => $status, 'next_charge_at' => 1800000000);
		$site = preg_replace('/<input type="hidden"[^>]*>/', '', pg_subscription_customer_actions_html($row, '#', '/account', 'tok'));
		pg_assert_same($site, $drawn[6][$i], 'the forms of a ' . $status . ' row');
	}
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
	pg_assert_contains("+ pg_sw_list_account_subscription_rows(\$values['account_id'], \$extras['subscriptions'])", $widgets);
	pg_assert_contains("+ pg_sw_list_account_credit_rows(\$values['account_id'], \$extras['store_credit']['cards'])", $widgets);
	pg_assert_contains("\$values['__store_credit_balance'] = h(\$extras['store_credit']['balance_info']);", $widgets);
	pg_assert_contains("\$out['sections']['subscriptions'] = pg_subscription_customer_view_html(\$out['subscriptions']);", $account);
	pg_assert_contains("\$out['sections']['store_credit_convert'] = pg_credit_account_convert_html(\$credit['convert']);", $account);
	pg_assert_contains("customName: _sdT('Store credit') }, _sdAccountPart('store_credit')),", $js);
	pg_assert_contains("['has_subscriptions', _sdT('The member has subscriptions')]", $js);
	pg_assert_contains("'__subscription_actions_html', '__schedule_period_options_html',", $js);
}
