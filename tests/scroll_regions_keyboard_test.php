<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Text-only boxes that scroll on their own (a capped height or width with
 * overflow) are named keyboard regions: Tab reaches them in every browser,
 * the arrow keys scroll them and a screen reader says what they hold. The
 * Audit Trail's JSON values, the Workspace MCP tool answer, the API
 * documentation's tables and Try it answer, and the Write with AI draft.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/audit_list.php');

function scroll_regions_keyboard_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// A JSON value is capped at 16rem; its box is named after the field and side.
function test_scroll_regions_audit_json_value_is_a_named_region()
{
	$html = pg_audit_list_changes_html(pg_audit_view_changes('{"settings":{"a":1}}', '{"settings":{"a":2}}'));

	pg_assert_contains('<pre class="small mb-0" tabindex="0" role="region" aria-label="' . h(lang(array('string' => '{var:1}, before', 'vars' => 'settings'))) . '"', $html);
	pg_assert_contains('aria-label="' . h(lang(array('string' => '{var:1}, after', 'vars' => 'settings'))) . '"', $html);

	// A plain value does not scroll and takes no Tab stop.
	$html = pg_audit_list_changes_html(pg_audit_view_changes('{"title":"old"}', '{"title":"new"}'));
	pg_assert_false(strpos($html, 'tabindex') !== false, 'a plain value has no tabindex');
}

function test_scroll_regions_workspace_mcp_answer_is_a_named_region()
{
	$source = scroll_regions_keyboard_source('workspace_mcp.php');

	pg_assert_contains('<pre class="small bg-1 border rounded-4 p-3 mb-0" tabindex="0" role="region" aria-label="\' . h(lang(\'Answer\')) . \'"', $source);
}

// The Try it answer is always a region; a table holder becomes one only
// while it scrolls sideways, rechecked when the window is resized.
function test_scroll_regions_api_documentation()
{
	$source = scroll_regions_keyboard_source('includes/api/console_view.php');

	pg_assert_contains(<<<'EXPECTED'
"<pre class=\'mt-3 d-none\' tabindex=\'0\' role=\'region\' aria-label=\'" + esc(T.response) + "\'></pre>"
EXPECTED
, $source);
	pg_assert_contains('response:  \' . json_encode(lang(\'Response\')', $source);
	pg_assert_contains('function markScrollers(root)', $source);
	pg_assert_contains('holder.scrollWidth > holder.clientWidth', $source);
	pg_assert_contains('holder.removeAttribute("tabindex")', $source);
	pg_assert_contains('window.addEventListener("resize"', $source);
	pg_assert_contains('markScrollers(root);', $source);
}

function test_scroll_regions_ai_write_draft()
{
	$source = scroll_regions_keyboard_source('assets/js/pg_ai_write.js');

	pg_assert_contains("box.tabIndex = 0;", $source);
	pg_assert_contains("box.setAttribute('role', 'region');", $source);
	pg_assert_contains("box.setAttribute('aria-label', onBrowser(state.settings) ? label('title_browser') : label('title'));", $source);
}
