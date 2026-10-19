<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for carrier detection, pg_shipping_carrier() and
 * pg_shipping_carriers() in includes/fn/ecommerce.php: the method code, the
 * site's default carrier, the number's shape, in that order, and the method
 * code read the same however Turkish capitals are typed.
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
