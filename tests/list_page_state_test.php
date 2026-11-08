<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests Load More in the Ctrl+K search (backend.src.js): it keeps the
 * keyboard's place and survives a failed page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function list_page_state_script()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
}

function test_command_palette_load_more_keeps_focus()
{
	$js = list_page_state_script();

	pg_assert_contains('<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span class="visually-hidden">\' + t(\'Loading...\') + \'</span>', $js, 'the next page spinner is heard');
	pg_assert_contains('<div class="srch-load-more text-center py-3 fg-secondary" role="status">', $js, 'the next page spinner is a status');
	pg_assert_contains("if (\$oldMore.is(':focus')) {\n                \$oldMore.prevAll('button').first().trigger('focus');", $js, 'the result above Load More holds the focus while the page loads');
	pg_assert_contains('var $first = appendResults(resp.results);', $js, 'appendResults() returns the first new result');
	pg_assert_contains("if (offset > 0) {\n                            \$first.trigger('focus');", $js, 'focus moves to the first new result after Load More');
	pg_assert_contains("\$results.append(\$more);\n                        if (offset > 0 && !(resp.results && resp.results.length)) {\n                            \$more.trigger('focus');", $js, 'a page with no results keeps focus on Load More');
}

function test_command_palette_load_more_failure_recovers()
{
	$js = list_page_state_script();

	pg_assert_contains('function loadMoreFailed() {', $js, 'a later page that fails has its own path');
	pg_assert_contains('currentOffset -= 20;', $js, 'the offset goes back so the same page can be asked again');
	pg_assert_contains("if (offset > 0 && !(resp && resp.status === 'success')) {\n                        loadMoreFailed();\n                        return;", $js, 'a non-success answer at offset > 0 reaches the failure path');
	pg_assert_contains("if (offset > 0) { loadMoreFailed(); } else {", $js, 'a network failure at offset > 0 reaches the failure path');
}
