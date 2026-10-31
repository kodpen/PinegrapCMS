<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The texts that send an operator to a Site Settings card name the card's
 * place today: Update Channel, Alerts and Maintenance Mode under Updates and
 * maintenance, Visitor Tracking and Legal Documents under Visitors and
 * privacy, Google Reviews and the Application API under Integrations.
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

function settings_place_texts_test_files()
{
	return array(
		'software_repair.php', 'database_engine.php', 'includes/panel/software.php',
		'includes/fn/update.php', 'includes/fn/system_status.php', 'includes/fn/visitor_retention.php',
		'includes/fn/ops_alerts.php', 'includes/fn/waf_notice.php', 'includes/fn/maintenance_mode.php',
		'includes/fn/google_reviews.php', 'includes/api/resources/system.php',
		'assets/js/style_designer.src.js', 'includes/workspace/claude.php', 'install/index.php',
		'includes/migrations/2026.11.0.php',
	);
}

function test_settings_place_texts_name_no_old_place()
{
	$old = array(
		'General, Update Channel', 'General, Alerts', 'General, Maintenance Mode',
		'Settings › SEO › Visitor Tracking', 'Communication, Google Reviews',
		"'Site Settings › API'", 'Ecommerce › Legal Documents', 'Site Map and Crawling',
		'Settings › Security', 'Site Settings › Communication: the key',
	);

	foreach (settings_place_texts_test_files() as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		foreach ($old as $text) {
			pg_assert_true(strpos($source, $text) === false, $file . ' no longer names "' . $text . '"');
		}
	}
}

// The new places, in both languages, with the Turkish card names.
function test_settings_place_texts_translate_the_new_places()
{
	$map = pg_test_language_map('tr');

	$cases = array(
		'Site Settings › Integrations › Application API' => 'Site Ayarları › Entegrasyonlar › Uygulama API\'si',
		'Site Settings › Visitors and privacy › Legal Documents' => 'Site Ayarları › Ziyaretçiler ve Gizlilik › Yasal Belgeler',
		'Site Settings › SEO › Search Engine Optimization' => 'Site Ayarları › SEO › Arama Motoru Optimizasyonu',
	);

	foreach ($cases as $english => $turkish) {
		pg_assert_same($turkish, isset($map[$english]) ? $map[$english] : null, $english);
	}

	$checks = array(
		'Visitor records are kept without a limit. Set a period under Site Settings › Visitors and privacy › Visitor Tracking.' => 'Ziyaretçiler ve Gizlilik › Ziyaretçi Takibi',
		'The same alert is sent at most once a day while the problem lasts. Alerts are set under Site Settings › Updates and maintenance › Alerts.' => 'Güncellemeler ve Bakım › Uyarılar',
		'The Places API key and the Place ID are set in Site Settings › Integrations › Google Reviews. Without them the widget shows nothing on the site.' => 'Entegrasyonlar › Google Yorumları',
	);

	foreach ($checks as $english => $place) {
		pg_assert_contains($place, isset($map[$english]) ? $map[$english] : '', $english);
	}

	$claude = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/claude.php');
	pg_assert_contains("settings_api.php#pgset-api'", $claude);
}
