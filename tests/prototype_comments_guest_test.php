<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the guest's side of prototype comments (prototype_comment.php,
 * includes/fn/prototype_comments.php, assets/js/pg_proto_comments.js, the
 * viewer prototype.php): what a guest who writes a comment meets.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_links.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_comments.php');
require_once(__DIR__ . '/helpers/source_reader.php');

function prototype_comments_guest_test_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_proto_comments.js');
}

// A guest who reads a page longer than PHP's idle session lifetime (1440
// seconds by default) before sending lost the session the door checks: the
// comment was refused and the typed text went with the reload it asked for.
function test_prototype_comments_guest_viewer_keeps_its_session_alive()
{
	$every = pg_proto_comment_ping_seconds();

	pg_assert_true(($every > 0) && ($every < 1440), 'pinged well inside the idle lifetime');
	pg_assert_contains("'ping' => pg_proto_comment_ping_seconds()", pg_test_function_source('pg_proto_viewer_comments'));

	$js = prototype_comments_guest_test_js();
	pg_assert_contains("body.append('ping', '1')", $js);
	pg_assert_contains('cfg.ping', $js);
}

function test_prototype_comments_guest_ping_writes_nothing()
{
	pg_assert_true(pg_proto_comment_is_ping(array('ping' => '1')));
	pg_assert_false(pg_proto_comment_is_ping(array()));
	pg_assert_false(pg_proto_comment_is_ping(array('ping' => array('1'))));
	pg_assert_false(pg_proto_comment_is_ping(array('ping' => '0')));

	// Answered after the door's gates, before the fields, the burst and the
	// write: a ping is never counted as a comment.
	$door = (string) file_get_contents(PG_FUNCTIONS_DIR . '/prototype_comment.php');
	$ping = strpos($door, 'pg_proto_comment_is_ping($_POST)');
	pg_assert_true($ping !== false, 'the door answers a ping');
	pg_assert_true($ping > strpos($door, 'pg_proto_comment_refuse($pg_pc_refusal)'), 'after the gates');
	pg_assert_true($ping < strpos($door, 'pg_proto_comment_check('), 'before the fields');
	pg_assert_true($ping < strpos($door, 'pg_proto_comment_burst()'), 'before the burst');
}

// Runs `$code` in Node with window.PGProtoComments loaded as C; its `out`
// comes back decoded. Null when Node cannot run.
function prototype_comments_guest_test_node($code)
{
	$out = array();
	$status = 1;

	if (!function_exists('exec')) {
		return null;
	}

	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgpcg');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, "var window = {};\n" . prototype_comments_guest_test_js() . "\nvar C = window.PGProtoComments, out = null;\n" . $code . "\nconsole.log(JSON.stringify(out));");
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// In comment mode every click on the page opens a new box: a guest who
// clicked the page (or a pin) while writing lost the comment typed so far.
function test_prototype_comments_guest_typed_text_is_not_dropped_by_another_click()
{
	$out = prototype_comments_guest_test_node('out = [C.unsent("a"), C.unsent("  \n "), C.unsent(""), C.unsent(null)];');

	if ($out !== null) {
		pg_assert_same(array(true, false, false, false), $out);
	}

	$js = prototype_comments_guest_test_js();
	$handler = substr($js, strpos($js, "if (kind === 'place')"), 400);

	$place = strpos($handler, 'holding()');
	$pin = strpos($handler, "kind === 'pin'");
	pg_assert_true(($place !== false) && ($place < strpos($handler, 'showNew(')), 'a new spot waits for the typed text');
	pg_assert_true(($pin !== false) && (strpos($handler, 'holding()', $pin) !== false) && (strpos($handler, 'holding()', $pin) < strpos($handler, 'showThread(')), 'a pin waits too');
	pg_assert_contains("'held' => lang('Send or close this comment first.')", pg_test_function_source('pg_proto_viewer_comments'));
}

// The box's fields are .form-control-sm in a .875rem box: under 16px, so
// iOS zoomed the whole viewer in when a guest tapped Your name or Comment.
function test_prototype_comments_guest_phone_fields_are_16px()
{
	$css = pg_proto_shell_comment_css();
	$phone = substr($css, strpos($css, '@media (max-width: 575.98px)'));

	pg_assert_true(strpos($css, '@media (max-width: 575.98px)') !== false, 'a phone rule');
	pg_assert_contains('.pg-proto-box .form-control { font-size: 16px; }', $phone);
}
