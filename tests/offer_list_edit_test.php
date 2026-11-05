<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the All Offers list quick edit (includes/fn/offer_list_edit.php):
 * every list value the offer editor changes is a field, the change goes
 * through the editor's own request and checks, and the answer redraws the
 * row's cells and filter data.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

foreach (array('BASE_CURRENCY_SYMBOL' => '$', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $offer_edit_test_name => $offer_edit_test_value) {
	if (!defined($offer_edit_test_name)) {
		define($offer_edit_test_name, $offer_edit_test_value);
	}
}

require_once(PG_FUNCTIONS_DIR . '/edit_offer_f.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/offer_list_edit.php');

function offer_list_edit_test_state(array $over = array())
{
	return array(
		'offer' => $over + array(
			'id' => 4, 'code' => 'SPRING', 'description' => 'Ten off', 'enabled' => 1, 'require_code' => 1,
			'period' => 'range', 'start_date' => '2026-03-01', 'end_date' => '2026-06-01',
			'only_apply_best_offer' => 0, 'scope' => 'order', 'multiple_recipients' => 0,
			'upsell' => array('enabled' => 0, 'message' => '', 'trigger_subtotal' => 0, 'trigger_quantity' => 0, 'button_label' => '', 'page_id' => 0)),
		'conditions' => array(array('type' => 'subtotal', 'amount' => 5000)),
		'actions' => array(array('id' => 9, 'type' => 'discount order', 'unit' => 'percent', 'value' => 10, 'target' => 'product', 'product_id' => 0, 'group_id' => 0, 'quantity' => 0, 'tiers' => array(), 'shipping_method_ids' => array())));
}

function offer_list_edit_test_entry(array $over = array())
{
	return $over + array(
		'id' => 4, 'code' => 'SPRING', 'description' => 'Ten <b>off</b>', 'status' => 'active', 'status_label' => lang('Active'),
		'enabled' => true, 'require_code' => 1, 'incomplete' => false, 'start_date' => '2026-03-01', 'end_date' => PG_OFFER_OPEN_END_DATE,
		'modified' => 'now', 'modified_by' => '<i>ayse</i>');
}

function test_offer_quick_edit_fields_are_the_editor_list_values()
{
	$columns = pg_offer_edit_columns();
	pg_assert_same(array('code', 'description', 'require_code', 'enabled', 'start_date', 'end_date'), array_keys($columns));
	pg_assert_same(50, $columns['code']['max'], 'the editor\'s code length');
	pg_assert_true(!empty($columns['code']['required']), 'a code is required, as in the editor');
	pg_assert_same(255, $columns['description']['max'], 'the editor\'s message length');

	$cells = pg_offer_list_cells(offer_list_edit_test_entry());
	$text = array_diff(array_keys($cells), array_keys($columns));
	pg_assert_same(array('modified'), array_values($text), 'only the last change is a drawn cell that stays text');

	$tpl = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/view_offers.php');
	foreach (array_keys($cells) as $name) {
		pg_assert_contains("<?=\$cell('" . $name . "')?>", $tpl, 'the list draws ' . $name . ' from pg_offer_list_cells()');
	}
}

function test_offer_quick_request_changes_one_value_and_keeps_the_rest()
{
	$state = offer_list_edit_test_state();
	$request = pg_offer_quick_request($state, 'code', 'SUMMER', '2026-04-01');
	pg_assert_same('SUMMER', $request['offer']['code']);
	pg_assert_same($state['conditions'], $request['conditions'], 'conditions go back as loaded');
	pg_assert_same($state['actions'], $request['actions'], 'results go back as loaded, ids kept');
	pg_assert_same('range', $request['offer']['period']);

	pg_assert_same(0, pg_offer_quick_request($state, 'enabled', '0', '2026-04-01')['offer']['enabled']);
	pg_assert_same(0, pg_offer_quick_request($state, 'require_code', '0', '2026-04-01')['offer']['require_code']);
}

function test_offer_quick_request_dates_follow_the_editor_periods()
{
	$today = '2026-04-01';
	$ranged = offer_list_edit_test_state();

	$cleared = pg_offer_quick_request($ranged, 'end_date', '', $today);
	pg_assert_same('open', $cleared['offer']['period'], 'no end date on a started offer is open-ended');
	pg_assert_same(PG_OFFER_OPEN_END_DATE, $cleared['offer']['end_date']);

	$later = pg_offer_quick_request(offer_list_edit_test_state(array('start_date' => '2026-05-01')), 'end_date', '', $today);
	pg_assert_same('range', $later['offer']['period'], 'a later start keeps its date up to the open end');
	pg_assert_same('2026-05-01', $later['offer']['start_date']);
	pg_assert_same(PG_OFFER_OPEN_END_DATE, $later['offer']['end_date']);

	$open = offer_list_edit_test_state(array('period' => 'open', 'end_date' => PG_OFFER_OPEN_END_DATE));
	pg_assert_same('open', pg_offer_quick_request($open, 'start_date', '2026-01-01', $today)['offer']['period']);
	$future = pg_offer_quick_request($open, 'start_date', '2026-09-01', $today)['offer'];
	pg_assert_same(array('range', '2026-09-01', PG_OFFER_OPEN_END_DATE), array($future['period'], $future['start_date'], $future['end_date']), 'a later start is not moved back to today');

	pg_assert_same('range', pg_offer_quick_request($open, 'end_date', '2026-12-31', $today)['offer']['period']);
}

function test_offer_quick_error_names_the_field_first()
{
	pg_assert_same('bad dates', pg_offer_quick_error(array('actions.0.value' => 'x', 'offer.dates' => 'bad dates'), 'end_date'));
	pg_assert_same('bad code', pg_offer_quick_error(array('offer.code' => 'bad code'), 'code'));
	pg_assert_same(lang(array('string' => 'The offer cannot be saved as it is: {var:1} Open it to fix this first.', 'vars' => array('x'))), pg_offer_quick_error(array('actions.0.value' => 'x'), 'description'));
	pg_assert_same('', pg_offer_quick_error(array(), 'code'));
}

function test_offer_quick_answer_redraws_the_row()
{
	$answer = pg_offer_quick_answer(offer_list_edit_test_entry(array('enabled' => false, 'status' => 'disabled', 'status_label' => lang('Disabled'), 'require_code' => 0, 'incomplete' => true)), 'enabled');
	pg_assert_true($answer['ok']);
	pg_assert_same('0', $answer['value']);
	pg_assert_contains(lang('Incomplete'), $answer['html']);
	pg_assert_same(array('status' => 'disabled', 'how' => 'auto', 'incomplete' => '1', 'enabled' => 0), $answer['row'], 'the chips\' filter data');
	pg_assert_true(!isset($answer['cells']['enabled']), 'the cell itself is not among the others');
	pg_assert_contains('Ten &lt;b&gt;off&lt;/b&gt;', $answer['cells']['description']['html'], 'the message is escaped');
	pg_assert_contains('&lt;i&gt;ayse&lt;/i&gt;', $answer['cells']['modified']['html'], 'the user name is escaped');
	pg_assert_same(lang('Open-ended'), $answer['cells']['end_date']['html']);
	pg_assert_same(PG_OFFER_OPEN_END_DATE, $answer['cells']['end_date']['sort']);

	$end = pg_offer_quick_answer(offer_list_edit_test_entry(array('end_date' => '2026-06-01')), 'end_date');
	pg_assert_same('1/6/2026', $end['value'], 'the site\'s date format');
	pg_assert_same('', pg_offer_quick_value(offer_list_edit_test_entry(), 'end_date'), 'an open-ended offer opens with no end date');
}

function test_offer_quick_edit_answers_before_the_screen()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_offers.php');
	pg_assert_true(strpos($src, 'pg_list_edit_request(pg_offer_edit_columns()') < strpos($src, 'validate_ecommerce_access($user);'), 'a refusal is JSON, not the access page');
	pg_assert_contains("return (\$user['role'] < 3) || !empty(\$user['manage_ecommerce']);", $src, 'the editor\'s rights');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	pg_assert_contains("new window.CustomEvent('pg:list-edit-saved'", $js, 'a saved quick edit is announced to the screen');
}
