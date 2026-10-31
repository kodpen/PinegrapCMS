<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Accounting category of Site Settings: the ERP and E-Invoice cards left
 * Ecommerce for it, with the fields they post and the columns their save
 * writes unchanged, and the old links to the two cards land on it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

function settings_accounting_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// One category's entry in pg_settings_categories(), cut out of the source:
// the list asks the database, so it is read rather than called.
function settings_accounting_test_category($key)
{
	$source = settings_accounting_test_source('includes/settings/registry.php');
	$start = strpos($source, "\n        '" . $key . "' => array(");

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n        ),\n", $start);

	return substr($source, $start, $end - $start);
}

// The field names a card block posts.
function settings_accounting_test_names($source)
{
	preg_match_all('/\bname="([a-z0-9_]+)(?:\[\])?"/', $source, $match);
	$names = array_values(array_unique($match[1]));
	sort($names);

	return $names;
}

function test_settings_accounting_category_follows_ecommerce()
{
	$source = settings_accounting_test_source('includes/settings/registry.php');
	$commerce = strpos($source, "\n        'commerce' => array(");
	$accounting = strpos($source, "\n        'accounting' => array(");
	$api = strpos($source, "\n        'api' => array(");

	pg_assert_true($accounting !== false, 'an accounting category');
	pg_assert_true(($commerce < $accounting) && ($accounting < $api), 'read after Ecommerce, before API');
}

function test_settings_accounting_category_holds_the_erp_and_invoice_cards()
{
	$accounting = settings_accounting_test_category('accounting');
	$commerce = settings_accounting_test_category('commerce');

	pg_assert_contains("'label' => lang('Accounting')", $accounting, 'named as in the main menu');
	pg_assert_contains("'pgset-erp'       => lang('ERP')", $accounting, 'the ERP card');
	pg_assert_contains("'pgset-invoice'   => lang('E-Invoice')", $accounting, 'the E-Invoice card');
	pg_assert_contains("'erp_fx_currencies' => 1", $accounting, 'its list field goes with the ERP card');
	pg_assert_true(strpos($accounting, "'pgset-erp'") < strpos($accounting, "'pgset-invoice'"), 'ERP first');

	pg_assert_same(false, strpos($commerce, 'pgset-erp'), 'no ERP card in Ecommerce');
	pg_assert_same(false, strpos($commerce, 'pgset-invoice'), 'no E-Invoice card in Ecommerce');
	pg_assert_same(false, strpos($commerce, 'erp_fx_currencies'), 'no ERP list field in Ecommerce');
}

function test_settings_accounting_old_links_land_on_the_cards()
{
	$anchors = pg_settings_legacy_anchors();

	pg_assert_same(array('accounting', 'pgset-erp'), $anchors['pgset-erp'], 'settings_commerce.php#pgset-erp');
	pg_assert_same(array('accounting', 'pgset-invoice'), $anchors['pgset-invoice'], 'settings_commerce.php#pgset-invoice');
	pg_assert_same(array('accounting', 'pgset-invoice'), $anchors['pgsub-invoice'], 'settings.php#pgsub-invoice');
}

function test_settings_accounting_cards_moved_out_of_ecommerce()
{
	$commerce = settings_accounting_test_source('includes/settings/commerce.php');
	$accounting = settings_accounting_test_source('includes/settings/accounting.php');

	pg_assert_same(false, strpos($commerce, 'id="pgset-erp"'), 'no ERP card drawn by Ecommerce');
	pg_assert_same(false, strpos($commerce, 'id="pgset-invoice"'), 'no E-Invoice card drawn by Ecommerce');
	pg_assert_contains('<div id="pgset-erp" class="pg-set-card">', $accounting, 'the ERP card');
	pg_assert_contains('<div id="pgset-invoice" class="pg-set-card\' . (pg_edoc_settings_shown() ? \'\' : \' d-none\') . \'">', $accounting, 'the E-Invoice card, hidden the same way');
	pg_assert_contains("if (!defined('PG_SETTINGS_ENTRY')) {", $accounting, 'the gate constant');
	pg_assert_contains('Pinegrap - Enterprise Website Platform', $accounting, 'the header block');

	// The fields the moved cards post: none left in Ecommerce, all in Accounting.
	$moved = settings_accounting_test_names($accounting);

	foreach (array('erp_enabled', 'erp_seller_vkn', 'erp_seller_tax_office', 'erp_web_address', 'erp_default_series', 'erp_overdue_notify_days', 'erp_auto_invoice_on', 'enable_parasut', 'parasut_client_id', 'parasut_client_secret', 'parasut_auth_code', 'erp_edoc_autosend', 'local_sale_prices') as $field) {
		pg_assert_true(in_array($field, $moved, true), $field . ' drawn by Accounting');
	}

	// The provider choice and the currency list are drawn by prep.php.
	foreach (array('$output_erp_edoc_options', '$output_erp_fx_currencies') as $drawn) {
		pg_assert_contains($drawn, $accounting, $drawn . ' printed by Accounting');
		pg_assert_same(false, strpos($commerce, $drawn), $drawn . ' no longer printed by Ecommerce');
	}

	pg_assert_same(array(), array_values(array_intersect($moved, settings_accounting_test_names($commerce))), 'no field drawn by both');
}

function test_settings_accounting_save_writes_what_the_cards_post()
{
	$commerce = settings_accounting_test_source('includes/settings/commerce.save.php');
	$accounting = settings_accounting_test_source('includes/settings/accounting.save.php');

	foreach (array('erp_enabled', 'erp_seller_vkn', 'erp_seller_tax_office', 'erp_web_address', 'erp_default_series', 'erp_fx_enabled', 'erp_overdue_notify_days', 'erp_auto_invoice_on', 'erp_edoc_autosend', 'local_sale_prices', 'enable_parasut', 'parasut_credentials_enc', 'parasut_company_id') as $column) {
		pg_assert_contains($column . ' = ', $accounting, $column . ' written by Accounting');
		pg_assert_same(false, strpos($commerce, $column . ' = '), $column . ' no longer written by Ecommerce');
	}

	pg_assert_contains('function pg_parasut_credentials_for_save()', $accounting, 'the Paraşüt secrets kept as before');
	pg_assert_same(false, strpos($commerce, 'pg_parasut_credentials_for_save'), 'not in Ecommerce');
	pg_assert_contains('erp_edoc_activate($erp_edoc_picked);', $accounting, 'the e-document provider picked here');
	pg_assert_contains('parasut_connect_with_code(', $accounting, 'a pasted Paraşüt code exchanged here');
	pg_assert_same(false, strpos($commerce, 'parasut_connect_with_code('), 'not in Ecommerce');
	pg_assert_contains('last_modified_user_id', $accounting, 'the save stamps who changed it');
	pg_assert_contains("if (!defined('PG_SETTINGS_ENTRY')) {", $accounting, 'the gate constant');
}

function test_settings_accounting_has_a_page()
{
	$page = settings_accounting_test_source('settings_accounting.php');

	pg_assert_contains("define('PG_SETTINGS_KEY', 'accounting');", $page, 'the page path of the category');
	pg_assert_contains("include('includes/settings/screen.php');", $page, 'drawn by the shared screen');
}

function test_settings_accounting_links_name_the_new_place()
{
	foreach (array('erp_settings.php', 'erp_inbox.php', 'erp_account_sync.php', 'view_parasut_inbox.php', 'add_order.php', 'includes/fn/auth.php') as $file) {
		$source = settings_accounting_test_source($file);
		pg_assert_same(false, strpos($source, "('commerce', 'pgset-erp')"), $file . ': no ERP link to Ecommerce');
		pg_assert_same(false, strpos($source, "('commerce', 'pgset-invoice')"), $file . ': no E-Invoice link to Ecommerce');
	}

	$erp_settings = settings_accounting_test_source('erp_settings.php');
	pg_assert_same(false, strpos($erp_settings, 'commerce settings'), 'the pointer card no longer names the commerce settings');
	pg_assert_same(false, strpos($erp_settings, "lang('Commerce')"), 'nor its place');
}

// ── The ERP Settings screen's cards in the pane ──

if (!defined('PG_ERP_ENTRY')) {
	define('PG_ERP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/fx.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/rules.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/late_fee.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/settings_cards.php');

function test_settings_accounting_lists_the_erp_settings_cards()
{
	$accounting = settings_accounting_test_category('accounting');
	$order = array();

	foreach (array('pgset-erp', 'pgset-invoice', 'pgset-erp-rules', 'pgset-late-fee', 'pgset-efatura-threshold', 'pgset-erp-templates') as $section) {
		$at = strpos($accounting, "'" . $section . "'");
		pg_assert_true($at !== false, $section . ' listed');
		$order[] = $at;
	}

	$sorted = $order;
	sort($sorted);
	pg_assert_same($sorted, $order, 'in reading order');
	pg_assert_contains("unset(\$categories['accounting']['sections']['pgset-efatura-threshold']", settings_accounting_test_source('includes/settings/registry.php'), 'the threshold left out of the list where the E-Invoice card is');
}

function test_settings_accounting_pane_draws_the_module_cards()
{
	$pane = settings_accounting_test_source('includes/settings/accounting.php');

	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');", $pane, 'the ERP module loaded');

	foreach (array('pgset-erp-rules' => 'erp_settings_rules_fields()', 'pgset-late-fee' => 'erp_settings_late_fee_fields()', 'pgset-efatura-threshold' => 'erp_settings_efatura_threshold_fields()', 'pgset-erp-templates' => 'erp_settings_templates_status(') as $id => $call) {
		pg_assert_contains('id="' . $id . '"', $pane, $id . ' drawn');
		pg_assert_contains($call, $pane, $id . ' from the module');
	}

	pg_assert_contains("' . (erp_settings_efatura_threshold_shown() ? '' : ' d-none') . '", $pane, 'the threshold drawn hidden outside Turkey');
	pg_assert_same(false, strpos($pane, 'name="template"'), 'the template editor stays on the screen');
}

function test_settings_accounting_pane_saves_through_the_module_after_its_checks()
{
	$save = settings_accounting_test_source('includes/settings/accounting.save.php');
	$refusals = strpos($save, 'erp_settings_cards_refusals($_POST, $erp_settings_cards)');
	$stop = strpos($save, 'if ($liveform->check_form_errors()) {');
	$update = strpos($save, 'db("UPDATE config');
	$cards = strpos($save, 'erp_settings_cards_save($_POST, $erp_settings_cards)');

	pg_assert_true(($refusals !== false) && ($refusals < $stop) && ($stop < $update), 'a refused value stops the save before any write');
	pg_assert_true(($cards !== false) && ($cards > $update), 'the cards saved with the pane');
	pg_assert_contains("isset(\$_POST['late_fee_rate'], \$_POST['late_fee_tax_rate'])", $save, 'a card whose fields did not come is left alone');
}

function test_settings_accounting_screen_draws_the_same_functions()
{
	$screen = settings_accounting_test_source('erp_settings.php');

	pg_assert_contains("validate_erp_access(\$user, 'settings')", $screen, 'the right stays');
	pg_assert_contains('$documents = erp_settings_documents();', $screen, 'one list of documents');
	pg_assert_contains('erp_settings_cards_save($_POST, array($card))', $screen, 'saved like the pane');

	foreach (array('erp_settings_rules_fields()', 'erp_settings_late_fee_fields()', 'erp_settings_efatura_threshold_fields()') as $call) {
		pg_assert_contains($call, $screen, $call);
	}

	foreach (array('erp_rules_save(', 'erp_late_fee_settings_save(', 'erp_efatura_threshold_save(', 'name="late_fee_rate"', 'name="vat_exemption_code"', 'name="erp_efatura_web_threshold"') as $gone) {
		pg_assert_same(false, strpos($screen, $gone), $gone . ' not written twice');
	}

	foreach (array('id="erp_rules"', 'id="erp_late_fee"', 'id="erp_efatura_threshold"') as $anchor) {
		pg_assert_contains($anchor, $screen, $anchor . ' kept for the links that land on it');
	}

	pg_assert_same(false, strpos($screen, '#settings/accounting'), 'no redirect while the template editor is only here');
}

function test_settings_accounting_card_refusals()
{
	pg_assert_same(array(), erp_settings_cards_refusals(array('vat_exemption_code' => '351', 'late_fee_rate' => '1,5', 'late_fee_tax_rate' => '', 'erp_efatura_web_threshold' => '500000', 'erp_efatura_general_threshold' => ''), array('rules', 'late_fee', 'efatura_threshold')), 'good values pass');
	pg_assert_same(array(), erp_settings_cards_refusals(array('vat_exemption_code' => 'x', 'late_fee_rate' => '999'), array()), 'a card not posted is not checked');

	$refused = erp_settings_cards_refusals(array('vat_exemption_code' => '35', 'late_fee_rate' => (string) (ERP_LATE_FEE_MAX_RATE + 1), 'late_fee_tax_rate' => '101', 'erp_efatura_web_threshold' => '500.000', 'erp_efatura_general_threshold' => '1000000001'), array('rules', 'late_fee', 'efatura_threshold'));

	pg_assert_same(array('vat_exemption_code', 'late_fee_rate', 'late_fee_tax_rate', 'erp_efatura_web_threshold', 'erp_efatura_general_threshold'), array_keys($refused), 'each bad value marked');
	pg_assert_same(lang('The exemption code is three digits, as the tax authority lists it (for example 351).'), $refused['vat_exemption_code'], 'the save\'s own sentence');
	pg_assert_same(array(), erp_settings_cards_refusals(array('late_fee_rate' => array('999'), 'late_fee_tax_rate' => ''), array('late_fee')), 'a list is read as empty, as the save reads it');
}
