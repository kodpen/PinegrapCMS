<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the comments a guest pins on a prototype opened by its share link
 * (includes/fn/prototype_comments.php, prototype_comment.php): each gate of
 * the guest's door, the limits, the text kept plain, the place bounded, an
 * answer tied to its thread, and the delete with a design.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_comments.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// The facts of a post that passes every gate, laid over the given ones.
function prototype_comments_test_facts($over = array())
{
	return array_merge(array(
		'method' => 'POST', 'same_origin' => true, 'token_ok' => true, 'state' => 'ok',
		'session' => true, 'password_ok' => true, 'team_ok' => true, 'allowed' => true, 'page_shown' => true,
	), $over);
}

function prototype_comments_test_link()
{
	return array('id' => 7, 'style_id' => 31, 'allow_comments' => 1);
}

// A first comment with every field, laid over the given ones.
function prototype_comments_test_input($over = array())
{
	return array_merge(array('page_id' => '120', 'node' => 'n_ab-12', 'x' => '12.5', 'y' => 99, 'name' => 'Ayşe', 'text' => 'Make this bigger.', 'parent' => ''), $over);
}

function test_prototype_comments_door_passes_a_good_post()
{
	pg_assert_same('', pg_proto_comment_door(prototype_comments_test_facts()));
}

function test_prototype_comments_door_refuses_each_gate()
{
	pg_assert_same('method', pg_proto_comment_door(prototype_comments_test_facts(array('method' => 'GET'))));
	pg_assert_same('origin', pg_proto_comment_door(prototype_comments_test_facts(array('same_origin' => false))), 'another origin');
	pg_assert_same('token', pg_proto_comment_door(prototype_comments_test_facts(array('token_ok' => false))), 'no form token');
	pg_assert_same('link', pg_proto_comment_door(prototype_comments_test_facts(array('state' => 'revoked'))));
	pg_assert_same('link', pg_proto_comment_door(prototype_comments_test_facts(array('state' => 'expired'))));
	pg_assert_same('link', pg_proto_comment_door(prototype_comments_test_facts(array('state' => 'gone'))));
	pg_assert_same('link', pg_proto_comment_door(prototype_comments_test_facts(array('state' => 'unknown'))));
	pg_assert_same('session', pg_proto_comment_door(prototype_comments_test_facts(array('session' => false))), 'another session');
	pg_assert_same('session', pg_proto_comment_door(prototype_comments_test_facts(array('password_ok' => false))), 'password cookie gone');
	pg_assert_same('session', pg_proto_comment_door(prototype_comments_test_facts(array('team_ok' => false))), 'signed out of a team link');
	pg_assert_same('comments_off', pg_proto_comment_door(prototype_comments_test_facts(array('allowed' => false))));
	pg_assert_same('page', pg_proto_comment_door(prototype_comments_test_facts(array('page_shown' => false))), 'a page the link does not show');
	pg_assert_same('method', pg_proto_comment_door(array()), 'no facts at all');
	pg_assert_same('origin', pg_proto_comment_door(array('method' => 'POST')), 'a fact not read counts as refused');
}

function test_prototype_comments_form_token_compared_strictly()
{
	pg_assert_true(pg_proto_comment_token_ok('abc123', 'abc123'));
	pg_assert_false(pg_proto_comment_token_ok('abc123', 'abc124'));
	pg_assert_false(pg_proto_comment_token_ok('', ''), 'a session without a token');
	pg_assert_false(pg_proto_comment_token_ok('abc123', array('abc123')), 'an array');
	pg_assert_false(pg_proto_comment_token_ok('abc123', null));
}

function test_prototype_comments_limits()
{
	$limits = pg_proto_comment_limits();

	pg_assert_same(30, $limits['burst']);
	pg_assert_same(600, $limits['burst_window']);
	pg_assert_same(200, $limits['open_per_link']);
	pg_assert_same('', pg_proto_comment_limit_refusal(false, 199));
	pg_assert_same('full', pg_proto_comment_limit_refusal(false, 200));
	pg_assert_same('rate', pg_proto_comment_limit_refusal(true, 0));
	pg_assert_same('rate', pg_proto_comment_limit_refusal(true, 500), 'the burst is told first');
	pg_assert_same(429, pg_proto_comment_error_status('rate'));
	pg_assert_same(409, pg_proto_comment_error_status('full'));
	pg_assert_same(403, pg_proto_comment_error_status('origin'));
	pg_assert_same(403, pg_proto_comment_error_status('session'));
	pg_assert_same(404, pg_proto_comment_error_status('page'));
}

function test_prototype_comments_check_gives_the_row()
{
	$check = pg_proto_comment_check(prototype_comments_test_input(), prototype_comments_test_link());

	pg_assert_true($check['ok']);
	pg_assert_same(array(
		'link_id' => 7, 'style_id' => 31, 'page_id' => 120, 'parent_id' => 0, 'author_name' => 'Ayşe', 'user_id' => 0,
		'body' => 'Make this bigger.', 'node_id' => 'n_ab-12', 'pos_x' => 1250, 'pos_y' => 9900,
	), $check['row']);
	pg_assert_false(isset($check['row']['ip']), 'no address kept');
}

function test_prototype_comments_name_and_text_bounds()
{
	$link = prototype_comments_test_link();

	pg_assert_same('name', pg_proto_comment_check(prototype_comments_test_input(array('name' => '   ')), $link)['error']);
	pg_assert_same('name', pg_proto_comment_check(prototype_comments_test_input(array('name' => str_repeat('ş', 61))), $link)['error']);
	pg_assert_true(pg_proto_comment_check(prototype_comments_test_input(array('name' => str_repeat('ş', 60))), $link)['ok']);
	pg_assert_same('name', pg_proto_comment_check(prototype_comments_test_input(array('name' => array('x'))), $link)['error']);
	pg_assert_same('text', pg_proto_comment_check(prototype_comments_test_input(array('text' => '')), $link)['error']);
	pg_assert_same('text', pg_proto_comment_check(prototype_comments_test_input(array('text' => str_repeat('ğ', 1001))), $link)['error']);
	pg_assert_true(pg_proto_comment_check(prototype_comments_test_input(array('text' => str_repeat('ğ', 1000))), $link)['ok']);
	pg_assert_same('text', pg_proto_comment_check(prototype_comments_test_input(array('text' => "bad \xC3\x28 utf-8")), $link)['error']);
}

function test_prototype_comments_text_kept_plain()
{
	$check = pg_proto_comment_check(prototype_comments_test_input(array(
		'name' => "Ali\n<b>Veli</b>\x07",
		'text' => "<script>alert(1)</script>\r\nline two\x00\x1B \u{202E}x",
	)), prototype_comments_test_link());

	pg_assert_true($check['ok']);
	pg_assert_same('Ali <b>Veli</b>', $check['row']['author_name'], 'one line, markup kept as typed');
	pg_assert_same("<script>alert(1)</script>\nline two x", $check['row']['body'], 'line break kept, controls out');
}

function test_prototype_comments_place_out_of_bounds_refused()
{
	$link = prototype_comments_test_link();

	foreach (array('-1', '100.01', '1e2', 'NaN', '', ' 5', array(5), 101, -0.5, INF, NAN) as $bad) {
		pg_assert_same('place', pg_proto_comment_check(prototype_comments_test_input(array('x' => $bad)), $link)['error'], 'x ' . var_export($bad, true));
		pg_assert_same('place', pg_proto_comment_check(prototype_comments_test_input(array('y' => $bad)), $link)['error'], 'y ' . var_export($bad, true));
	}

	pg_assert_same(0, pg_proto_comment_place('0'));
	pg_assert_same(10000, pg_proto_comment_place(100));
	pg_assert_same(3333, pg_proto_comment_place('33.333333'));
}

function test_prototype_comments_node_and_page_shapes()
{
	$link = prototype_comments_test_link();

	pg_assert_same('node', pg_proto_comment_check(prototype_comments_test_input(array('node' => 'a"b')), $link)['error']);
	pg_assert_same('node', pg_proto_comment_check(prototype_comments_test_input(array('node' => str_repeat('a', 65))), $link)['error']);
	pg_assert_same('node', pg_proto_comment_check(prototype_comments_test_input(array('node' => null)), $link)['error']);
	pg_assert_same('page', pg_proto_comment_check(prototype_comments_test_input(array('page_id' => '')), $link)['error']);
	pg_assert_same('page', pg_proto_comment_check(prototype_comments_test_input(array('page_id' => '1x')), $link)['error']);
	pg_assert_same('parent', pg_proto_comment_check(prototype_comments_test_input(array('parent' => '-3')), $link)['error']);
	pg_assert_same(0, pg_proto_comment_id(''));
	pg_assert_same(-1, pg_proto_comment_id(array(1)));
}

function test_prototype_comments_answer_takes_its_thread()
{
	$link = prototype_comments_test_link();
	$root = array('id' => 50, 'link_id' => 7, 'page_id' => 120, 'parent_id' => 0, 'resolved_at' => 0, 'node_id' => 'n1', 'pos_x' => 10, 'pos_y' => 20);
	$answer = prototype_comments_test_input(array('parent' => '50', 'node' => 'other', 'x' => 'junk'));

	$check = pg_proto_comment_check($answer, $link, $root);

	pg_assert_true($check['ok']);
	pg_assert_same(50, $check['row']['parent_id']);
	pg_assert_same('n1', $check['row']['node_id'], 'the thread\'s node, not the posted one');
	pg_assert_same(10, $check['row']['pos_x']);

	pg_assert_same('parent', pg_proto_comment_check($answer, $link, null)['error'], 'no such comment');
	pg_assert_same('parent', pg_proto_comment_check($answer, $link, array('link_id' => 8) + $root)['error'], 'another link');
	pg_assert_same('parent', pg_proto_comment_check($answer, $link, array('page_id' => 121) + $root)['error'], 'another page');
	pg_assert_same('parent', pg_proto_comment_check($answer, $link, array('parent_id' => 9) + $root)['error'], 'an answer itself');
	pg_assert_same('parent', pg_proto_comment_check($answer, $link, array('resolved_at' => 5) + $root)['error'], 'a resolved thread');
}

function test_prototype_comments_delete_with_design_sql()
{
	pg_assert_same("DELETE FROM prototype_comments WHERE style_id IN (4,9)", pg_proto_comments_delete_design_sql(array(4, '9', 0, -2, 4)));
	pg_assert_same("DELETE FROM prototype_comments WHERE style_id IN (12)", pg_proto_comments_delete_design_sql(12));
	pg_assert_same('', pg_proto_comments_delete_design_sql(array()));
	pg_assert_same('', pg_proto_comments_delete_design_sql(0));
}

function test_prototype_comments_every_design_delete_path_deletes_comments()
{
	$root = PG_FUNCTIONS_DIR;

	foreach (array('delete_styles.php', 'includes/explorer/recycle_pages_styles.php', 'includes/install_design_template.php', 'includes/fn/designer.php', 'includes/fn/legacy_bootstrap_trial.php') as $file) {
		$code = (string) file_get_contents($root . '/' . $file);

		pg_assert_same(substr_count($code, 'pg_proto_links_revoke_design('), substr_count($code, 'pg_proto_comments_delete_design('), $file);
	}
}

function test_prototype_comments_public_shape()
{
	$public = pg_proto_comment_public(array(
		'id' => '3', 'link_id' => '7', 'page_id' => '120', 'node_id' => 'n1', 'pos_x' => '1250', 'pos_y' => '9900',
		'parent_id' => '0', 'author_name' => 'Ayşe', 'user_id' => '0', 'body' => 'Hi', 'created_at' => '100', 'resolved_at' => '0', 'resolved_by' => '0',
	));

	pg_assert_same(12.5, $public['x']);
	pg_assert_same(99, (int) $public['y']);
	pg_assert_false($public['staff']);
	pg_assert_false(isset($public['resolved_by']));
}

function test_prototype_comments_stamp_changes_for_two_comments_in_one_second()
{
	$first = array('id' => 41, 'page_id' => 120, 'created_at' => 1900000000);
	$second = array('id' => 42, 'page_id' => 120, 'created_at' => 1900000000);

	pg_assert_same(41, pg_proto_comments_stamp_of_rows(array($first)));
	pg_assert_same(42, pg_proto_comments_stamp_of_rows(array($first, $second)), 'the same second, another stamp');
	pg_assert_same(0, pg_proto_comments_stamp_of_rows(array()));
	pg_assert_same("SELECT MAX(id) FROM prototype_comments WHERE page_id IN (120,121)", pg_proto_comments_page_stamp_sql(array(120, '121', 0)));
	pg_assert_same('', pg_proto_comments_page_stamp_sql(array()));
}

function test_prototype_comments_burst_counts_only_counted_addresses()
{
	$source = pg_test_function_source('pg_proto_comment_burst');

	pg_assert_contains('waf_ip_is_counted(', $source);
	pg_assert_true(strpos($source, 'waf_ip_is_counted(') < strpos($source, 'waf_rate_exceeded('), 'gated before it counts');
	pg_assert_false(waf_ip_is_counted('10.0.0.8'), 'a private network shares one address');
}
