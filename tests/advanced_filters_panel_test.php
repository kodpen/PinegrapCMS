<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Advanced Filters panel's parts (includes/fn/advanced_filters.php):
 * the count of filters in use, the address of "Clear", the head with its
 * count pill and Close, the foot with Clear and Update, and the five list
 * screens drawing the panel as a head, a body that alone scrolls and a foot.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/advanced_filters.php');

function test_advanced_filters_count_only_values_off_their_empty_value()
{
	$empty = array('order_number' => '', 'payment_method' => 'any', 'date_type' => 'order_date');

	pg_assert_same(0, pg_advanced_filters_count(array(), $empty), 'nothing kept: no filter in use');
	pg_assert_same(0, pg_advanced_filters_count(array('order_number' => '  ', 'payment_method' => 'any', 'date_type' => 'order_date'), $empty), 'empty values do not count');
	pg_assert_same(1, pg_advanced_filters_count(array('order_number' => '1001'), $empty), 'a text filter counts');
	// "None" is the payment method '' and is a filter of its own.
	pg_assert_same(1, pg_advanced_filters_count(array('payment_method' => ''), $empty), 'payment method None counts');
	pg_assert_same(2, pg_advanced_filters_count(array('payment_method' => 'Offline Payment', 'date_type' => 'ship_date', 'status' => 'any'), $empty), 'only the panel\'s fields count');
	pg_assert_same(0, pg_advanced_filters_count(array('order_number' => array('x')), $empty), 'a non-string value is not counted');
}

function test_advanced_filters_clear_url_sends_every_field_empty()
{
	$url = pg_advanced_filters_clear_url('view_orders.php', array('order_number' => '', 'payment_method' => 'any'));

	pg_assert_same('view_orders.php?order_number=&payment_method=any&submit_data=Update', $url);

	$kept = pg_advanced_filters_clear_url('view_contacts.php', array('city' => ''), array('filter' => 'all my contacts'));
	pg_assert_same('view_contacts.php?filter=all%20my%20contacts&city=&submit_data=Update', $kept, 'a kept view goes first and is encoded');
}

function test_advanced_filters_head_count_pill_and_close()
{
	$head = pg_advanced_filters_head('view_orders.php?advanced_filters=false', 'Remove Advanced Filters', 0);

	pg_assert_contains('class="advanced-filter-head"', $head);
	pg_assert_contains('<i class="bi bi-funnel" aria-hidden="true"></i>', $head, 'the icon is silent');
	pg_assert_contains('href="view_orders.php?advanced_filters=false"', $head, 'Close turns the panel off');
	pg_assert_contains('aria-label="Remove Advanced Filters"', $head, 'Close is named');
	pg_assert_contains('class="pg-icon-button pg-icon-button-sm"', $head, 'Close is an icon button');
	pg_assert_same(false, strpos($head, 'pg-count'), 'no pill without a filter in use');

	$head = pg_advanced_filters_head('view_orders.php?advanced_filters=false', 'Remove Advanced Filters', 3);
	pg_assert_contains('<span aria-hidden="true">3</span>', $head, 'the count is shown');
	pg_assert_contains('class="pg-count"', $head, 'as the count pill');
	pg_assert_contains('visually-hidden', $head, 'and read as words');
}

function test_advanced_filters_foot_clear_then_update()
{
	$foot = pg_advanced_filters_foot('view_orders.php?order_number=&submit_data=Update');

	$clear = strpos($foot, 'pg-button-quiet');
	$update = strpos($foot, 'pg-button-fill');

	pg_assert_true($clear !== false && $update !== false && $clear < $update, 'Clear (quiet) comes before Update (fill)');
	pg_assert_contains('href="view_orders.php?order_number=&amp;submit_data=Update"', $foot, 'Clear\'s address is escaped');
	pg_assert_contains('type="submit" name="submit_data" value="Update"', $foot, 'Update submits as before');
	pg_assert_contains('<i class="bi bi-arrow-repeat" aria-hidden="true"></i>', $foot, 'the Update icon is silent');
}

function test_advanced_filters_screens_draw_head_scroll_and_foot()
{
	foreach (array('view_orders.php', 'view_contacts.php', 'view_submitted_forms.php', 'view_commissions.php', 'view_log.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		$panel = strpos($source, 'advanced-filter-bar');
		pg_assert_true($panel !== false, $file . ': the panel is found');

		$block = substr($source, $panel);
		$head = strpos($block, 'pg_advanced_filters_head(');
		$scroll = strpos($block, 'class="advanced-filter-scroll"');
		$foot = strpos($block, 'pg_advanced_filters_foot(');
		$end = strpos($block, '</form>');

		pg_assert_true($head !== false && $scroll !== false && $foot !== false && $end !== false, $file . ': head, scrolling body and foot are drawn');
		pg_assert_true($head < $scroll && $scroll < $foot && $foot < $end, $file . ': the foot is the form\'s last part, after the scrolling body');
		pg_assert_same(false, strpos(substr($block, 0, $end), 'position-sticky'), $file . ': no button floats over the fields');
		pg_assert_same(false, strpos(substr($block, 0, $end), 'btn-close'), $file . ': Close is the look\'s icon button');
		pg_assert_same(false, strpos(substr($block, 0, $end), 'class="form-label"'), $file . ': labels are the look\'s field labels');
	}
}

function test_advanced_filters_order_empty_values_cover_the_panel()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_orders.php');
	$start = strpos($source, '$output_advanced_filters =');
	$end = strpos($source, '</form>', $start);
	preg_match_all('/\bname="([a-z_0-9]+)"/', substr($source, $start, $end - $start), $names);

	$empty = pg_order_advanced_filters_empty(true, true, true);

	foreach (array_unique($names[1]) as $name) {
		if (preg_match('/^(start|stop)_(month|day|year)$/', $name)) {
			// The period is the bar's navigator's; Clear keeps it.
			continue;
		}
		pg_assert_true(array_key_exists($name, $empty), 'view_orders.php: Clear empties ' . $name);
	}

	pg_assert_same('any', $empty['payment_method'], 'payment method Any is its empty value');
	pg_assert_same('order_date', $empty['date_type'], 'date type Order Date is its empty value');
	pg_assert_same(false, array_key_exists('affiliate_code', pg_order_advanced_filters_empty(false, true, true)), 'no affiliate field without the program');
	pg_assert_same(false, array_key_exists('currency_code', pg_order_advanced_filters_empty(true, false, true)), 'no currency field without multi-currency');
	pg_assert_same(false, array_key_exists('date_type', pg_order_advanced_filters_empty(true, true, false)), 'no date type without shipping');
}
