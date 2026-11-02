<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for texts of the Site Settings window: the Turkish wording of its
 * labels, the relative time inside "Last modified ... by ...", the Turkish
 * starter data's dollar name, and the language names of the add-language list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

// The labels read as correct, consistent Turkish.
function test_settings_turkish_labels()
{
	$tr = pg_test_language_map('tr');

	$expected = array(
		'Allow Remember Me'         => 'Beni Hatırla\'ya İzin Ver',
		'Enable Mobile'             => 'Mobil Görünümü Etkinleştir',
		'Learn more about IndexNow' => 'IndexNow hakkında daha fazla bilgi',
		'Organization City'         => 'Kuruluş İlçesi/Şehri',
		'Organization State'        => 'Kuruluş Eyaleti/İli',
		'Panel Backdrop'            => 'Panel Arka Planı',
		'Registration & Membership' => 'Kayıt ve Üyelik',
		'Payment Methods'           => 'Ödeme Yöntemleri',
		'Multi-Currency'            => 'Çoklu Para Birimi',
		'Accept Givex Cards'        => 'Givex Kartlarını Kabul Et',
		'System tools'              => 'Sistem Araçları',
	);

	foreach ($expected as $key => $value) {
		pg_assert_same($value, isset($tr[$key]) ? $tr[$key] : null, $key);
	}

	// One spelling of the brand on the iyzico settings card.
	foreach (array('Enable Pay with Iyzico', 'Enable Iyzipay Protected Currency', 'Stored-card renewals allowed by iyzico') as $key) {
		pg_assert_true(isset($tr[$key]) && (strpos($tr[$key], 'iyzico') === 0), $key . ' starts with "iyzico"');
	}
}

// The relative time inside the footer sentence keeps a small letter.
function test_settings_last_modified_relative_time_mid_sentence()
{
	$tr = pg_test_language_map('tr');
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/core.php');
	$registry = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

	pg_assert_contains("'string' => 'a moment {var:1}'", $core, 'get_relative_time() has a mid-sentence form');
	pg_assert_contains("get_relative_time(array('timestamp' => \$timestamp, 'mid_sentence' => true))", $registry, 'the footer asks for it');
	pg_assert_same('az {var:1}', isset($tr['a moment {var:1}']) ? $tr['a moment {var:1}'] : null);
}

// The Turkish starter data's 'US Dolar' is shown like 'US Dollar'.
function test_currency_label_turkish_starter_dollar()
{
	pg_assert_same(lang('US Dollar'), pg_currency_label('US Dolar'));
}

// Every language the add-language list offers is named in the panel language.
function test_settings_add_language_names_translated()
{
	$tr = pg_test_language_map('tr');
	$languages = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/languages.php');

	pg_assert_contains('h(lang($translation_info[0]))', $languages, 'the option names the language through lang()');

	foreach (pg_tr_known_languages() as $code => $info) {
		pg_assert_true(isset($tr[$info[0]]) && ($tr[$info[0]] !== ''), $info[0] . ' (' . $code . ') has a Turkish key');
	}
}
