<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the place of the Advanced Filters panel on the list screens that
 * have one: the panel is printed after the toolbar (#button_bar) and the
 * button that opens it, and before the list, so the Tab and reading order
 * reach the toggle before the panel's own close link and filter boxes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * The list screens with an Advanced Filters panel and their file names.
 */
function advanced_filters_order_test_screens()
{
	return array('view_orders.php', 'view_contacts.php', 'view_submitted_forms.php', 'view_commissions.php', 'view_log.php');
}

function test_advanced_filters_order_panel_after_toolbar_and_toggle()
{
	foreach (advanced_filters_order_test_screens() as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		$main = strpos($source, '<main id="content"');
		pg_assert_true($main !== false, $file . ': <main> is found');

		$page = substr($source, $main);
		$toolbar = strpos($page, 'id="button_bar"');
		$toggle = strpos($page, $file . '?advanced_filters=');
		$panel = strpos($page, '\' . $output_advanced_filters . \'');
		$list = strpos($page, '<table class="chart');

		pg_assert_true($toolbar !== false && $toggle !== false && $panel !== false && $list !== false, $file . ': toolbar, toggle, panel and list are found in <main>');
		pg_assert_true($toolbar < $panel, $file . ': the panel comes after the toolbar');
		pg_assert_true($toggle < $panel, $file . ': the panel comes after the button that opens it');
		pg_assert_true($panel < $list, $file . ': the panel comes before the list');
		pg_assert_same(1, substr_count($source, '$output_advanced_filters . \''), $file . ': the panel is printed once');
	}
}

function test_advanced_filters_order_site_log_not_before_main()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_log.php');
	pg_assert_same(false, strpos($source, '\'pre_main_html\' => $output_advanced_filters'), 'view_log.php does not pass the panel as pre_main_html');
}
