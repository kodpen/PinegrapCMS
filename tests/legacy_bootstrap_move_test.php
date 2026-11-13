<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for moving a Legacy design's pages to its trial design and back
 * (includes/fn/legacy_bootstrap_move.php): the cursor, its lines and
 * progress, the refusals, the parts the trial has no copy of, and the
 * wiring of the module, the screen and the job.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_bootstrap_move.php');

function test_lbs_move_state()
{
	$s = pg_lbs_move_state_new('move', 12, 30, array(5, '7', 5, 0, -1), array('id' => 3, 'username' => 'ada'), 1700000000);
	pg_assert_same('move', $s['direction']);
	pg_assert_same(array(5, 7), $s['pages'], 'ids once, positive only');
	pg_assert_same(12, $s['legacy']);
	pg_assert_same(30, $s['trial']);
	pg_assert_same(1, preg_match('/^[0-9]{8}-[0-9]{6}-[0-9a-f]{8}$/', $s['id']), 'a run id fit for a file name');
	pg_assert_same('back', pg_lbs_move_state_new('back', 1, 2, array(), array(), 0)['direction']);
	pg_assert_same('move', pg_lbs_move_state_new('other', 1, 2, array(), array(), 0)['direction'], 'anything else moves');

	pg_assert_same(array('done' => 0, 'total' => 2), pg_lbs_move_state_progress($s));
	pg_assert_same(false, pg_lbs_move_state_done($s));
	$s['index'] = 2;
	pg_assert_same(true, pg_lbs_move_state_done($s));
	$s['index'] = 9;
	pg_assert_same(array('done' => 2, 'total' => 2), pg_lbs_move_state_progress($s));
	pg_assert_same(true, pg_lbs_move_state_done(pg_lbs_move_state_new('move', 1, 2, array(), array(), 0)), 'no page: done');
}

function test_lbs_move_state_lines()
{
	$s = pg_lbs_move_state_new('move', 1, 2, array(1), array(), 0);
	$s = pg_lbs_move_state_line($s, 'moved', 'home');
	$s = pg_lbs_move_state_line($s, 'skipped', 'blog: held');
	$s = pg_lbs_move_state_line($s, 'failed', 'shop: not saved');
	pg_assert_same(array('moved' => 1, 'skipped' => 1, 'failed' => 1), $s['counts']);
	pg_assert_same(array('kind' => 'skipped', 'text' => 'blog: held'), $s['lines'][1]);
	$s['lines'] = array_fill(0, PG_LBS_MOVE_LINES, array('kind' => 'moved', 'text' => 'x'));
	$s = pg_lbs_move_state_line($s, 'moved', 'one more');
	pg_assert_same(PG_LBS_MOVE_LINES, count($s['lines']), 'lines past the limit are counted only');
	pg_assert_same(1, $s['more']);
	pg_assert_same(2, $s['counts']['moved']);
}

function test_lbs_move_refusal()
{
	pg_assert_same('', pg_lbs_move_refusal(array(), 'move'));
	pg_assert_same('experiment', pg_lbs_move_refusal(array('experiment' => true, 'held' => true), 'move'));
	pg_assert_same('held', pg_lbs_move_refusal(array('held' => true), 'move'));
	pg_assert_same('', pg_lbs_move_refusal(array('kept' => true, 'legacy' => true), 'back'));
	pg_assert_same('no_origin', pg_lbs_move_refusal(array('legacy' => true), 'back'), 'no kept tree');
	pg_assert_same('origin', pg_lbs_move_refusal(array('kept' => true), 'back'), 'the Legacy design is gone');
	pg_assert_same('experiment', pg_lbs_move_refusal(array('kept' => true, 'legacy' => true, 'experiment' => true), 'back'));
	pg_assert_same('held', pg_lbs_move_refusal(array('kept' => true, 'legacy' => true, 'held' => true), 'back'));
	foreach (array('no_origin', 'origin', 'experiment', 'held') as $code) {
		pg_assert_same(true, pg_lbs_move_refusal_text($code) !== '', $code);
	}
	pg_assert_same('', pg_lbs_move_refusal_text(''));
}

function test_lbs_move_unmapped_parts()
{
	$tree = array('type' => 'root', 'children' => array(
		array('type' => 'shared_ref', 'props' => array('sharedId' => 4), 'children' => array()),
		array('type' => 'section', 'children' => array(
			array('type' => 'shared_ref', 'props' => array('sharedId' => '9'), 'children' => array()),
		)),
	));
	pg_assert_same(array(9), pg_lbs_move_unmapped_parts($tree, array(4 => 40)));
	pg_assert_same(array(), pg_lbs_move_unmapped_parts($tree, array(4 => 40, 9 => 90)));
	pg_assert_same(array(4, 9), pg_lbs_move_unmapped_parts($tree, array()));
	$map = json_decode(json_encode((object)array(4 => 40, 9 => 90)), true);
	pg_assert_same(array(), pg_lbs_move_unmapped_parts($tree, $map), 'the map as trials.json gives it back');
}
function test_lbs_move_wiring()
{
	$module = (string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legacy_bootstrap_move.php');
	pg_assert_same(true, strpos($module, "(int)\$user['role'] > 1") !== false, 'only a designer starts a move');
	pg_assert_same(true, strpos($module, "GET_LOCK('pg_lbs_move', 0)") !== false, 'one request at a time');
	$write = strpos($module, 'function pg_lbs_move_write_page');
	pg_assert_same(true, strpos($module, 'pg_page_revision_record(', $write) < strpos($module, 'UPDATE page SET', $write), 'the tree it had is a revision before the page is written');
	$move = strpos($module, 'function pg_lbs_move_page(');
	pg_assert_same(true, strpos($module, 'pg_lbs_move_kept_write(', $move) < strpos($module, 'pg_lbs_move_write_page(', $move), 'the Legacy tree is kept before the page moves');
	pg_assert_same(true, strpos($module, "pg_lbs_move_page_states(array((int)\$page_id))", $move) !== false, 'held and experiment checked per page');
	$back = strpos($module, 'function pg_lbs_move_back_page(');
	pg_assert_same(true, strpos($module, 'pg_lbs_move_write_page(', $back) < strpos($module, '@unlink(pg_lbs_move_kept_path(', $back), 'the kept tree goes only once the page is back');

	$screen = (string)file_get_contents(PG_FUNCTIONS_DIR . '/legacy_to_designer.php');
	$post = strpos($screen, "if (isset(\$_POST['bootstrap_move']))");
	pg_assert_same(true, $post !== false, 'the screen has the move branch');
	pg_assert_same(true, strpos($screen, 'validate_token_field()') < $post, 'the token is checked first');
	pg_assert_same(true, strpos($screen, 'pg_lbs_move_start(', $post) < strpos($screen, 'pg_ltd_plan(', $post), 'the branch redirects before the dry run');
	pg_assert_same(true, strpos($screen, 'pg_lbs_move_run(8)') !== false, 'the screen works the move');
	pg_assert_same(3, substr_count($screen, 'pg_lbs_moves_running()'), 'back, trial and the screen state wait for either move');

	$run = (string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_run.php');
	pg_assert_same(true, strpos($run, 'legacy_to_bootstrap.json') !== false, 'a move to the Page Design Studio waits for a page move');
	$job = (string)file_get_contents(PG_FUNCTIONS_DIR . '/job.php');
	pg_assert_same(true, strpos($job, "is_file(PG_FUNCTIONS_DIR . '/data/temp/legacy_to_bootstrap.json')") !== false, 'the job loads the module only for a running move');
	pg_assert_same(true, strpos($job, 'pg_lbs_move_job_tick(') !== false);
}
