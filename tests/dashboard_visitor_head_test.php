<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard Visitor summaries: on a narrow card the legend drops below the
 * figures instead of squeezing their column, and the figure lines keep to
 * one line.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The declarations of the first rule whose selector is exactly $selector.
function dashboard_visitor_head_rule($selector)
{
	$css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css'));

	if (preg_match('/(?:^|[}\s])' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $css, $match)) {
		return $match[1];
	}

	return '';
}

function test_dashboard_visitor_head_wraps()
{
	pg_assert_contains('flex-wrap: wrap', dashboard_visitor_head_rule('.pg-vs-head'), 'the legend wraps below the figures when both do not fit');
}

function test_dashboard_visitor_figures_keep_one_line()
{
	pg_assert_contains('white-space: nowrap', dashboard_visitor_head_rule('.pg-vs-big'), 'the figure and its unit stay on one line');
	pg_assert_contains('white-space: nowrap', dashboard_visitor_head_rule('.pg-vs-sub'), 'the all-of-yesterday line stays on one line');
}
