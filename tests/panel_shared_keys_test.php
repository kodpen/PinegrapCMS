<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Short shared keys read in the sense of each screen: SEO stays SEO, the
 * cheque direction filter has its own key beside the cheque status, the
 * uptime note names uptime monitors, the audit trail's action has its own
 * "created" key, and the visitor report's order steps
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

// The audit trail's action reads "Oluşturuldu" under its own key; "Created"
// stays "Oluşturulma", the column header of about 30 lists.
function test_panel_shared_keys_audit_action_has_its_own_key()
{
	$map = pg_test_language_map();

	pg_assert_same('Oluşturuldu', $map['Record created'] ?? null, 'Record created');
	pg_assert_same('Oluşturulma', $map['Created'] ?? null, 'Created');

	$source = panel_shared_keys_source('includes/fn/audit_view.php');

	pg_assert_true(strpos($source, "'create'  => lang('Record created')") !== false, 'audit action create label');
	pg_assert_false(strpos($source, "lang('Created')") !== false, 'audit_view.php has no lang(\'Created\')');
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

// The comments quote starter is named "Alıntılar" under its own key; "Quotes"
// stays the ERP quotations screen. The product rating line reads its count
// word from a visible key; "reviews" stays the design templates' address slug.
function test_panel_shared_keys_starter_labels_have_their_own_keys()
{
	$map = pg_test_language_map();

	pg_assert_same('Alıntılar', $map['Quotations'] ?? null, 'Quotations');
	pg_assert_same('Fiyat Teklifleri', $map['Quotes'] ?? null, 'Quotes');
	pg_assert_same('değerlendirme', $map['review(s)'] ?? null, 'review(s)');
	pg_assert_same('degerlendirmeler', $map['reviews'] ?? null, 'reviews');

	$source = panel_shared_keys_source('includes/fn/widgets_starters.php');

	pg_assert_true(strpos($source, "'label' => lang('Quotations')") !== false, 'quote starter label');
	pg_assert_true(strpos($source, "lang('review(s)')") !== false, 'rating count word');
	pg_assert_false(strpos($source, "lang('Quotes')") !== false, 'widgets_starters.php has no lang(\'Quotes\')');
	pg_assert_false(strpos($source, "lang('reviews')") !== false, 'widgets_starters.php has no lang(\'reviews\')');
}

// A revision's source is a noun under its own key, not the Edit and Restore
// button labels that sit beside Compare and Load.
function test_panel_shared_keys_revision_sources_are_nouns()
{
	$map = pg_test_language_map();

	pg_assert_same('Düzenleme', $map['Edited'] ?? null, 'Edited');
	pg_assert_same('Geri yükleme', $map['Restoration'] ?? null, 'Restoration');
	pg_assert_same('Düzenle', $map['Edit'] ?? null, 'Edit');
	pg_assert_same('Geri Yükle', $map['Restore'] ?? null, 'Restore');

	$source = panel_shared_keys_source('includes/fn/record_revisions.php');
	$start = (int) strpos($source, 'function pg_record_revision_source_label(');
	$body = substr($source, $start, (int) strpos($source, "\n}\n", $start) - $start);

	pg_assert_true(strpos($body, "lang('Edited')") !== false, 'edit source label');
	pg_assert_true(strpos($body, "lang('Restoration')") !== false, 'restore source label');
	pg_assert_false(strpos($body, "lang('Edit')") !== false, 'no Edit button key');
	pg_assert_false(strpos($body, "lang('Restore')") !== false, 'no Restore button key');
}
