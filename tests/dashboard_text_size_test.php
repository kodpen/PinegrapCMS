<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pin: dashboard widget cards print their inline text sizes in rem,
 * so names, counts and labels grow with the browser's text size like the
 * card, its grid track and its title do. A px size stays fixed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// No widget file prints a px font size.
function test_dashboard_text_size_widgets_print_no_px_font_size()
{
	$files = glob(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_*.php');

	pg_assert_true(count($files) > 0, 'widget files found');

	foreach ($files as $file) {
		$source = (string) file_get_contents($file);

		pg_assert_false((bool) preg_match('/font-size\s*:\s*[0-9.]+px/i', $source), basename($file) . ' prints a px font size');
	}
}

// The converted sizes keep their look at the 16 px default.
function test_dashboard_text_size_firewall_figures_in_rem()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_21.php');

	pg_assert_contains('font-size:1.0625rem', $source, 'headline figure 17px');
	pg_assert_contains('font-size:.5625rem', $source, 'event badge 9px');
}
