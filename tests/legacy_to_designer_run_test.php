<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the run of the bulk move to the visual designer
 * (includes/fn/legacy_to_designer_run.php): the cursor, its steps and
 * progress, report lines, body classes, placed component ids, and the
 * wiring of the screen and the general job.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_run.php');

function test_ltdr_state_new_keeps_order_once()
{
	$s = pg_ltd_state_new(array(5, '3', 5, 0, -2, 9), array('id' => 7, 'username' => 'ayse'), 1762200000);
	pg_assert_same(array(5, 3, 9), $s['styles']);
	pg_assert_same(7, $s['user_id']);
	pg_assert_same('regions', $s['step']);
	pg_assert_same(0, $s['index']);
	pg_assert_same(1, preg_match('/^\d{8}-\d{6}-[0-9a-f]{6}$/', $s['id']), $s['id']);
	pg_assert_same(array(), pg_ltd_state_new(array(), array(), 1)['styles']);
}

function test_ltdr_state_advance_through_the_steps()
{
	$s = pg_ltd_state_new(array(1, 2), array(), 1);
	$seen = array();
	while (!pg_ltd_state_done($s)) {
		$p = pg_ltd_state_progress($s);
		$seen[] = $p['style'] . ':' . $p['step'];
		$s = pg_ltd_state_advance($s);
	}
	pg_assert_same(array('1:regions', '1:design', '1:page_regions', '1:frame', '2:regions', '2:design', '2:page_regions', '2:frame'), $seen);
	pg_assert_same(array('done' => 2, 'total' => 2, 'step' => 'regions', 'style' => 0), pg_ltd_state_progress($s));
}

function test_ltdr_state_advance_skips_a_style()
{
	$s = pg_ltd_state_new(array(1, 2), array(), 1);
	$s = pg_ltd_state_advance($s);
	$s = pg_ltd_state_advance($s, true);
	pg_assert_same(array('done' => 1, 'total' => 2, 'step' => 'regions', 'style' => 2), pg_ltd_state_progress($s));
}

function test_ltdr_state_line_counts_and_caps()
{
	$s = pg_ltd_state_new(array(4), array(), 1);
	$s = pg_ltd_state_line($s, 'made', 'a', 'pages');
	$s = pg_ltd_state_line($s, 'skipped', 'b');
	$s = pg_ltd_state_line($s, 'failed', 'c');
	pg_assert_same(1, $s['counts']['pages']);
	pg_assert_same(1, $s['counts']['skipped']);
	pg_assert_same(1, $s['counts']['failed']);
	pg_assert_same(array('style' => 4, 'kind' => 'made', 'text' => 'a'), $s['lines'][0]);
	$s['lines'] = array_fill(0, PG_LTD_REPORT_LINES, array());
	$s = pg_ltd_state_line($s, 'made', 'd');
	pg_assert_same(PG_LTD_REPORT_LINES, count($s['lines']));
	pg_assert_same(1, $s['more']);
}

function test_ltdr_fold_body_classes()
{
	$t = pg_ltd_fold_body_classes(array('type' => 'root', 'props' => array('cssClass' => 'home  wide')), ' wide dark ');
	pg_assert_same('wide dark home', $t['props']['cssClass']);
	$t = pg_ltd_fold_body_classes(array('type' => 'root'), 'x');
	pg_assert_same('x', $t['props']['cssClass']);
	$same = array('type' => 'root', 'props' => array());
	pg_assert_same($same, pg_ltd_fold_body_classes($same, ''));
}

function test_ltdr_shared_ids_direct_only()
{
	$tree = array('type' => 'root', 'children' => array(
		array('type' => 'shared_ref', 'props' => array('sharedId' => 4), 'children' => array(
			array('type' => 'shared_ref', 'props' => array('sharedId' => 8), 'children' => array()))),
		array('type' => 'semantic', 'children' => array(array('type' => 'shared_ref', 'props' => array('sharedId' => '4')))),
		array('type' => 'shared_ref', 'props' => array('sharedId' => 0)),
	));
	pg_assert_same(array(4), pg_ltd_shared_ids($tree));
}

function test_ltdr_wiring()
{
	$job = (string)file_get_contents(PG_FUNCTIONS_DIR . '/job.php');
	pg_assert_same(true, strpos($job, "'/data/temp/legacy_to_designer.json'") !== false, 'the job checks the cursor file before loading the run');
	pg_assert_same(true, strpos($job, 'pg_ltd_job_tick(pg_cron_time_left(20))') !== false);
	pg_assert_same(PG_FUNCTIONS_DIR . '/data/temp/legacy_to_designer.json', pg_ltd_state_path());
	$screen = (string)file_get_contents(PG_FUNCTIONS_DIR . '/legacy_to_designer.php');
	pg_assert_same(true, strpos($screen, 'session_write_close()') < strpos($screen, 'pg_ltd_run(8)'), 'the session is closed before a step');
	pg_assert_same(true, strpos($screen, 'hash_equals(') < strpos($screen, 'pg_ltd_run(8)'), 'the token is checked before a step');
	$run = (string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_run.php');
	pg_assert_same(true, strpos($run, "GET_LOCK('pg_ltd', 0)") !== false);
}

function test_ltdr_dry_run_in_toolbar()
{
	$screen = (string)file_get_contents(PG_FUNCTIONS_DIR . '/legacy_to_designer.php');
	$bar = strpos($screen, 'pg_screen_toolbar(');
	pg_assert_same(true, $bar !== false);
	$button = strpos($screen, '<button type="submit" form="ltd_dry_run"');
	pg_assert_same(true, $button !== false, 'Dry run submits the form from outside it');
	pg_assert_same(true, strpos($screen, "'primary' => \$toolbar_primary") > $bar, 'Dry run is the primary slot of the toolbar');
	pg_assert_same(1, preg_match('/<form id="ltd_dry_run"[^>]* hidden>/', $screen), 'the token form is hidden, not a second bar');
	pg_assert_same(1, preg_match('/if \(\$report && \$running === null\) \{\s*\$toolbar_primary/', $screen), 'no Dry run while a move runs');
}
