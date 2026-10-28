<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the ERP status badges and the receivables age bar use colour
 * names Bootstrap 6 draws: erp_quote_statuses(), erp_purchase_order_statuses(),
 * the linked invoice statuses on edit_erp_purchase_order.php and the 90+ day
 * bucket on erp_dashboard.php. Bootstrap 6 has no "dark" theme, so a
 * "badge theme-dark" was bare text.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/quotes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/purchase_orders.php');

function erp_status_tones_bs6_themes()
{
	return array('primary', 'secondary', 'success', 'danger', 'warning', 'info', 'inverse', 'accent', 'contrast', 'muted', 'subtle');
}

function erp_status_tones_assert_themes(array $statuses, $label)
{
	foreach ($statuses as $key => $status) {
		pg_assert_true(in_array($status[1], erp_status_tones_bs6_themes(), true), $label . ' ' . $key . ': ' . $status[1]);
	}
}

function test_erp_status_tones_quotes()
{
	erp_status_tones_assert_themes(erp_quote_statuses(), 'quote');
	pg_assert_same('inverse', erp_quote_statuses()['cancelled'][1]);
}

function test_erp_status_tones_purchase_orders()
{
	erp_status_tones_assert_themes(erp_purchase_order_statuses(), 'purchase order');
	pg_assert_same('inverse', erp_purchase_order_statuses()['cancelled'][1]);
}

// The linked invoice list on the purchase order page keeps its own array.
function test_erp_status_tones_purchase_order_invoices()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_purchase_order.php');

	pg_assert_true((bool) preg_match('/\$invoice_statuses = array\((.*?)\n    \);/s', $source, $m), 'invoice status array');
	pg_assert_true((bool) preg_match_all("/=> array\\(lang\\('[^']+'\\), '([a-z]+)'\\)/", $m[1], $tones), 'invoice status tones');
	pg_assert_same(5, count($tones[1]));
	foreach ($tones[1] as $tone) {
		pg_assert_true(in_array($tone, erp_status_tones_bs6_themes(), true), 'invoice status: ' . $tone);
	}
}

// bg-black is #000 on the dark theme's card, so the 90+ day share vanished.
function test_erp_status_tones_receivables_bucket()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_dashboard.php');

	pg_assert_contains("'d90p' => 'bg-inverse',", $source);
	pg_assert_true(strpos($source, 'bg-black') === false, 'erp_dashboard.php: bg-black');
}
