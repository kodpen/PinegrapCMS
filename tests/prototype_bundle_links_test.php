<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a release bundle's preview shared by a prototype link
 * (prototype_links.bundle_id, includes/fn/prototype_links.php, the Bundles
 * screen's Share the preview): what a bundle's link is made with, which
 * frames a link opens, the frame address signed for a bundle's link, the
 * schema step, and the places that end a bundle's link.
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

function prototype_bundle_links_test_durations()
{
	return array(3600, 86400, 604800, 2592000, 7776000);
}

function test_prototype_bundle_links_bundle_id_of_a_row()
{
	pg_assert_same(5, pg_proto_link_bundle_id(array('id' => 1, 'style_id' => 0, 'bundle_id' => '5')));
	pg_assert_same(0, pg_proto_link_bundle_id(array('id' => 1, 'style_id' => 7, 'bundle_id' => '0')));
	// A row read before the step that adds the column is a design's link.
	pg_assert_same(0, pg_proto_link_bundle_id(array('id' => 1, 'style_id' => 7)));
	pg_assert_same(0, pg_proto_link_bundle_id(null));
}

function test_prototype_bundle_links_plan_starts_at_the_first_draft_without_comments()
{
	$plan = pg_proto_link_bundle_plan(array('audience' => 'anyone', 'duration' => 604800, 'password' => '', 'start_page_id' => 32, 'allow_comments' => 1),
		9, array(31, 32), prototype_bundle_links_test_durations(), 1000);

	pg_assert_true($plan['ok']);
	pg_assert_same(9, $plan['link']['bundle_id']);
	pg_assert_same(0, $plan['link']['start_page_id'], 'the drafts come and go: always the first');
	pg_assert_same(0, $plan['link']['allow_comments'], 'comments belong to a design');
	pg_assert_same(1000 + 604800, $plan['link']['expires_at']);
}

function test_prototype_bundle_links_plan_keeps_the_rules_of_a_design_link()
{
	$durations = prototype_bundle_links_test_durations();

	$plan = pg_proto_link_bundle_plan(array('audience' => 'anyone', 'duration' => 0), 9, array(31), $durations, 1000);
	pg_assert_same('duration', $plan['error'], 'a link for anyone runs out');

	$plan = pg_proto_link_bundle_plan(array('audience' => 'team', 'duration' => 0, 'password' => 'secret-word'), 9, array(31), $durations, 1000);
	pg_assert_true($plan['ok'], 'a team link may run for good');
	pg_assert_same(0, $plan['link']['expires_at']);
	pg_assert_same('secret-word', $plan['link']['password']);

	$plan = pg_proto_link_bundle_plan(array('audience' => 'team', 'password' => 'short'), 9, array(31), $durations, 1000);
	pg_assert_same('password', $plan['error']);

	$plan = pg_proto_link_bundle_plan(array('audience' => 'everyone'), 9, array(31), $durations, 1000);
	pg_assert_same('audience', $plan['error']);
}

function test_prototype_bundle_links_plan_needs_a_waiting_draft_and_a_bundle()
{
	$durations = prototype_bundle_links_test_durations();

	$plan = pg_proto_link_bundle_plan(array('audience' => 'team'), 9, array(), $durations, 1000);
	pg_assert_same('no_drafts', $plan['error'], 'a published or empty bundle has nothing to share');

	$plan = pg_proto_link_bundle_plan(array('audience' => 'team'), 0, array(31), $durations, 1000);
	pg_assert_same('no_drafts', $plan['error']);
	pg_assert_same(null, $plan['link']);
}

function test_prototype_bundle_links_frame_owner()
{
	$design = array('id' => 4, 'style_id' => 7, 'bundle_id' => 0);
	$bundle = array('id' => 5, 'style_id' => 0, 'bundle_id' => 9);

	pg_assert_true(pg_proto_link_frame_owner($design, 7, 0));
	pg_assert_false(pg_proto_link_frame_owner($design, 8, 0), 'another design');
	pg_assert_false(pg_proto_link_frame_owner($design, 7, 9), 'a design link opens no bundle frame');

	// A bundle's drafts may belong to several designs: the frame's design is
	// the drawn draft's own.
	pg_assert_true(pg_proto_link_frame_owner($bundle, 7, 9));
	pg_assert_true(pg_proto_link_frame_owner($bundle, 8, 9));
	pg_assert_false(pg_proto_link_frame_owner($bundle, 7, 10), 'another bundle');
	pg_assert_false(pg_proto_link_frame_owner($bundle, 7, 0), 'a bundle link opens no design frame');

	// A design link of a design id 0 never matches a frame.
	pg_assert_false(pg_proto_link_frame_owner(array('id' => 6, 'style_id' => 0), 0, 0));
}

function test_prototype_bundle_links_frame_signature_carries_the_bundle()
{
	$now = 1900000000;
	$key = 'site-key';
	$expires = $now + pg_proto_frame_ttl();

	// A design link's signature is the one it always was.
	pg_assert_same(pg_proto_link_frame_sign(7, 31, 5, $expires, $key), pg_proto_link_frame_sign(7, 31, 5, $expires, $key, '', '', 0));

	$signature = pg_proto_link_frame_sign(7, 31, 5, $expires, $key, '', '', 9);
	pg_assert_same(1, preg_match('/^[0-9a-f]{64}$/', $signature));
	pg_assert_false($signature === pg_proto_link_frame_sign(7, 31, 5, $expires, $key), 'the bundle is part of the signature');

	$query = pg_proto_link_frame_query(7, 31, 5, $expires, $signature, '', '', '', 9);
	pg_assert_contains('&k=9', $query);
	parse_str($query, $source);

	$parsed = pg_proto_link_frame_parse($source);
	pg_assert_same(9, $parsed['k']);
	pg_assert_same('ok', pg_proto_link_frame_state($parsed, $key, $now));

	// Without its bundle, or with another, the address is no longer genuine.
	$without = $source;
	unset($without['k']);
	pg_assert_same('invalid', pg_proto_link_frame_state(pg_proto_link_frame_parse($without), $key, $now));

	$other = $source;
	$other['k'] = '10';
	pg_assert_same('invalid', pg_proto_link_frame_state(pg_proto_link_frame_parse($other), $key, $now));

	// A design link's address reads no bundle.
	parse_str(pg_proto_link_frame_query(7, 31, 5, $expires, pg_proto_link_frame_sign(7, 31, 5, $expires, $key)), $plain);
	pg_assert_same(0, pg_proto_link_frame_parse($plain)['k']);

	$bad = $source;
	$bad['k'] = '09';
	pg_assert_same(null, pg_proto_link_frame_parse($bad), 'k is a plain positive whole number');
}

function test_prototype_bundle_links_schema_step()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	// The table's own step creates the column and its key where it has not run.
	$start = strpos($source, 'function upgrade_2026_11_0_prototype_links()');
	$create = substr($source, $start, strpos($source, ') ENGINE', $start) - $start);
	pg_assert_contains('bundle_id              INT UNSIGNED NOT NULL DEFAULT 0', $create);
	pg_assert_contains('KEY idx_bundle (bundle_id)', $create);

	// Where it ran, a step of its own adds both with the idempotent helpers.
	pg_assert_contains('upgrade_2026_11_0_prototype_link_bundles();', $source);
	$start = strpos($source, 'function upgrade_2026_11_0_prototype_link_bundles()');
	pg_assert_true($start !== false);
	$step = substr($source, $start, 600);
	pg_assert_contains("install_add_column('prototype_links', 'bundle_id', \"INT UNSIGNED NOT NULL DEFAULT 0\")", $step);
	pg_assert_contains("install_add_index('prototype_links', 'idx_bundle', \"INDEX idx_bundle (bundle_id)\")", $step);
	pg_assert_false(strpos($step, 'ALTER TABLE') !== false);
}

function test_prototype_bundle_links_wired_into_the_viewer_screen_and_delete()
{
	$viewer = file_get_contents(PG_FUNCTIONS_DIR . '/prototype.php');
	pg_assert_contains('pg_proto_link_frame_owner($pg_proto_link, $parsed[\'s\'], $pg_proto_bundle_id)', $viewer);
	pg_assert_contains('pg_proto_bundle_pages($pg_proto_link_bundle)', $viewer);

	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/page_bundles.php');
	pg_assert_contains("pg_proto_link_bundle_share(\$bundle, \$action", $screen);
	pg_assert_contains('page_bundle_share_revoke', $screen);

	// A deleted bundle's links end with it: a later bundle may get its id.
	$bundles = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_bundles.php');
	$start = strpos($bundles, 'function pg_page_bundle_delete_locked(');
	pg_assert_contains('pg_proto_links_revoke_bundle($id);', substr($bundles, $start, 1500));
}
