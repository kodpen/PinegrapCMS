<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for carrier detection, pg_shipping_carrier() and
 * pg_shipping_carriers() in includes/fn/ecommerce.php: the method code, the
 * site's default carrier, the number's shape, in that order, and the method
 * code read the same however Turkish capitals are typed. Then the pure parts
 * of the shipment write path in includes/fn/shipping.php: the number format
 * warning, the S10 check digit, which saves record a delivery, and the values
 * a row stores. The database half (pg_shipment_record(), pg_shipment_prune(),
 * pg_shipment_delivered()) is checked on a sandbox; only its refusals that
 * happen before any query are tested here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every carrier entry carries the four keys pg_shipping_carrier() reads.
function test_shipping_carriers_have_every_key()
{
	foreach (pg_shipping_carriers() as $carrier => $entry) {
		foreach (array('name', 'aliases', 'url', 'patterns') as $key) {
			pg_assert_true(isset($entry[$key]), $carrier . '.' . $key);
		}
		pg_assert_contains('{code}', $entry['url'], $carrier . '.url');
	}
}

// A method code listed as an alias names the carrier outright.
function test_shipping_carrier_from_alias()
{
	pg_assert_same('aras', pg_shipping_carrier('1234567890', 'tr-aras'));
}

// A service-style method code is read a word at a time.
function test_shipping_carrier_from_method_word()
{
	pg_assert_same('mng', pg_shipping_carrier('1234567890', 'MNG Ertesi Gün'));
}

// With no method code, a 1Z number is recognised by its shape.
function test_shipping_carrier_from_ups_number_shape()
{
	pg_assert_same('ups', pg_shipping_carrier('1Z999AA10123456784', ''));
}

// Current behaviour, pinned as it is today: an 11-digit plain number with no
// method code and no default carrier matches the second UPS pattern, so it is
// read as UPS even though the Turkish carriers also issue plain-digit
// numbers. Change this test together with the detection, not on its own.
function test_shipping_carrier_plain_eleven_digits_reads_as_ups()
{
	pg_assert_same('ups', pg_shipping_carrier('12345678901', ''));
}

// The site's default carrier answers a method code that names no carrier,
// before the number's shape: the 11-digit Turkish number is no longer UPS.
function test_shipping_carrier_default_before_number_shape()
{
	pg_assert_same('yurtici', pg_shipping_carrier('12345678901', 'Standart Teslimat', 'yurtici'), 'a method code that says nothing');
	pg_assert_same('yurtici', pg_shipping_carrier('12345678901', '', 'yurtici'), 'no method code');
	pg_assert_same('yurtici', pg_shipping_carrier('1Z999AA10123456784', '', 'yurtici'), 'the default beats a shape too');
	pg_assert_same('aras', pg_shipping_carrier('12345678901', 'Ücretsiz Kargo', ' ARAS '), 'the key read like a method code');
}

// A method code that names a carrier keeps it, whatever the default says.
function test_shipping_carrier_method_beats_default()
{
	pg_assert_same('mng', pg_shipping_carrier('12345678901', 'MNG Ertesi Gün', 'yurtici'), 'a word of the code');
	pg_assert_same('ups', pg_shipping_carrier('1Z999AA10123456784', 'UPS-5GUN', 'yurtici'), 'a word of the code, UPS');
	pg_assert_same('aras', pg_shipping_carrier('12345678901', 'tr-aras', 'yurtici'), 'an alias');
}

// A default that is not a carrier key is no default.
function test_shipping_carrier_unknown_default_ignored()
{
	pg_assert_same('ups', pg_shipping_carrier('12345678901', '', 'nope'), 'unknown key');
	pg_assert_same('ups', pg_shipping_carrier('12345678901', '', ''), 'none');
	pg_assert_same('', pg_shipping_carrier('', '', 'yurtici'), 'no number, no carrier');
}

// Turkish capitals: "YURTİÇİ" (dotted capital I) used to lower-case to
// "yurti̇çi̇" and match nothing; typed with ASCII capitals it still reads.
function test_shipping_carrier_turkish_capitals()
{
	pg_assert_same('yurtici', pg_shipping_carrier('12345678901', 'YURTİÇİ KARGO'), 'Turkish capitals');
	pg_assert_same('yurtici', pg_shipping_carrier('12345678901', 'YURTİÇİ'), 'the key in Turkish capitals');
	pg_assert_same('yurtici', pg_shipping_carrier('12345678901', 'YURTICI KARGO'), 'ASCII capitals');
	pg_assert_same('yurtici', pg_shipping_carrier('12345678901', 'Yurtıcı Kargo Standart'), 'a dotless i, a word of the code');
	pg_assert_same('surat', pg_shipping_carrier('12345678901', 'SÜRAT KARGO'), 'Ü');
	pg_assert_same('surat', pg_shipping_carrier('12345678901', 'Surat Kargo'), 'without the dots');
	pg_assert_same('ptt', pg_shipping_carrier('12345678901', 'PTT Kargo İçi'), 'a capital I elsewhere in the code');
}

// The link and the name ask the same question with the same default.
function test_shipping_tracking_url_and_name_take_the_default()
{
	pg_assert_contains('yurticikargo.com', get_shipping_tracking_url('12345678901', 'Standart Teslimat', 'yurtici'), 'link');
	pg_assert_contains('12345678901', get_shipping_tracking_url('12345678901', 'Standart Teslimat', 'yurtici'), 'the number in the link');
	pg_assert_same('Yurtiçi Kargo', get_shipping_carrier_name('12345678901', 'Standart Teslimat', 'yurtici'), 'name');
	pg_assert_same('UPS', get_shipping_carrier_name('12345678901', 'Standart Teslimat', ''), 'no default: the shape');
	pg_assert_same('', get_shipping_tracking_url('', 'Standart Teslimat', 'yurtici'), 'no number, no link');
}

// Without the define (as here), the site has no default carrier, and the
// helpers fall back to it when no default is handed in.
function test_shipping_default_carrier_unset()
{
	pg_assert_same('', pg_shipping_default_carrier(), 'no define');
	pg_assert_same('UPS', get_shipping_carrier_name('12345678901', 'Standart Teslimat'), 'the helpers read it');
}

// The method-code reading on its own: a carrier somebody named, never the
// site's default or a number's shape.
function test_shipping_method_carrier_names_only()
{
	pg_assert_same('mng', pg_shipping_method_carrier('MNG Ertesi Gün'), 'a word of the code');
	pg_assert_same('yurtici', pg_shipping_method_carrier('Yurtiçi Kargo'), 'a carrier name, as a marketplace sends it');
	pg_assert_same('aras', pg_shipping_method_carrier(' ARAS '), 'a key in capitals');
	pg_assert_same('', pg_shipping_method_carrier('Standart Teslimat'), 'a code that names none');
	pg_assert_same('', pg_shipping_method_carrier(''), 'no code');
	pg_assert_same('', pg_shipping_method_carrier(null), 'not a string');
}

// The status and source lists the shipment row's VARCHAR columns hold.
function test_shipment_statuses_and_sources()
{
	pg_assert_same(array('created', 'in_transit', 'out_for_delivery', 'delivered', 'returned', 'exception'), pg_shipment_statuses());
	pg_assert_same(array('panel', 'api', 'marketplace', 'carrier', 'csv'), pg_shipment_sources());
	pg_assert_same('api', pg_shipment_source(' API '), 'case and spaces');
	pg_assert_same('marketplace', pg_shipment_source('marketplace'));
	pg_assert_same('panel', pg_shipment_source('somewhere'), 'unknown');
	pg_assert_same('panel', pg_shipment_source(null), 'missing');
}

// A number is stored trimmed and cut to the column.
function test_shipment_number_clean()
{
	pg_assert_same('ABC123', pg_shipment_number_clean("  ABC123 \n"));
	pg_assert_same(100, mb_strlen(pg_shipment_number_clean(str_repeat('ç', 150))), 'cut to 100 characters');
	pg_assert_same('', pg_shipment_number_clean('   '), 'blank');
	pg_assert_same('', pg_shipment_number_clean(array('1')), 'not a scalar');
	pg_assert_same('12345', pg_shipment_number_clean(12345), 'an integer');
}

// Only an absolute http(s) address is kept as a carrier's tracking page: it
// is printed as a link.
function test_shipment_tracking_url_clean()
{
	pg_assert_same('https://www.n11.com/kargo-takip?code=123', pg_shipment_tracking_url_clean(' https://www.n11.com/kargo-takip?code=123 '));
	pg_assert_same('http://example.com/t/1', pg_shipment_tracking_url_clean('http://example.com/t/1'), 'http');
	pg_assert_same('', pg_shipment_tracking_url_clean('javascript:alert(1)'), 'a script address');
	pg_assert_same('', pg_shipment_tracking_url_clean('//example.com/t/1'), 'scheme-relative');
	pg_assert_same('', pg_shipment_tracking_url_clean('/kargo/1'), 'a path');
	pg_assert_same('', pg_shipment_tracking_url_clean('ftp://example.com/1'), 'another scheme');
	pg_assert_same('', pg_shipment_tracking_url_clean('https://example.com/a b'), 'a space');
	pg_assert_same('', pg_shipment_tracking_url_clean('https://example.com/' . str_repeat('a', 500)), 'longer than the column');
	pg_assert_same('', pg_shipment_tracking_url_clean(array('https://example.com')), 'not a string');
}

// The DATE columns read back as 0000-00-00 when empty.
function test_shipment_date()
{
	pg_assert_same('2026-10-20', pg_shipment_date('2026-10-20'));
	pg_assert_same('2026-10-20', pg_shipment_date('2026-10-20 08:30:00'), 'a datetime');
	pg_assert_same('', pg_shipment_date('0000-00-00'), 'the empty DATE');
	pg_assert_same('', pg_shipment_date(''), 'blank');
	pg_assert_same('', pg_shipment_date('2026-02-30'), 'no such day');
	pg_assert_same('', pg_shipment_date('20.10.2026'), 'another format');
	pg_assert_same('', pg_shipment_date(null), 'not a string');
}

// A day becomes a moment: now for today, noon for an earlier day, never the
// future.
function test_shipment_day_time()
{
	$now = strtotime('2026-10-20 15:40:00');

	pg_assert_same($now, pg_shipment_day_time('2026-10-20', $now), 'today');
	pg_assert_same(strtotime('2026-10-18 12:00:00'), pg_shipment_day_time('2026-10-18', $now), 'an earlier day');
	pg_assert_same($now, pg_shipment_day_time('2026-10-25', $now), 'a later day');
	pg_assert_same($now, pg_shipment_day_time('0000-00-00', $now), 'no day');
}

// Which saves record a delivery. The rule it replaces announced a date typed
// into an empty field (even a future one) and nothing else, so checkout's
// estimate kept almost every web order from ever being announced.
function test_shipment_delivery_entered()
{
	$today = '2026-10-20';

	pg_assert_true(pg_shipment_delivery_entered('0000-00-00', '2026-10-20', $today), 'entered today');
	pg_assert_true(pg_shipment_delivery_entered('', '2026-10-18', $today), 'entered as an earlier day');
	pg_assert_true(pg_shipment_delivery_entered('2026-10-23', '2026-10-19', $today), 'the estimate replaced by the day it arrived');
	pg_assert_false(pg_shipment_delivery_entered('2026-10-23', '2026-10-23', $today), 'the estimate sent back unchanged');
	pg_assert_false(pg_shipment_delivery_entered('2026-10-18', '2026-10-18', $today), 'a past date sent back unchanged');
	pg_assert_false(pg_shipment_delivery_entered('0000-00-00', '2026-10-25', $today), 'a future date is an estimate');
	pg_assert_false(pg_shipment_delivery_entered('2026-10-23', '2026-10-28', $today), 'the estimate moved later');
	pg_assert_false(pg_shipment_delivery_entered('2026-10-18', '', $today), 'the date cleared');
	pg_assert_false(pg_shipment_delivery_entered('', '2026-10-18', ''), 'no today');
}

// The UPU S10 check digit, all three branches of the mod 11 rule.
function test_shipment_s10_check_digit()
{
	pg_assert_true(pg_shipment_s10_valid('RR123456785TR'), 'the standard example');
	pg_assert_true(pg_shipment_s10_valid('RR100000140TR'), 'remainder 1: check digit 0');
	pg_assert_true(pg_shipment_s10_valid('RR100000025TR'), 'remainder 0: check digit 5');
	pg_assert_true(pg_shipment_s10_valid(' rr 123 456 785 tr '), 'lower case and spaces');
	pg_assert_false(pg_shipment_s10_valid('RR123456784TR'), 'a wrong check digit');
	pg_assert_false(pg_shipment_s10_valid('RR12345678TR'), 'a digit short');
	pg_assert_false(pg_shipment_s10_valid('12345678901'), 'plain digits');
}

// The format warning: a hint for the operator, never a refusal.
function test_shipment_number_warning()
{
	pg_assert_false(pg_shipment_number_warning('12345678901', 'yurtici'), 'Turkish courier, plain digits');
	pg_assert_true(pg_shipment_number_warning('1Z999AA10123456784', 'yurtici'), 'Turkish courier, a UPS number');
	pg_assert_true(pg_shipment_number_warning('1234567', 'aras'), 'Turkish courier, too few digits');
	pg_assert_false(pg_shipment_number_warning('12345678901', ' MNG '), 'the key in capitals');
	pg_assert_false(pg_shipment_number_warning('RR123456785TR', 'ptt'), 'PTT, an S10 number');
	pg_assert_true(pg_shipment_number_warning('RR123456784TR', 'ptt'), 'PTT, an S10 number with a wrong check digit');
	pg_assert_false(pg_shipment_number_warning('2730012345678', 'ptt'), 'PTT, a domestic barcode');
	pg_assert_false(pg_shipment_number_warning('1Z999AA10123456784', 'ups'), 'UPS, its own pattern');
	pg_assert_true(pg_shipment_number_warning('ABCDEFGH', 'ups'), 'UPS, nothing like it');
	pg_assert_false(pg_shipment_number_warning('ABC-12345', ''), 'no carrier: only the general shape');
	pg_assert_false(pg_shipment_number_warning('ABC-12345', 'nope'), 'not a carrier key');
	pg_assert_true(pg_shipment_number_warning('https://kargo.example/123', ''), 'a pasted link');
	pg_assert_true(pg_shipment_number_warning('Aras: 12345678', 'aras'), 'a note around the number');
	pg_assert_true(pg_shipment_number_warning('1234', ''), 'too short to be a number');
	pg_assert_true(pg_shipment_number_warning('çok-yanlış-1234', ''), 'a letter outside ASCII');
	pg_assert_false(pg_shipment_number_warning('', 'yurtici'), 'no number, nothing to warn about');
}

// The write path refuses before it asks the database anything.
function test_shipment_writers_refuse_without_a_parcel()
{
	pg_assert_false(pg_shipment_record(1, 1, '   ')['ok'], 'no number');
	pg_assert_false(pg_shipment_record(0, 1, '12345678901')['ok'], 'no order');
	pg_assert_false(pg_shipment_record(1, -1, '12345678901')['ok'], 'no address');
	pg_assert_same(0, pg_shipment_prune(0, array()), 'prune needs an address');
	pg_assert_false(pg_shipment_delivered(0, 0), 'delivered needs an address');
}

// Without a connection the schema gate stays shut, so nothing writes the new
// columns before the 11.0.20 step has added them.
function test_shipment_rows_ready_without_a_database()
{
	pg_assert_false(pg_shipment_rows_ready());
}

// How the order screen reads a parcel row: the carrier and link the row
// names, else the ones worked out as every screen works them out.
function test_shipment_view()
{
	$named = pg_shipment_view(array('number' => '12345678901', 'carrier' => 'aras', 'status' => 'delivered', 'format_warning' => '1'), 'Standart Teslimat', '');
	pg_assert_same('aras', $named['carrier'], 'the row names the carrier');
	pg_assert_true($named['named'], 'named');
	pg_assert_same('Aras Kargo', $named['carrier_name']);
	pg_assert_contains('araskargo.com.tr', $named['url'], 'link from the named carrier');
	pg_assert_same('delivered', $named['status']);
	pg_assert_true($named['warning'], 'warning flag');

	$guessed = pg_shipment_view(array('number' => '12345678901', 'carrier' => ''), 'MNG Ertesi Gün', 'yurtici');
	pg_assert_same('mng', $guessed['carrier'], 'guessed from the method code');
	pg_assert_false($guessed['named'], 'not named');
	pg_assert_same('created', $guessed['status'], 'no status: created');
	pg_assert_false($guessed['warning'], 'no warning');

	$own_link = pg_shipment_view(array('number' => '73001234567', 'carrier' => 'yurtici', 'tracking_url' => 'https://www.n11.com/kargo/73001234567'), '', '');
	pg_assert_same('https://www.n11.com/kargo/73001234567', $own_link['url'], 'the row\'s own link first');

	$bad_link = pg_shipment_view(array('number' => '73001234567', 'carrier' => 'yurtici', 'tracking_url' => 'javascript:alert(1)'), '', '');
	pg_assert_contains('yurticikargo.com', $bad_link['url'], 'an unsafe stored link is not used');

	$unknown = pg_shipment_view(array('number' => 'ABC-12345', 'carrier' => 'nope', 'status' => 'lost'), 'Standart Teslimat', '');
	pg_assert_same('', $unknown['carrier'], 'no carrier');
	pg_assert_same('', $unknown['url'], 'no link');
	pg_assert_same('created', $unknown['status'], 'an unknown status reads as created');
}

// The badge of each status.
function test_shipment_status_badges()
{
	pg_assert_same('text-bg-success', pg_shipment_status_class('delivered'));
	pg_assert_same('text-bg-primary', pg_shipment_status_class('in_transit'));
	pg_assert_same('text-bg-primary', pg_shipment_status_class('out_for_delivery'));
	pg_assert_same('text-bg-warning', pg_shipment_status_class('returned'));
	pg_assert_same('text-bg-danger', pg_shipment_status_class('exception'));
	pg_assert_same('text-bg-secondary', pg_shipment_status_class('created'));
	pg_assert_same('text-bg-secondary', pg_shipment_status_class('anything'));
	pg_assert_same(lang('Recorded'), pg_shipment_status_label('anything'), 'unknown reads as recorded');
	pg_assert_same(lang('Delivered'), pg_shipment_status_label('delivered'));
}

// The carrier select: "Automatic" first, naming what it resolves to, then
// every carrier; labels escaped because the select prints them raw.
function test_shipment_carrier_options()
{
	$options = pg_shipment_carrier_options('Yurtiçi Kargo');
	pg_assert_same('', reset($options), 'Automatic is the first option');
	pg_assert_contains('Yurtiçi Kargo', (string) key($options), 'and names the carrier it resolves to');
	pg_assert_same(count(pg_shipping_carriers()) + 1, count($options), 'every carrier once');
	pg_assert_contains('aras', array_values($options), 'a carrier key as the value');

	$plain = pg_shipment_carrier_options('');
	pg_assert_same(h(lang('Automatic')), (string) key($plain), 'nothing to name');

	$escaped = pg_shipment_carrier_options('<b>x</b>');
	pg_assert_contains('&lt;b&gt;', (string) key($escaped), 'escaped');
}

// The warning note names the carrier it checked against.
function test_shipment_warning_text()
{
	pg_assert_contains('MNG Kargo', pg_shipment_warning_text('MNG Kargo'));
	pg_assert_same(lang('This does not look like a tracking number. It was saved anyway.'), pg_shipment_warning_text(''));
}

// The order_shipped / order_delivered e-mail fields: every number, each
// carrier once in the order first met, and the first parcel's link (one link
// fits an href); a row without a number adds nothing.
function test_shipment_merge_fields()
{
	$fields = pg_shipment_merge_fields(array(
		array('number' => '1234567890', 'carrier_name' => 'Aras Kargo', 'url' => ''),
		array('number' => '', 'carrier_name' => 'MNG Kargo', 'url' => 'https://example.com/x'),
		array('number' => 'AB123', 'carrier_name' => 'Yurtiçi Kargo', 'url' => 'https://example.com/ab123'),
		array('number' => '777', 'carrier_name' => 'Aras Kargo', 'url' => 'https://example.com/777'),
		array('number' => '888', 'carrier_name' => '', 'url' => ''),
	));

	pg_assert_same(array(
		array('name' => 'tracking_numbers', 'data' => '1234567890, AB123, 777, 888'),
		array('name' => 'carrier_name', 'data' => 'Aras Kargo, Yurtiçi Kargo'),
		array('name' => 'tracking_url', 'data' => 'https://example.com/ab123'),
	), $fields);

	// No parcel: the fields are still there, empty, so a [[...||...]]
	// conditional takes its else part instead of printing the ^^name^^.
	pg_assert_same(array(
		array('name' => 'tracking_numbers', 'data' => ''),
		array('name' => 'carrier_name', 'data' => ''),
		array('name' => 'tracking_url', 'data' => ''),
	), pg_shipment_merge_fields(array()));
}
