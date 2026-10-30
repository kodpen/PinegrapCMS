<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Short shared keys read in the sense of each screen: SEO stays SEO, the
 * cheque direction filter has its own key beside the cheque status, the
 * uptime note names uptime monitors, and the visitor report's order steps
 * and page views read as the order flow, not as data recovery.
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

function panel_shared_keys_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_panel_shared_keys_seo_is_seo()
{
	$map = pg_test_language_map();

	pg_assert_same('SEO', $map['SEO'] ?? null, 'SEO');
}

function test_panel_shared_keys_cheque_direction_has_its_own_key()
{
	$map = pg_test_language_map();

	pg_assert_same('Verilen', $map['Given out'] ?? null, 'Given out');
	// The cheque status and the privacy export's consent state keep "Given".
	pg_assert_same('Verildi', $map['Given'] ?? null, 'Given');

	$source = panel_shared_keys_source('erp_cheques.php');

	pg_assert_false(strpos($source, "lang('Given')") !== false, 'erp_cheques.php has no lang(\'Given\')');
	pg_assert_same(2, substr_count($source, "lang('Given out')"), 'erp_cheques.php direction label and filter');
}

function test_panel_shared_keys_uptime_note_names_uptime_monitors()
{
	$map = pg_test_language_map();
	$key = 'Uptime monitors can poll this address. It answers 200 while the site is up and its general job runs, and 503 during maintenance or when the general job has stopped:';

	pg_assert_true(isset($map[$key]), 'uptime note key exists');
	pg_assert_same(0, strpos($map[$key] ?? '', 'Çalışma süresi (uptime) izleyicileri '), 'uptime note prefix');
	pg_assert_same(':', substr($map[$key] ?? '', -1), 'uptime note keeps its colon');
}

function test_panel_shared_keys_visitor_report_order_steps()
{
	$map = pg_test_language_map();
	$expected = array(
		'Order Checked Out' => 'Ödemeye Geçildi',
		'Order Not Checked Out' => 'Ödemeye Geçilmedi',
		'Checked Out' => 'Ödemeye geçildi',
		'Not Checked Out' => 'Ödemeye geçilmedi',
		'Order Retrieved' => 'Sipariş Geri Çağrıldı',
		'Order Not Retrieved' => 'Sipariş Geri Çağrılmadı',
		'Retrieved' => 'Geri çağrıldı',
		'Not Retrieved' => 'Geri çağrılmadı',
		'Reorder/Retrieve Order Next Page' => 'Siparişi Yinele/Geri Çağır Sonraki Sayfası',
		'Retrieve' => 'Geri Çağır',
		'The order is complete. You may only retrieve incomplete orders.' => 'Sipariş tamamlanmış. Yalnızca tamamlanmamış siparişleri geri çağırabilirsiniz.',
		'Page Views' => 'Sayfa Görüntüleme',
	);

	foreach ($expected as $key => $value) {
		pg_assert_same($value, $map[$key] ?? null, $key);
	}
}
