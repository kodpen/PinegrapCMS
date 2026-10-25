<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for when a render draws the edit pencils of system widget records at
 * all: only for a signed-in user, never in a page drawn for the search index
 * or the SEO score, and never in a page drawn as the body of an e-mail.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A signed-in user's own page view draws the pencils; a visitor's does not.
function test_widget_edit_chip_gate_signed_in()
{
	pg_assert_true(pg_sw_edit_chips_rule(true, false, false), 'signed in, page view');
	pg_assert_false(pg_sw_edit_chips_rule(false, false, false), 'visitor');
}

// A page drawn for the search index or the SEO score (the designer's canvas
// and Preview among them) draws none, even inside a signed-in request.
function test_widget_edit_chip_gate_background_render()
{
	pg_assert_false(pg_sw_edit_chips_rule(true, true, false), 'search index or SEO render');
}

// A page drawn as an e-mail body (a campaign, a form's confirmation or
// notification, an order receipt) draws none: the e-mail goes to other
// people, even when a signed-in editor's request builds it.
function test_widget_edit_chip_gate_email()
{
	pg_assert_false(pg_sw_edit_chips_rule(true, false, true), 'e-mail body');
	pg_assert_false(pg_sw_edit_chips_rule(true, true, true), 'e-mail body in a background render');
	pg_assert_false(pg_sw_edit_chips_rule(false, false, true), 'e-mail body for a visitor');
}

// pg_sw_edit_chips_on() takes the e-mail flag from the render context that
// get_page_content() sets, and leaves the decision to the rule.
function test_widget_edit_chip_gate_reads_email_context()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	$start = strpos($source, 'function pg_sw_edit_chips_on(');
	pg_assert_true($start !== false, 'pg_sw_edit_chips_on() found');

	$body = substr($source, $start, strpos($source, "\n}\n", $start) - $start);
	pg_assert_contains('pg_sw_render_context()', $body, 'reads the render context');
	pg_assert_contains("['email']", $body, 'reads the e-mail flag');
	pg_assert_contains('pg_sw_edit_chips_rule(', $body, 'asks the rule');
}

// get_page_content() keeps the e-mail flag in the render context for the
// whole render, not only around the widget pass: the comment pencil it draws
// after that pass asks pg_sw_edit_chips_on() as well. The flag is set before
// anything is drawn and the caller's context comes back only after the last
// pencil, before the function returns.
function test_widget_edit_chip_gate_email_context_spans_render()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');
	$start = strpos($source, 'function get_page_content(');
	pg_assert_true($start !== false, 'get_page_content() found');

	$body = substr($source, $start);
	$return = strrpos($body, 'return $content;');
	pg_assert_true($return !== false, 'the final return found');
	$body = substr($body, 0, $return);

	$set = strpos($body, "pg_sw_render_context(array('email' => (bool)\$email))");
	pg_assert_true($set !== false, 'sets the e-mail flag for the whole render');

	$widget_pass = strpos($body, '_expand_system_widgets(');
	$first_gate = strpos($body, 'pg_sw_edit_chips_on()');
	$last_gate = strrpos($body, 'pg_sw_edit_chips_on()');
	pg_assert_true($widget_pass !== false && $first_gate !== false, 'widget pass and comment pencil found');
	pg_assert_true($set !== false && $set < $widget_pass && $set < $first_gate, 'set before the widget pass and the comment pencil');

	$restore = strrpos($body, 'pg_sw_render_context($pg_render_prev_ctx)');
	pg_assert_true($restore !== false && $restore > $last_gate, 'restored after the last pencil, before the return');
}
