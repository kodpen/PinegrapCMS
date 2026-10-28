<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the record values a system widget may show from an entity's
 * allow-list (includes/fn/widgets_entity_fields.php): how each kind reads,
 * the recurring-only values, the editor's twin list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _wef_product($extra = array())
{
	return $extra + array(
		'keywords' => '  oak, desk ',
		'length' => '120.5000',
		'width' => '0.0000',
		'height' => '75.0000',
		'minimum_quantity' => '2',
		'maximum_quantity' => '0',
		'preparation_time' => '3',
		'extra_shipping_cost' => '1250',
		'tax_rate' => '20.000',
		'recurring' => '0',
		'payment_period' => 'Monthly',
		'number_of_payments' => '12',
		'free_shipping' => '1',
		'gift_card' => '0',
		'selection_type' => 'quantity',
	);
}

function test_widget_entity_fields_product_values()
{
	$v = pg_sw_entity_values('product', _wef_product(), array('currency_symbol' => '$'));

	pg_assert_same('oak, desk', $v['__keywords'], 'text is trimmed');
	pg_assert_same('120.5', $v['__length'], 'trailing zeros dropped');
	pg_assert_same('', $v['__width'], 'zero measure is empty');
	pg_assert_same('75', $v['__height']);
	pg_assert_same('2', $v['__minimum_quantity']);
	pg_assert_same('', $v['__maximum_quantity'], 'zero count is empty');
	pg_assert_same('3', $v['__preparation_days']);
	pg_assert_contains('12', $v['__extra_shipping_cost'], 'minor units read as money');
	pg_assert_contains('$', $v['__extra_shipping_cost']);
	pg_assert_same('20', $v['__vat_rate']);
	pg_assert_same('1', $v['__is_free_shipping']);
	pg_assert_same('', $v['__is_gift_card'], 'no is empty, so "is empty" reads no');
	pg_assert_same('', $v['__is_donation']);
}

function test_widget_entity_fields_recurring_only()
{
	$v = pg_sw_entity_values('product', _wef_product());
	pg_assert_same('', $v['__payment_period'], 'not a recurring product');
	pg_assert_same('', $v['__number_of_payments']);

	$v = pg_sw_entity_values('product', _wef_product(array('recurring' => '1')));
	pg_assert_same(lang('Monthly'), $v['__payment_period'], 'translated words');
	pg_assert_same('12', $v['__number_of_payments']);

	$v = pg_sw_entity_values('product', _wef_product(array('recurring' => '1', 'payment_period' => 'Hourly')));
	pg_assert_same('', $v['__payment_period'], 'a value the column does not hold');
}

function test_widget_entity_fields_nulls_and_missing()
{
	$v = pg_sw_entity_values('product', _wef_product(array('tax_rate' => null, 'selection_type' => 'donation')));
	pg_assert_same('', $v['__vat_rate'], 'NULL rate: the zone decides, nothing shown');
	pg_assert_same('1', $v['__is_donation']);

	$v = pg_sw_entity_values('product', null);
	pg_assert_same(array_keys(pg_sw_entity_fields('product')), array_keys($v), 'no row: every token, empty');
	pg_assert_same('', implode('', $v));

	pg_assert_same(array(), pg_sw_entity_values('nothing', array('x' => 1)));
}

function test_widget_entity_fields_catalog_is_new()
{
	$now = strtotime('2026-10-26 12:00:00');
	pg_assert_true(pg_sw_catalog_is_new('2026-10-20', $now));
	pg_assert_false(pg_sw_catalog_is_new('2026-08-01', $now), 'older than 30 days');
	pg_assert_false(pg_sw_catalog_is_new('2026-11-01', $now), 'in the future');
	pg_assert_false(pg_sw_catalog_is_new('0000-00-00', $now));
	pg_assert_false(pg_sw_catalog_is_new('', $now));
}

function _wef_event($extra = array())
{
	return $extra + array(
		'reservations' => '1',
		'limit_reservations' => '1',
		'number_of_remaining_spots' => 0,
		'last_modified_timestamp' => strtotime('2026-10-20 14:30:00'),
		'product_price' => 4500,
		'recurrence' => '1',
		'recurrence_type' => 'week',
	);
}

function test_widget_entity_fields_event_values()
{
	$v = pg_sw_entity_values('calendar_event', _wef_event(), array('date_format' => 'd.m.Y', 'currency_symbol' => '$'));

	pg_assert_same('0', $v['__event_spots_left'], 'no places left reads 0, not empty');
	pg_assert_same('20.10.2026', $v['__event_updated_date']);
	pg_assert_contains('45', $v['__event_reservation_price']);
	pg_assert_same(lang('Weekly'), $v['__event_recurrence']);
	pg_assert_same('1', $v['__event_reservations_open']);
	pg_assert_same('1', $v['__event_spots_limited']);
	pg_assert_same('1', $v['__event_is_recurring']);
}

function test_widget_entity_fields_event_gates()
{
	$v = pg_sw_entity_values('calendar_event', _wef_event(array('limit_reservations' => '0', 'number_of_remaining_spots' => 12)));
	pg_assert_same('', $v['__event_spots_left'], 'unlimited: no count');
	pg_assert_same('', $v['__event_spots_limited']);

	$v = pg_sw_entity_values('calendar_event', _wef_event(array('reservations' => '0', 'recurrence' => '0')));
	pg_assert_same('', $v['__event_spots_left'], 'no reservations at all');
	pg_assert_same('', $v['__event_reservation_price']);
	pg_assert_same('', $v['__event_reservations_open']);
	pg_assert_same('', $v['__event_spots_limited']);
	pg_assert_same('', $v['__event_recurrence'], 'not recurring: no words');
	pg_assert_same('', $v['__event_is_recurring']);

	$v = pg_sw_entity_values('calendar_event', _wef_event(array('last_modified_timestamp' => 0, 'product_price' => 0)));
	pg_assert_same('', $v['__event_updated_date']);
	pg_assert_same('', $v['__event_reservation_price'], 'free reservation');
}

function test_widget_entity_fields_wanted()
{
	pg_assert_true(pg_sw_entity_wanted('calendar_event', '{"_bindings":{"show_if":"__event_is_recurring"}}'));
	pg_assert_false(pg_sw_entity_wanted('calendar_event', '{"_bindings":{"text":"__event_title"}}'));
}

function test_widget_entity_fields_quiz_score()
{
	$v = pg_sw_entity_values('form_record', array('quiz_score' => '0', 'quiz' => 1));
	pg_assert_same('0', $v['__quiz_score'], 'a zero score shows');

	$v = pg_sw_entity_values('form_record', array('quiz_score' => '85', 'quiz' => 1));
	pg_assert_same('85', $v['__quiz_score']);

	$v = pg_sw_entity_values('form_record', array('quiz_score' => '0', 'quiz' => 0));
	pg_assert_same('', $v['__quiz_score'], 'not a quiz form');
}

// A form record's public values come from its fields' RSS roles, the
// title and summary as plain text in the page's language, the picture only
// when the media field holds one.
function test_widget_entity_fields_post_values()
{
	$rows = array(
		array('field_name' => 'baslik', 'field_rss' => 'title', 'field_type' => 'text box', 'data' => 'Stored title', 'file_name' => ''),
		array('field_name' => 'ozet', 'field_rss' => 'description', 'field_type' => 'text area', 'data' => '<p>Short &amp; <b>sweet</b></p>', 'file_name' => ''),
		array('field_name' => 'gorsel', 'field_rss' => 'media', 'field_type' => 'file upload', 'data' => '', 'file_name' => 'cover photo.jpg'),
		array('field_name' => 'email', 'field_rss' => '', 'field_type' => 'email address', 'data' => 'a@b.c', 'file_name' => ''),
	);
	$custom = array('baslik' => array('data' => 'Translated title'), 'ozet' => array('data' => '<p>Short &amp; <b>sweet</b></p>'));
	$v = pg_sw_entity_post_values($rows, $custom);
	pg_assert_same('Translated title', $v['post_title'], 'the value in the page language');
	pg_assert_same('Short & sweet', trim(preg_replace('/\s+/', ' ', $v['post_summary'])), 'plain text');
	pg_assert_contains('cover%20photo.jpg', $v['post_image']);
	pg_assert_false(in_array('a@b.c', $v, true), 'no private value');

	$img = function ($data, $file = '') { return pg_sw_entity_post_image(array('data' => $data, 'file_name' => $file)); };
	pg_assert_same('https://x.test/a.png', $img('https://x.test/a.png'));
	pg_assert_same('', $img('https://x.test/clip.mp4'), 'a video is no picture');
	pg_assert_same('', $img('', 'notes.pdf'), 'another file is no picture');
	pg_assert_same('/files/b.webp', $img('<p><img src="/files/b.webp"></p>'));
	pg_assert_same('', $img('<img src="javascript:alert(1)">'));

	$e = pg_sw_entity_values('form_record', array('submitted_timestamp' => 86400 * 365, 'post_author' => 'Ada', 'post_title' => 'T') + $v,
		array('format_stamp' => function ($ts) { return date('Y-m-d', $ts); }));
	pg_assert_same('1971-01-01', $e['__post_date']);
	pg_assert_same('Ada', $e['__post_author']);
	pg_assert_same('T', $e['__post_title']);
	pg_assert_same('', pg_sw_entity_values('form_record', null)['__post_title']);
}

function _wef_order($extra = array())
{
	return $extra + array(
		'po_number' => ' PO-77 ',
		'custom_field_1' => 'Blue',
		'custom_field_1_label' => 'Colour',
		'custom_field_2' => '',
		'custom_field_2_label' => 'Gift note',
		'billing_fax_number' => '',
		'gift_card_discount' => '2500',
		'currency_code' => 'EUR',
		'paid_at' => strtotime('2026-10-21 09:00:00'),
		'cancelled_at' => null,
		'cancellation_reason' => 'Changed my mind',
		'refund_status' => '',
		'refunded_amount' => '0',
		'refunded_at' => null,
		'tax_exempt' => '1',
	);
}

function test_widget_entity_fields_order_values()
{
	$v = pg_sw_entity_values('order', _wef_order(), array('date_format' => 'd.m.Y', 'currency_symbol' => '$'));

	pg_assert_same('PO-77', $v['__order_po_number']);
	pg_assert_same('Blue', $v['__order_custom_field_1']);
	pg_assert_same('Colour', $v['__order_custom_field_1_label']);
	pg_assert_same('', $v['__order_custom_field_2_label'], 'no value: no label either');
	pg_assert_same('', $v['__billing_fax']);
	pg_assert_contains('25', $v['__order_gift_card_discount']);
	pg_assert_same('EUR', $v['__order_currency']);
	pg_assert_same('21.10.2026', $v['__order_paid_date']);
	pg_assert_same('', $v['__order_cancelled_date'], 'NULL stamp: not cancelled');
	pg_assert_same('', $v['__order_cancellation_reason'], 'a reason only on a cancelled order');
	pg_assert_same('', $v['__order_refund_status']);
	pg_assert_same('', $v['__order_refunded_amount']);
	pg_assert_same('', $v['__order_refunded_date']);
	pg_assert_same('1', $v['__order_is_tax_exempt']);
}

function test_widget_entity_fields_order_refund_and_cancel()
{
	$cancelled = strtotime('2026-10-22 10:00:00');
	$v = pg_sw_entity_values('order', _wef_order(array(
		'cancelled_at' => $cancelled,
		'refund_status' => 'refunded',
		'refunded_amount' => '9900',
		'refunded_at' => $cancelled,
		'tax_exempt' => '0',
	)), array('format_stamp' => function ($ts) { return 'day ' . date('j', $ts); }));

	pg_assert_same('day 22', $v['__order_cancelled_date'], 'the widget\'s own date formatter');
	pg_assert_same('Changed my mind', $v['__order_cancellation_reason']);
	pg_assert_same(lang('Refunded'), $v['__order_refund_status'], 'translated words');
	pg_assert_contains('99', $v['__order_refunded_amount']);
	pg_assert_same('day 22', $v['__order_refunded_date']);
	pg_assert_same('', $v['__order_is_tax_exempt']);

	foreach (array('pending', 'failed', 'manual_required') as $status) {
		pg_assert_same(lang('Refund in progress'), pg_sw_entity_refund_words($status), $status . ' reads as in progress to the customer');
	}
	pg_assert_same('', pg_sw_entity_refund_words('elsewhere'));
}

// Secrets and staff-only order columns never enter the allow-list.
function test_widget_entity_fields_order_no_secrets()
{
	$columns = array();
	foreach (pg_sw_entity_fields('order') as $spec) $columns[] = $spec[0];
	foreach (array('card_number', 'card_verification_number', 'expiration_month', 'expiration_year', 'cardholder',
		'authorization_code', 'ip_address', 'http_referer', 'utm_source', 'utm_campaign', 'notes', 'commission',
		'erp_account_id', 'erp_invoice_id', 'parasut_invoice_id', 'refund_reference', 'cancelled_by', 'affiliate_code') as $secret) {
		pg_assert_false(in_array($secret, $columns, true), $secret . ' stays off');
	}
}

function _wef_account($extra = array())
{
	return $extra + array(
		'user_reward_points' => '0',
		'timezone' => '(UTC+03:00) Istanbul',
		'expiration_date' => '2027-01-31',
		'salutation' => 'Dr.',
		'nickname' => ' Jo ',
		'company' => 'Acme',
		'title' => 'Buyer',
		'website' => 'https://example.com',
		'mobile_phone' => '555 1',
		'business_phone' => '555 2',
		'home_phone' => null,
		'business_city' => 'Izmir',
		'business_country' => 'Turkey',
		'tax_number' => '1234567890',
		'tax_office' => null,
		'affiliate_approved' => '0',
		'affiliate_code' => 'JO10',
	);
}

function test_widget_entity_fields_account_values()
{
	$v = pg_sw_entity_values('account', _wef_account(), array('date_format' => 'd.m.Y'));

	pg_assert_same('0', $v['__member_reward_points'], 'no points yet reads 0');
	pg_assert_same('31.01.2027', $v['__member_expiry_date'], 'a DATE column in the site format');
	pg_assert_same('Jo', $v['__member_nickname']);
	pg_assert_same('Buyer', $v['__member_job_title']);
	pg_assert_same('', $v['__member_home_phone'], 'NULL is empty');
	pg_assert_same('', $v['__member_tax_office'], 'a column the database lacks');
	pg_assert_same('(UTC+03:00) Istanbul', $v['__member_time_zone']);
	pg_assert_same('', $v['__member_affiliate_code'], 'only an approved affiliate');

	$v = pg_sw_entity_values('account', _wef_account(array('affiliate_approved' => '1', 'expiration_date' => '0000-00-00', 'user_reward_points' => null)));
	pg_assert_same('JO10', $v['__member_affiliate_code']);
	pg_assert_same('', $v['__member_expiry_date'], 'no expiry date');
	pg_assert_same('', $v['__member_reward_points'], 'reward program off');
}

// Secrets, roles and rights never enter the account allow-list, and no
// account token takes a name another widget already fills.
function test_widget_entity_fields_account_no_secrets()
{
	$columns = array();
	foreach (pg_sw_entity_fields('account') as $token => $spec) {
		$columns[] = $spec[0];
		pg_assert_true(strpos($token, '__member_') === 0, $token);
	}
	foreach (array('user_password', 'user_password_hint', 'token', 'secret_key', 'secret_key_iv', 'secret_key_hash',
		'user_devpasspin', 'user_role', 'user_manage_ecommerce', 'user_view_card_data', 'description',
		'affiliate_commission_rate', 'erp_account_id', 'parasut_contact_id', 'lead_source') as $secret) {
		pg_assert_false(in_array($secret, $columns, true), $secret . ' stays off');
	}
	pg_assert_false(isset(pg_sw_entity_fields('account')['__member_id']), 'the order view fills __member_id');
}

function _wef_line($extra = array())
{
	return $extra + array(
		'show_shipped_quantity' => '1',
		'shipped_quantity' => '0',
		'tax_total' => '360',
		'recurring_payment_period' => 'Monthly',
		'recurring_number_of_payments' => '6',
		'recurring_start_date' => '2026-11-01',
		'event_name' => 'Pottery class',
		'event_date' => 'Monday, November 2, 2026 10:00 AM',
		'added_by_offer' => '0',
		'discounted_by_offer' => '1',
	);
}

function test_widget_entity_fields_order_line_values()
{
	$v = pg_sw_entity_values('order_line', _wef_line(), array('date_format' => 'd.m.Y', 'currency_symbol' => '$'));

	pg_assert_same('0', $v['__item_shipped_qty'], 'nothing shipped yet reads 0 where the shop shows it');
	pg_assert_contains('3.60', $v['__item_vat']);
	pg_assert_same(lang('Monthly'), $v['__item_payment_period']);
	pg_assert_same('6', $v['__item_payment_count']);
	pg_assert_same('01.11.2026', $v['__item_first_payment_date']);
	pg_assert_same('Pottery class', $v['__item_event_name']);
	pg_assert_same('', $v['__item_added_by_offer']);
	pg_assert_same('1', $v['__item_discounted_by_offer']);

	$v = pg_sw_entity_values('order_line', _wef_line(array('show_shipped_quantity' => '0', 'recurring_payment_period' => '', 'tax_total' => null)));
	pg_assert_same('', $v['__item_shipped_qty'], 'the shop does not show it');
	pg_assert_same('', $v['__item_payment_period'], 'not a subscription');
	pg_assert_same('', $v['__item_payment_count']);
	pg_assert_same('', $v['__item_first_payment_date']);
	pg_assert_same('', $v['__item_vat'], 'a database without tax_total');
}

function test_widget_entity_fields_order_line_event_and_select()
{
	$row = pg_sw_entity_order_line_event(array('calendar_event_id' => 0));
	pg_assert_same('', $row['event_name'], 'no event reserved');
	pg_assert_same('', $row['event_date']);

	// The event columns are read by their own function, never selected.
	pg_assert_same(array('event_name', 'event_date'), array_values(array_filter(array_map(function ($spec) {
		return isset($spec[2]['from']) ? $spec[0] : null;
	}, pg_sw_entity_fields('order_line')))));

	// No line token begins with another order view item token, so a fill
	// can never cut one short.
	foreach (array_keys(pg_sw_entity_fields('order_line')) as $token) {
		foreach (array('__item_number', '__item_name', '__item_id', '__item_qty', '__item_price', '__item_total', '__item_image', '__item_url', '__item_ship_to') as $old) {
			pg_assert_false(strpos($token, $old) === 0, $token . ' / ' . $old);
		}
	}
}

function test_widget_entity_fields_ship_to_values()
{
	$row = array('ship_to_name' => 'Office', 'salutation' => 'Mr.', 'address_type' => 'business',
		'ship_date' => '2026-11-03', 'delivery_date' => '0000-00-00');
	$v = pg_sw_entity_values('ship_to', $row, array('date_format' => 'd.m.Y'));

	pg_assert_same('Office', $v['__ship_to_name']);
	pg_assert_same('Mr.', $v['__shipping_salutation']);
	pg_assert_same(lang('Business address'), $v['__shipping_address_type'], 'a choice reads as translated words');
	pg_assert_same('03.11.2026', $v['__shipping_ship_date']);
	pg_assert_same('', $v['__shipping_delivered_date'], 'not delivered yet');

	$v = pg_sw_entity_values('ship_to', array('address_type' => 'residential'));
	pg_assert_same(lang('Residential address'), $v['__shipping_address_type']);
	$v = pg_sw_entity_values('ship_to', array());
	pg_assert_same('', $v['__shipping_address_type'], 'a digital order has no recipient');

	// The order view's first recipient and a list of recipients read the
	// same columns in the same query.
	$sql = pg_sw_entity_select('ship_to', 'ship_tos');
	foreach (array('ship_to_name', 'salutation', 'address_type', 'ship_date', 'delivery_date') as $column) {
		pg_assert_contains('ship_tos.' . $column, $sql);
	}
	$list = pg_sw_list_order_shipment_row($row, function ($ymd) { return 'on ' . $ymd; });
	pg_assert_same('on 2026-11-03', $list['__shipping_ship_date'], 'a list row dates it the widget\'s way');
	pg_assert_same(lang('Business address'), $list['__shipping_address_type']);
}

function test_widget_entity_fields_address_book_values()
{
	$v = pg_sw_entity_values('address_book', array('salutation' => 'Dr.', 'first_name' => ' Ayşe ', 'last_name' => 'Kaya',
		'address_1' => 'Street 1', 'address_2' => '', 'city' => 'Izmir', 'state' => 'Izmir', 'zip_code' => '35000',
		'country' => 'Turkey', 'address_type' => 'residential'));

	pg_assert_same('Ayşe', $v['__recipient_first_name']);
	pg_assert_same('Kaya', $v['__recipient_last_name']);
	pg_assert_same('', $v['__recipient_address_2']);
	pg_assert_same('35000', $v['__recipient_zip']);
	pg_assert_same('Turkey', $v['__recipient_country']);
	pg_assert_same(lang('Residential address'), $v['__recipient_address_type']);
}

// Shipping cost, zone, offer, verification and package data stay off, and
// no address token takes a name its widget already fills.
function test_widget_entity_fields_address_no_internals()
{
	foreach (array('ship_to', 'address_book') as $entity) {
		foreach (pg_sw_entity_fields($entity) as $token => $spec) {
			pg_assert_false(in_array($spec[0], array('shipping_cost', 'original_shipping_cost', 'zone_id', 'offer_id',
				'address_verified', 'packages', 'shipping_method_code', 'arrival_date_code', 'user', 'order_id'), true), $token);
		}
	}
	foreach (array('__shipping_name', '__shipping_first_name', '__shipping_address_1', '__shipping_country', '__ship_to_key') as $own) {
		pg_assert_false(isset(pg_sw_entity_fields('ship_to')[$own]), $own);
	}
	foreach (array('__recipient_name', '__recipient_full_name', '__recipient_company', '__recipient_address', '__recipient_phone') as $own) {
		pg_assert_false(isset(pg_sw_entity_fields('address_book')[$own]), $own);
	}
}

// The order lists carry the recipient's, the line's and its product's
// values as their own row tokens, a yes/no one marked as a condition.
function test_widget_entity_fields_order_list_rows()
{
	$sources = pg_sw_list_sources();
	$names = function ($source) use ($sources) { return array_map(function ($t) { return $t[0]; }, $sources[$source]['tokens']); };
	foreach (array_keys(pg_sw_entity_fields('ship_to')) as $token) {
		pg_assert_true(in_array($token, $names('order.shipments'), true), $token);
	}
	foreach (array_merge(array_keys(pg_sw_entity_fields('order_line')), array_keys(pg_sw_entity_fields('product'))) as $token) {
		pg_assert_true(in_array($token, $names('order.items'), true), $token);
	}
	foreach ($sources['order.items']['tokens'] as $t) {
		if ($t[0] === '__item_added_by_offer' || $t[0] === '__is_gift_card') pg_assert_same('cond', isset($t[2]) ? $t[2] : '', $t[0]);
		if ($t[0] === '__item_vat') pg_assert_same(2, count($t));
	}
}

// The list view never offers the record's allow-listed values.
function test_widget_entity_fields_quiz_not_in_list_view()
{
	$js = (string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	pg_assert_true((bool)preg_match('/var SW_BUILTIN_FIELD_OPTIONS = (.*?);\n/s', $js, $m), 'list view options found');
	pg_assert_false(strpos($m[1], 'SW_FORM_RECORD_ENTITY_TOKENS') !== false || strpos($m[1], '__quiz_score') !== false);
}

// The editor's twin lists name the same tokens in the same order as
// pg_sw_entity_fields(), and mark exactly the yes/no ones as condition-only.
function test_widget_entity_fields_editor_twin()
{
	$js = (string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');

	foreach (array('product' => 'SW_PRODUCT_ENTITY_TOKENS', 'calendar_event' => 'SW_EVENT_ENTITY_TOKENS', 'form_record' => 'SW_FORM_RECORD_ENTITY_TOKENS', 'order' => 'SW_ORDER_ENTITY_TOKENS', 'account' => 'SW_MEMBER_ENTITY_TOKENS', 'order_line' => 'SW_ORDER_LINE_ENTITY_TOKENS', 'ship_to' => 'SW_SHIP_TO_ENTITY_TOKENS', 'address_book' => 'SW_ADDRESS_BOOK_ENTITY_TOKENS') as $entity => $name) {
		pg_assert_true((bool)preg_match('/var ' . $name . ' = \[(.*?)\n    \];/s', $js, $m), $name . ' found');

		preg_match_all("/\['(__[a-z0-9_]+)',[^\n]*?(, 'cond')?\]/", $m[1], $rows, PREG_SET_ORDER);
		$tokens = array();
		$cond = array();
		foreach ($rows as $row) {
			$tokens[] = $row[1];
			if (!empty($row[2])) $cond[] = $row[1];
		}

		$fields = pg_sw_entity_fields($entity);
		pg_assert_same(array_keys($fields), $tokens, $name);

		$yesno = array();
		foreach ($fields as $token => $spec) {
			if ($spec[1] === 'yesno') $yesno[] = $token;
		}
		pg_assert_same($yesno, $cond, $name . ' conditions');
	}
}
