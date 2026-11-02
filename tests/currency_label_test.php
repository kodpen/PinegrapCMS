<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how a stored currency is shown in the panel
 * (pg_currency_label() in includes/fn/ecommerce.php): the English names the
 * starter data stores go through lang(), a name the site owner wrote stays
 * as it is; the currency list writes its rate with the panel's separators.
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

// A name the starter data does not store is printed as written.
function test_currency_label_keeps_an_owner_written_name()
{
	pg_assert_same('Bitcoin', pg_currency_label('Bitcoin'));
	pg_assert_same('', pg_currency_label(''));
	pg_assert_same('british POUND sterling', pg_currency_label('british POUND sterling'));
}

// Every starter name is a lang() key and has Turkish wording.
function test_currency_label_starter_names_are_translated()
{
	$tr = pg_test_language_map('tr');

	foreach (array('US Dollar', 'British pound', 'Canadian dollar', 'Euro', 'Japanese yen', 'Australian dollar') as $name) {
		pg_assert_same(lang($name), pg_currency_label($name), $name . ' goes through lang()');
		pg_assert_true(isset($tr[$name]) && ($tr[$name] !== ''), $name . ' has a Turkish key');
	}
}

// The list and the dashboard card name a currency through the label, and
// the list writes the rate like the card does.
function test_currency_screens_use_the_label()
{
	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_currencies.php');
	$card = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_16.php');

	pg_assert_contains('h(pg_currency_label($currency[\'name\']))', $list, 'the list names by label');
	pg_assert_contains('pg_format_number((float) $currency[\'exchange_rate\'], 5)', $list, 'the list formats the rate');
	pg_assert_contains('h(pg_currency_label($currency[\'name\']))', $card, 'the card names by label');
}

// The ERP currency select (invoice, receipt, expense and account forms) and
// the order report's currency filter name a currency through the label too.
// Before, a Turkish panel showed "GBP - British pound" there while the
// settings screen said "GBP - İngiliz Sterlini".
function test_currency_erp_select_and_order_report_use_the_label()
{
	$fx = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/fx.php');
	$report = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_order_report.php');

	pg_assert_contains("' - ' . pg_currency_label(\$names[\$base])", $fx, 'the base currency names by label');
	pg_assert_contains("' - ' . pg_currency_label(\$names[\$code])", $fx, 'the allowed currencies name by label');
	pg_assert_contains("pg_currency_label(\$row['name']) . ' (' . \$row['code'] . ')'", $report, 'the report filter names by label');
}
