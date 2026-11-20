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
