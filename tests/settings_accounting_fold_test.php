<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Accounting as folded rows: the twelve rows' order, names
 * and icons, each part's line of state and pill, the ERP card cut into six
 * cards by topic with Enable the ERP module above the rows, the cards a site
 * does not show drawn hidden after them, and the names that stay (card ids,
 * field names, the save, the anchors, the registry).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_ENTRY')) {
	define('PG_SETTINGS_ENTRY', true);
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/search.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/accounting_settings_fold.php');

function settings_accounting_fold_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The part of accounting.php from one card's id to the start of the next card.
function settings_accounting_fold_test_card($id)
{
	$source = settings_accounting_fold_test_source('includes/settings/accounting.php');
	$start = strpos($source, '<div id="' . $id . '" class="pg-set-card');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, 'class="pg-set-card', $start + 30);

	return substr($source, $start, (($end === false) ? strlen($source) : $end) - $start);
}

function settings_accounting_fold_test_names($html)
{
	// A field drawn by the pane's switch or radio rows is named in the call.
	preg_match_all('/\bname="([a-z0-9_]+)(?:\[\])?"|pg_accounting_settings_(?:switch|choice)\(\'([a-z0-9_]+)\'/', $html, $match);
	$names = array();

	foreach ($match[1] as $index => $name) {
		$names[] = ($name !== '') ? $name : $match[2][$index];
	}

	return array_values(array_unique($names));
}

function test_settings_accounting_fold_order_and_names()
{
	pg_assert_same(
		array('erp', 'erp-sales', 'erp-orders', 'erp-accounts', 'erp-currency', 'erp-notices', 'invoice', 'erp-rules', 'late-fee', 'efatura-threshold', 'stock-plan', 'erp-templates'),
		pg_accounting_fold_order(),
		'the ERP\'s six rows first, the module\'s other cards after'
	);

	$names = array();

	foreach (pg_accounting_fold_order() as $key) {
		$face = pg_accounting_fold_face($key);
		$names[] = $face['name'];
		pg_assert_true(strpos($face['icon'], 'bi-') === 0, $key . ': an icon');
		pg_assert_true(strpos($face['tone'], 'pg-tone-') === 0, $key . ': a tone');
	}

	pg_assert_same(
		array(lang('Documents'), lang('Sales'), lang('Web orders'), lang('Customer accounts'), lang('Foreign currency'), lang('Reminders and notices'),
			lang('E-Invoice'), lang('Accounting rules'), lang('Late payment fee'), lang('e-Fatura threshold'), lang('Stock planning'), lang('Document templates')),
		$names,
		'the rows\' names in sentence case'
	);
}

function test_settings_accounting_fold_documents_and_sales_lines()
{
	pg_assert_same(lang(array('string' => 'Series {var:1}', 'vars' => array('ABC'))) . ' · ' . lang('numbers by year') . ' · ' . lang(array('string' => 'due in {var:1} day{suffix:1}', 'vars' => 30, 'suffix' => array('s'))),
		pg_accounting_fold_documents('ABC', 'year', 30)['line'], 'series, numbers by year, due in 30 days');
	pg_assert_contains(lang('numbers in the GİB shape'), pg_accounting_fold_documents('PGF', 'gib', 0)['line'], 'the GİB shape');
	pg_assert_contains(lang('numbers never restart'), pg_accounting_fold_documents('PGF', 'continuous', 0)['line'], 'continuous numbers');
	pg_assert_contains(lang('due on the invoice date'), pg_accounting_fold_documents('PGF', 'gib', 0)['line'], '0 is due on the invoice date');
	pg_assert_contains(lang(array('string' => 'Series {var:1}', 'vars' => array('PGF'))), pg_accounting_fold_documents('', 'gib', 0)['line'], 'an empty series reads as PGF');
	pg_assert_same(null, pg_accounting_fold_documents('PGF', 'gib', 0)['pill'], 'no pill');

	pg_assert_same(lang('Till prices with tax') . ' · ' . lang('invoices change stock') . ' · Retail customer',
		pg_accounting_fold_sales('gross', true, 'Retail customer')['line'], 'till prices, stock and the walk-in account');
	pg_assert_same(lang('Till prices without tax') . ' · ' . lang('invoices leave stock alone') . ' · ' . lang('no walk-in account'),
		pg_accounting_fold_sales('net', false, '')['line'], 'without tax, stock alone, no walk-in account');
}

function test_settings_accounting_fold_orders_and_accounts_lines()
{
	pg_assert_same(lang('Invoiced from the order screen'), pg_accounting_fold_orders('off', false, '')['line'], 'from the order screen');
	pg_assert_same(lang('Invoiced once paid') . ' · ' . lang('e-mailed to the customer'), pg_accounting_fold_orders('order_paid', true, '')['line'], 'once paid, e-mailed');
	pg_assert_same(lang('Invoiced once shipped'), pg_accounting_fold_orders('order_shipped', false, 'none')['line'], 'once shipped');
	pg_assert_same(lang('Invoices e-mailed to the customer'), pg_accounting_fold_orders(null, true, '')['line'], 'before the automatic invoice\'s column');
	pg_assert_same(lang('Shipping not taxed'), pg_accounting_fold_orders(null, null, 'none')['line'], 'before both columns: the shipping tax');
	pg_assert_same(null, pg_accounting_fold_orders('off', true, '')['pill'], 'no pill');

	pg_assert_same(lang('Over the limit: warned'), pg_accounting_fold_accounts('warn', false, false)['line'], 'warned');
	pg_assert_same(lang('Over the limit: refused') . ' · ' . lang('account prices in the store') . ' · ' . lang('on account at checkout'),
		pg_accounting_fold_accounts('block', true, true)['line'], 'refused, with both switches on');
	pg_assert_same(lang('On account at checkout'), pg_accounting_fold_accounts(null, false, true)['line'], 'without the limit\'s column');
	pg_assert_same(lang('List prices for everyone'), pg_accounting_fold_accounts(null, false, null)['line'], 'nothing on');
}

function test_settings_accounting_fold_currency_and_notices_lines()
{
	$off = pg_accounting_fold_currency(false, 'TRY', array('USD'));
	pg_assert_same(lang(array('string' => 'Off: everything in {var:1}', 'vars' => array('TRY'))), $off['line'], 'off names the base currency');
	pg_assert_same(array(lang('Off'), 'pg-tone-gray'), $off['pill'], 'pill Off');

	$on = pg_accounting_fold_currency(true, 'TRY', array('USD', 'EUR', 'TRY', 'usd'));
	pg_assert_same('USD, EUR', $on['line'], 'the currencies allowed, the base and repeats left out');
	pg_assert_same(array(lang('On'), 'pg-tone-teal'), $on['pill'], 'pill On');
	pg_assert_same(lang(array('string' => 'No currency picked beside {var:1}', 'vars' => array('TRY'))), pg_accounting_fold_currency(true, 'TRY', array())['line'], 'on with none picked');

	$daily = pg_accounting_fold_notices(7, 'daily', 9, 2);
	pg_assert_same(lang(array('string' => 'Overdue after {var:1} day{suffix:1}, daily at {var:2}', 'vars' => array(7, '09:00'), 'suffix' => array('s')))
		. ' · ' . lang(array('string' => '{var:1} notice{suffix:1} on', 'vars' => 2, 'suffix' => array('s'))), $daily['line'], 'overdue after 7 days, daily at 09:00, two notices');
	pg_assert_same(array(lang('On'), 'pg-tone-teal'), $daily['pill'], 'reminders on');
	pg_assert_contains('14:00', pg_accounting_fold_notices(30, 'weekly', 14, null)['line'], 'weekly at the hour');

	$none = pg_accounting_fold_notices(0, 'daily', 9, 0);
	pg_assert_same(lang('No reminder') . ' · ' . lang('no notice on'), $none['line'], 'no reminder, no notice');
	pg_assert_same(array(lang('Off'), 'pg-tone-gray'), $none['pill'], 'reminders off');
	pg_assert_same(lang('No reminder'), pg_accounting_fold_notices(0, 'daily', 9, null)['line'], 'no notices before their columns');
}

function test_settings_accounting_fold_short_rows_lines()
{
	$none = pg_accounting_fold_edoc('', false);
	pg_assert_same(lang('No e-document provider'), $none['line'], 'no provider');
	pg_assert_false($none['attention'], 'no provider asks nothing');

	$waiting = pg_accounting_fold_edoc('Paraşüt', false);
	pg_assert_same(lang(array('string' => '{var:1} is not connected', 'vars' => array('Paraşüt'))), $waiting['line'], 'picked, not connected');
	pg_assert_same(array(lang('Not connected'), 'pg-tone-amber'), $waiting['pill'], 'its pill');
	pg_assert_true($waiting['attention'], 'asks for attention');
	pg_assert_same('invoice', pg_settings_fold_pick(array(array('key' => 'erp', 'attention' => false), array('key' => 'invoice', 'attention' => true))), 'the pane arrives with E-Invoice open');

	$ready = pg_accounting_fold_edoc('Paraşüt', true);
	pg_assert_same(array(lang('On'), 'pg-tone-teal'), $ready['pill'], 'connected is On');
	pg_assert_false($ready['attention'], 'connected asks nothing');

	pg_assert_same(lang('Withheld VAT taken off') . ' · ' . lang(array('string' => 'exemption code {var:1}', 'vars' => array('351'))), pg_accounting_fold_rules(true, true, '351')['line'], 'the rules');
	pg_assert_same(lang('Withheld VAT shown beside') . ' · ' . lang('no exemption code'), pg_accounting_fold_rules(true, false, '')['line'], 'beside, no code');
	pg_assert_same(lang('VAT taken back is set with the expense categories'), pg_accounting_fold_rules(false, true, '351')['line'], 'outside the Turkish features');

	pg_assert_same(lang(array('string' => '{var:1}% a month', 'vars' => array('2,5'))), pg_accounting_fold_late_fee(2.5, '2,5')['line'], 'the monthly rate');
	pg_assert_same(lang('No fee'), pg_accounting_fold_late_fee(0, '0')['line'], 'no fee');
	pg_assert_same(array(lang('Off'), 'pg-tone-gray'), pg_accounting_fold_late_fee(0, '0')['pill'], 'off');

	pg_assert_same(lang(array('string' => 'Internet sales {var:1} · all sales {var:2}', 'vars' => array('4.000.000 ₺', lang('off')))), pg_accounting_fold_threshold(400000000, 0, '4.000.000 ₺', '0 ₺')['line'], 'one warning off');
	pg_assert_same(lang('No warning'), pg_accounting_fold_threshold(0, 0, '0', '0')['line'], 'both off');

	pg_assert_same(lang(array('string' => 'Safety {var:1} days · cover {var:2} days · dead stock after {var:3} days', 'vars' => array(7, 30, 90))), pg_accounting_fold_stock_plan(7, 30, 90)['line'], 'the plan\'s days');

	pg_assert_same(lang('Every document prints the built-in template'), pg_accounting_fold_templates(0, 3)['line'], 'built-in');
	pg_assert_same(lang(array('string' => '{var:1} of {var:2} documents print a saved template', 'vars' => array(1, 3))), pg_accounting_fold_templates(1, 3)['line'], 'one saved');
}

function test_settings_accounting_fold_pane_is_rows()
{
	$pane = settings_accounting_fold_test_source('includes/settings/accounting.php');

	pg_assert_contains('$pg_settings_cards[] = $accounting_head . pg_settings_fold($accounting_rows) . $accounting_hidden;', $pane, 'the head, the rows, then the hidden cards');
	pg_assert_same(1, substr_count($pane, '$pg_settings_cards[]'), 'no card outside the rows');

	// Enable the ERP module stands above the rows, a pg-switch in the same form.
	$head = substr($pane, (int) strpos($pane, '$accounting_head = '), 1200);
	pg_assert_contains('class="pg-switch" value="1"\' . $erp_enabled_checked . \' id="erp_enabled" name="erp_enabled"', $head, 'the module switch at the head');
	pg_assert_true(strpos($pane, 'name="erp_enabled"') < strpos($pane, '<div id="pgset-erp" '), 'above the first row');

	// The cards a site does not show stay drawn, hidden, out of the rows.
	pg_assert_contains("\$accounting_hidden .= \$accounting_invoice_card;", $pane, 'E-Invoice hidden outside Turkey');
	pg_assert_contains("\$accounting_hidden .= \$accounting_threshold_card;", $pane, 'the threshold hidden outside Turkey');
}

function test_settings_accounting_fold_the_cut()
{
	$cut = array(
		'pgset-erp' => array('erp_default_series', 'erp_number_style', 'erp_tax_name', 'erp_tax2_name', 'erp_web_address', 'erp_default_due_days'),
		'pgset-erp-sales' => array('erp_walkin_account_id', 'local_sale_prices', 'erp_stock_documents'),
		'pgset-erp-orders' => array('erp_auto_invoice_on', 'erp_auto_invoice_min_total', 'erp_auto_invoice_tax_number_only', 'erp_auto_invoice_marketplace', 'erp_invoice_mail_auto', 'erp_invoice_mail_message', 'erp_shipping_tax'),
		'pgset-erp-accounts' => array('erp_credit_limit_mode', 'erp_storefront_account_prices', 'erp_open_account_payment'),
		'pgset-erp-currency' => array('erp_fx_enabled', 'erp_fx_auto_diff'),
		'pgset-erp-notices' => array('erp_overdue_notify_days', 'erp_overdue_notify_frequency', 'erp_overdue_notify_hour', 'erp_overdue_notify_panel', 'erp_overdue_notify_email', 'erp_overdue_notify_push', 'erp_overdue_notify_customer', 'erp_overdue_notify_recipients', 'erp_notify_collections', 'erp_notify_collection_min', 'erp_notify_low_stock', 'erp_notify_quote_expiry', 'erp_notify_quote_expiry_days', 'erp_quote_expiry_followup'),
	);
	$anchors = pg_settings_legacy_anchors();

	foreach ($cut as $id => $fields) {
		$card = settings_accounting_fold_test_card($id);
		pg_assert_true($card !== '', $id . ' drawn');
		pg_assert_same($fields, settings_accounting_fold_test_names($card), $id . ' holds its fields, in their order');

		if ($id === 'pgset-erp-currency') {
			pg_assert_contains('$output_erp_fx_currencies', $card, 'with the currencies allowed');
		}

		if ($id === 'pgset-erp') {
			continue;
		}

		foreach ($fields as $field) {
			pg_assert_same(array('accounting', $id), $anchors[$field] ?? null, 'an old link to ' . $field . ' lands on ' . $id);
		}
	}

	pg_assert_same(array('accounting', 'pgset-erp'), $anchors['pgset-erp'], 'an old ERP card link lands on the first row');
	pg_assert_same(array('accounting', 'pgset-erp'), $anchors['erp_enabled'], 'and the module switch\'s');
}

function test_settings_accounting_fold_registry_and_search()
{
	$source = settings_accounting_fold_test_source('includes/settings/registry.php');
	$start = strpos($source, "\n        'accounting' => array(");
	$accounting = substr($source, $start, strpos($source, "\n        ),\n", $start) - $start);
	$order = array();

	foreach (array('pgset-erp', 'pgset-erp-sales', 'pgset-erp-orders', 'pgset-erp-accounts', 'pgset-erp-currency', 'pgset-erp-notices', 'pgset-invoice', 'pgset-erp-rules', 'pgset-late-fee', 'pgset-efatura-threshold', 'pgset-stock-plan', 'pgset-erp-templates') as $id) {
		$at = strpos($accounting, "'" . $id . "' ");
		pg_assert_true($at !== false, $id . ' is a section');
		pg_assert_true(substr_count($accounting, "'" . $id . "' ") === 2, $id . ' has its keywords');
		$order[] = $at;
	}

	$sorted = $order;
	sort($sorted);
	pg_assert_same($sorted, $order, 'in the rows\' order');
	pg_assert_contains("'pgset-erp'       => lang('Documents')", $accounting, 'the first row\'s name');
	pg_assert_contains('credit limit', $accounting, 'the search finds the credit limit');
	pg_assert_contains('exchange rate', $accounting, 'and the exchange rate');

	// Inside the rows the search index reads each card's labels apart.
	$html = pg_settings_fold(array(
		array('key' => 'erp', 'name' => 'Documents', 'icon' => 'bi-file-earmark-text', 'tone' => '', 'line' => 'x', 'pill' => null, 'attention' => false,
			'html' => '<div id="pgset-erp" class="pg-set-card"><div class="card"><div class="card-body"><label for="a">Invoice series</label><input id="a"></div></div></div>'),
		array('key' => 'erp-sales', 'name' => 'Sales', 'icon' => 'bi-shop-window', 'tone' => '', 'line' => 'y', 'pill' => null, 'attention' => false,
			'html' => '<div id="pgset-erp-sales" class="pg-set-card"><div class="card"><div class="card-body"><label for="b">Walk-in sales account</label><input id="b"></div></div></div>'),
	));
	$labels = pg_settings_card_labels($html, array('pgset-erp', 'pgset-erp-sales'));

	pg_assert_same(array('Invoice series'), $labels['pgset-erp'], 'Documents\' labels, none of Sales');
	pg_assert_same(array('Walk-in sales account'), $labels['pgset-erp-sales'], 'Sales\' label');
}

function test_settings_accounting_fold_names_stay()
{
	$pane = settings_accounting_fold_test_source('includes/settings/accounting.php');

	foreach (array('pgset-erp', 'pgset-erp-sales', 'pgset-erp-orders', 'pgset-erp-accounts', 'pgset-erp-currency', 'pgset-erp-notices', 'pgset-erp-rules', 'pgset-late-fee', 'pgset-stock-plan', 'pgset-erp-templates') as $id) {
		pg_assert_contains('<div id="' . $id . '" class="pg-set-card">', $pane, $id . ' keeps its card inside its row');
	}

	pg_assert_false(strpos(settings_accounting_fold_test_source('includes/settings/accounting.save.php'), 'fold') !== false, 'the save knows nothing of rows');
	pg_assert_contains("pg_settings_link('accounting', 'pgset-erp-sales')", settings_accounting_fold_test_source('add_order.php'), 'the till\'s Sales settings opens Sales');
}
