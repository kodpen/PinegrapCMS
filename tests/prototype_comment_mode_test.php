<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the viewer's comment mode on a prototype opened by its share link
 * (prototype.php, includes/fn/prototype.php, assets/js/pg_proto_comments.js):
 * only a link with "Allow comments" has its frame drawn with node marks and
 * the comment script, the link's "Allow comments" in the plan, the list and
 * the API, and a message from the frame taken only as a place or a pin.
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

// Runs `$code` in Node with window.PGProtoComments loaded as C; its `out`
// comes back decoded. Null when Node cannot run.
function prototype_comment_mode_test_node($code)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$script = "var window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_proto_comments.js') . "\n"
		. "var C = window.PGProtoComments, out = null;\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgpc');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function test_prototype_comment_mode_frame_marks_nodes_only_for_a_link_with_comments()
{
	pg_assert_false(pg_proto_frame_comment_mode(null, true), 'the staff viewer: no marks');
	pg_assert_false(pg_proto_frame_comment_mode(array('id' => 3), true), 'a link without the flag');
	pg_assert_false(pg_proto_frame_comment_mode(array('id' => 3, 'allow_comments' => 0), true), 'a link with the flag off');
	pg_assert_false(pg_proto_frame_comment_mode(array('id' => 3, 'allow_comments' => '0'), true), 'the flag off as read from the database');
	pg_assert_false(pg_proto_frame_comment_mode(array('id' => 3, 'allow_comments' => 1), false), 'a site before the step');
	pg_assert_true(pg_proto_frame_comment_mode(array('id' => 3, 'allow_comments' => '1'), true));
}

function test_prototype_comment_mode_frame_without_the_flag_gets_no_comment_script()
{
	$page = '<html><head></head><body><p data-x="1">x</p></body></html>';
	$plain = pg_proto_frame_inject($page, array());

	pg_assert_false(strpos($plain, 'pg-proto-comment') !== false);
	pg_assert_false(strpos($plain, '"place"') !== false);
	pg_assert_false(strpos($plain, 'data-pg-node') !== false);

	$on = pg_proto_frame_inject($page, array('comments' => true));

	pg_assert_contains('"place"', $on);
	pg_assert_contains('pg-proto-pin', $on);
}

// The frame draws its marks only in the comment branch: the audit render is
// switched on there alone and always switched off again.
function test_prototype_comment_mode_frame_turns_marks_on_only_in_the_comment_branch()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/prototype.php');

	pg_assert_same(1, substr_count($source, 'pg_designer_audit_render(true)'));
	$on = strpos($source, 'pg_designer_audit_render(true)');
	$gate = strpos($source, 'if ($pg_proto_comments)');
	pg_assert_true(($gate !== false) && ($gate < $on), 'the marks are switched on under the comment flag');
	pg_assert_contains('pg_designer_audit_render(false)', substr($source, $on));
	pg_assert_contains('pg_proto_frame_comment_mode(', $source);
}

function test_prototype_comment_mode_link_plan_takes_allow_comments()
{
	$base = array('audience' => 'team', 'start_page_id' => 0, 'duration' => 0, 'password' => '');

	$plan = pg_proto_link_plan($base, array(5), array(3600), 1000);
	pg_assert_true($plan['ok']);
	pg_assert_same(0, $plan['link']['allow_comments'], 'off unless asked');

	foreach (array(true, 1, '1', 'true') as $on) {
		$plan = pg_proto_link_plan($base + array('allow_comments' => $on), array(5), array(3600), 1000);
		pg_assert_same(1, $plan['link']['allow_comments'], var_export($on, true));
	}

	foreach (array(false, 0, '0', 'false', '', null) as $off) {
		$plan = pg_proto_link_plan($base + array('allow_comments' => $off), array(5), array(3600), 1000);
		pg_assert_same(0, $plan['link']['allow_comments'], var_export($off, true));
	}

	foreach (array('yes', 2, array(1)) as $bad) {
		$plan = pg_proto_link_plan($base + array('allow_comments' => $bad), array(5), array(3600), 1000);
		pg_assert_false($plan['ok'], var_export($bad, true));
		pg_assert_same('allow_comments', $plan['field']);
	}
}

function test_prototype_comment_mode_link_summary_says_whether_it_takes_comments()
{
	$row = array('id' => 1, 'style_id' => 2, 'start_page_id' => 0, 'audience' => 'anyone', 'token_hint' => 'abcdef', 'password_hash' => '',
		'expires_at' => 5000, 'created_by' => 1, 'created_at' => 10, 'revoked_at' => 0, 'uses' => 0, 'last_used_at' => 0);

	pg_assert_false(pg_proto_link_summary($row, 100)['comments'], 'a row from before the step');
	pg_assert_false(pg_proto_link_summary($row + array('allow_comments' => '0'), 100)['comments']);
	pg_assert_true(pg_proto_link_summary($row + array('allow_comments' => '1'), 100)['comments']);
}

function test_prototype_comment_mode_api_route_and_schema_carry_allow_comments()
{
	$schema = file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
	pg_assert_contains("array('name' => 'allow_comments', 'in' => 'body', 'type' => 'bool'", $schema);

	$design = file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/design.php');
	pg_assert_contains("'allow_comments'  => 'boolean'", $design);
}

// A message from the frame is a place and nothing else: the page, the link,
// the name and the text never come from it.
function test_prototype_comment_mode_frame_message_is_taken_only_as_a_place()
{
	$out = prototype_comment_mode_test_node('out = [
		C.place({ pgPrototype: "place", node: "n_ab-12", x: 12.346, y: 99.999, vx: 40, vy: 300, page: 99, l: 5, name: "X", text: "Y", parent: 3 }),
		C.place({ pgPrototype: "place", node: "n 1", x: 1, y: 1 }),
		C.place({ pgPrototype: "place", node: "", x: 1, y: 1 }),
		C.place({ pgPrototype: "place", node: "' . str_repeat('a', 65) . '", x: 1, y: 1 }),
		C.place({ pgPrototype: "place", node: "n1", x: -1, y: 1 }),
		C.place({ pgPrototype: "place", node: "n1", x: 1, y: 100.5 }),
		C.place({ pgPrototype: "place", node: "n1", x: "12", y: 1 }),
		C.place({ pgPrototype: "place", node: "n1", x: NaN, y: 1 }),
		C.place({ pgPrototype: "pin", node: "n1", x: 1, y: 1 }),
		C.place(null),
		C.place({ pgPrototype: "place", node: "n1", x: 0, y: 100, vx: "a", vy: Infinity })
	];');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('node' => 'n_ab-12', 'x' => 12.35, 'y' => 100, 'vx' => 40, 'vy' => 300), $out[0]);

	for ($i = 1; $i <= 9; $i++) {
		pg_assert_same(null, $out[$i], 'refused #' . $i);
	}

	pg_assert_same(array('node' => 'n1', 'x' => 0, 'y' => 100, 'vx' => 0, 'vy' => 0), $out[10]);
}

function test_prototype_comment_mode_pin_and_missing_messages_name_only_known_threads()
{
	$out = prototype_comment_mode_test_node('var known = { 4: true, 9: true };
		out = [
			C.pinId({ pgPrototype: "pin", id: 4 }, known),
			C.pinId({ pgPrototype: "pin", id: 5 }, known),
			C.pinId({ pgPrototype: "pin", id: "4" }, known),
			C.missing({ pgPrototype: "missing", ids: [9, 9, 12, "4", 4] }, known),
			C.missing({ pgPrototype: "missing", ids: "4" }, known)
		];');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(4, null, null, array(4, 9), array()), $out);
}

function test_prototype_comment_mode_threads_are_numbered_with_their_answers()
{
	$out = prototype_comment_mode_test_node('out = C.threads([
		{ id: 3, parent: 0, node: "a", x: 1, y: 2, name: "A", text: "one", created_at: 10, resolved_at: 0 },
		{ id: 5, parent: 0, node: "b", x: 3, y: 4, name: "B", text: "two", created_at: 11, resolved_at: 50 },
		{ id: 6, parent: 3, node: "a", x: 1, y: 2, name: "C", text: "answer", created_at: 12, resolved_at: 0, staff: true },
		{ id: 7, parent: 99, node: "z", x: 1, y: 2, name: "D", text: "lost", created_at: 13, resolved_at: 0 }
	]).map(function (t) { return [t.id, t.n, t.node, t.resolved, t.items.map(function (c) { return c.id; })]; });');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(array(3, 1, 'a', false, array(3, 6)), array(5, 2, 'b', true, array(5))), $out);
}
