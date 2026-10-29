<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the word beside a list row's colour: a disabled product in a
 * product group, a key code that does not work today and why, a shipping
 * arrival date outside its window, a gift card that has expired or has no
 * balance, and an unpaid expense past its due date. The colour alone was
 * the only sign of these states.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/product_group_form.php');

function list_status_words_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_list_status_word_markup()
{
	pg_assert_same('<div class="small fg-secondary">' . h(lang('Disabled')) . '</div>', pg_list_status_word(lang('Disabled')), 'a small muted line under the coloured text');
	pg_assert_contains('&lt;b&gt;', pg_list_status_word('<b>'), 'the word is escaped');
}

function test_key_code_cell_says_why_it_does_not_work()
{
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}

	$today = '2026-10-29';
	$key_code = array(
		'code' => 'SAVE<10>', 'enabled' => '1', 'expiration_date' => '0000-00-00',
		'offer_status' => 'enabled', 'offer_start_date' => '2026-01-01', 'offer_end_date' => '2026-12-31',
		'last_modified_timestamp' => time(), 'last_modified_username' => '',
	);

	$cells = pg_key_code_list_cells($key_code, $today);
	pg_assert_same(h('SAVE<10>'), $cells['code']['html'], 'a working code shows only the code, escaped');

	$key_code['offer_end_date'] = '2026-10-28';
	$cells = pg_key_code_list_cells($key_code, $today);
	pg_assert_contains(pg_list_status_word(lang('Offer not running')), $cells['code']['html'], 'an ended offer is named');

	$key_code['offer_status'] = 'disabled';
	$key_code['offer_end_date'] = '2026-12-31';
	$cells = pg_key_code_list_cells($key_code, $today);
	pg_assert_contains(pg_list_status_word(lang('Offer not running')), $cells['code']['html'], 'a disabled offer is named');

	$key_code['expiration_date'] = '2026-10-01';
	$cells = pg_key_code_list_cells($key_code, $today);
	pg_assert_contains(pg_list_status_word(lang('Expired')), $cells['code']['html'], 'the code\'s own expiry comes before the offer');

	$key_code['enabled'] = '0';
	$cells = pg_key_code_list_cells($key_code, $today);
	pg_assert_contains(pg_list_status_word(lang('Disabled')), $cells['code']['html'], 'a disabled code says Disabled');
	pg_assert_contains('status_disabled fg-danger', $cells['code']['class'], 'the colour stays');

	$src = list_status_words_source('includes/templates/view_key_codes.php');
	pg_assert_contains("<?=\$key_code['cells']['code']['html']?>", $src, 'the list prints the cell the quick-edit answer repaints');
}

function test_product_group_rows_name_a_disabled_product()
{
	$rows = pg_product_group_form_unselected_rows(array(
		array('id' => 1, 'name' => 'On', 'enabled' => 1, 'short_description' => 'a', 'image_name' => '', 'price' => 100),
		array('id' => 2, 'name' => 'Off', 'enabled' => 0, 'short_description' => 'b', 'image_name' => '', 'price' => 100),
	), false);

	pg_assert_same(1, substr_count($rows, pg_list_status_word(lang('Disabled'))), 'only the disabled product says Disabled');
	pg_assert_contains('Off' . pg_list_status_word(lang('Disabled')), $rows, 'the word sits under the name');

	foreach (array('edit_product_group.php', 'includes/panel/commerce.php') as $file) {
		pg_assert_contains("pg_list_status_word(lang('Disabled'))", list_status_words_source($file), $file . ' names a disabled product');
	}
}

function test_arrival_dates_and_gift_cards_name_their_state()
{
	$src = list_status_words_source('view_arrival_dates.php');
	pg_assert_contains("pg_list_status_word(lang('Inactive'))", $src, 'an arrival date outside its window says Inactive');

	$src = list_status_words_source('view_gift_cards.php');
	pg_assert_contains("pg_list_status_word(lang('Expired'))", $src, 'an expired gift card says Expired');
	pg_assert_contains("pg_list_status_word(lang('No balance'))", $src, 'a used-up gift card says No balance');
}

function test_overdue_expense_has_a_badge()
{
	foreach (array('erp_expenses.php', 'edit_erp_expense.php') as $file) {
		pg_assert_contains("(\$overdue ? ' <span class=\"badge theme-danger\">' . lang('Overdue') . '</span>' : '')", list_status_words_source($file), $file . ' says Overdue');
	}
}
