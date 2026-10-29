<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the help and error texts that send people to a Site Settings
 * place: they name it the way the settings dialog shows it (Two-Step
 * Verification, the accessibility report's nightly check, the Paraşüt
 * messages), in English and in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once __DIR__ . '/helpers/language_map.php';

// The source of a product file, relative to pinegrap/.
function settings_place_names_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The Turkish text of an English key; a missing key fails with its name.
function settings_place_names_test_tr($key)
{
	$map = pg_test_language_map('tr');

	pg_assert_true(isset($map[$key]), 'the Turkish shards hold: ' . $key);

	return isset($map[$key]) ? (string) $map[$key] : '';
}

// Two-Step Verification without a key: the switch that makes one is in the
// Payment Methods card of Ecommerce and shows only with card payments on.
function test_settings_place_names_two_step_verification_key_hint()
{
	$key = 'Two-step verification needs an encryption key in config.php. Make one in Site Settings › Ecommerce › Payment Methods: with Enable Commerce and Credit/Debit Card on, switch on Reset Encryption Key and save.';

	pg_assert_contains("lang('" . $key . "')", settings_place_names_test_source('includes/settings/security.php'));
	pg_assert_contains('Site Ayarları › E-Ticaret › Ödeme Metodları', settings_place_names_test_tr($key));
	pg_assert_contains('Şifreleme Anahtarını Sıfırla', settings_place_names_test_tr($key));
}

// The nightly accessibility check: its switch is in General › Cron Jobs and
// shows only while Run Cron Jobs Automatically is on.
function test_settings_place_names_accessibility_cron_hint()
{
	$key = 'The nightly check is off: in Site Settings › General › Cron Jobs, with Run Cron Jobs Automatically on, switch on "Accessibility report", or check the pages now.';

	pg_assert_contains("h(lang('" . $key . "'))", settings_place_names_test_source('view_accessibility.php'));
	pg_assert_contains('Site Ayarları › Genel › Cron işleri', settings_place_names_test_tr($key));
	pg_assert_contains('Cron İşlerini Otomatik Yürüt', settings_place_names_test_tr($key));
}

// Every Paraşüt message names the E-Invoice card of Ecommerce and the
// section the Paraşüt fields sit in; no "Paraşüt card" is left.
function test_settings_place_names_parasut_messages()
{
	$keys = array(
		'includes/fn/parasut.php' => array(
			'The buyer\'s VKN/TCKN is read from the order\'s "{var:1}" field, chosen on the E-Invoice card of the Ecommerce settings (Enable Parasut Configuration), and that field is empty on this order.',
			'Parasut credentials are not set. Enter them on the E-Invoice card of the Ecommerce settings (Enable Parasut Configuration).',
			'Pinegrap is not connected to Paraşüt. On the E-Invoice card of the Ecommerce settings (Enable Parasut Configuration), authorize it with the code Paraşüt shows (Connect), or enter the Paraşüt user name and password.',
			'The Paraşüt connection has expired ({var:1}). Authorize Pinegrap once more on the E-Invoice card of the Ecommerce settings (Enable Parasut Configuration).',
			'Paraşüt no longer holds the default product/service {var:1} entered on the E-Invoice card of the Ecommerce settings (Enable Parasut Configuration), which invoice lines without a product card of their own go on. Enter there the id of a product or service Paraşüt holds; the document was not written.',
		),
		'includes/erp/edoc/parasut.php' => array(
			'The Paraşüt company id is empty. Connect on the E-Invoice card of the Ecommerce settings (Enable Parasut Configuration), or enter it there.',
			'Line "{var:1}" has no Paraşüt product to go on. Enter the id of a generic service opened in Paraşüt as the default product/service on the E-Invoice card of the Ecommerce settings (Enable Parasut Configuration).',
		),
	);

	foreach ($keys as $path => $list) {
		$source = settings_place_names_test_source($path);

		pg_assert_false(strpos($source, 'Paraşüt card of the commerce settings') !== false, $path . ': no Paraşüt card is named');
		pg_assert_false(strpos($source, 'E-commerce, Invoicing') !== false, $path . ': no Invoicing card is named');

		foreach ($list as $key) {
			pg_assert_contains(str_replace('\'', '\\\'', $key), $source, $path);

			$tr = settings_place_names_test_tr($key);

			pg_assert_true(strpos($tr, 'E-Ticaret ayarlarının E-Fatura kart') !== false, 'Turkish names the E-Invoice card: ' . $tr);
			pg_assert_contains('(Paraşüt Yapılandırmasını Etkinleştir)', $tr);
		}
	}
}
