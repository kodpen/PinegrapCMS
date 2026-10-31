<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that what a screen tells the operator stays on the page at every
 * width: the Find Results match count in the toolbar, and the log screens'
 * filters each marked with what they filter.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function screen_labels_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The header hides its description below 1024 px: the match count is drawn
// in the content's toolbar instead.
function test_find_results_summary_sits_in_the_toolbar()
{
	$source = screen_labels_source('find_and_replace.php');

	pg_assert_false(strpos($source, "'heading_description' => \$summary") !== false, 'find_and_replace.php: the summary is not the heading description');
	pg_assert_contains("'grow' => '<span class=\"fg-secondary\" role=\"status\">' . h(\$summary)", $source, 'find_and_replace.php: the summary is in the toolbar grow slot');
}

// Each filter of the two log screens carries a visible icon label, and the
// "every value" choice says what it covers.
function test_log_filters_say_what_they_filter()
{
	$screens = array(
		'view_waf_log.php' => array('for="range"', 'for="category"', 'for="search"'),
		'view_performance_log.php' => array('for="perf_log_search"', 'for="perf_log_range"', 'for="perf_log_area"'),
	);

	foreach ($screens as $file => $fors) {
		$source = screen_labels_source($file);
		foreach ($fors as $for) {
			pg_assert_true((bool) preg_match('/<label (class="input-group-text" ' . preg_quote($for, '/') . '|' . preg_quote($for, '/') . ' class="input-group-text")/', $source), $file . ': ' . $for . ' has a visible label');
		}
	}

	pg_assert_contains("''         => lang('All categories')", screen_labels_source('includes/fn/waf_log_list.php'), 'firewall log: the first category choice names categories');
	pg_assert_contains("''         => lang('All areas')", screen_labels_source('view_performance_log.php'), 'performance log: the first area choice names areas');
}
