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

function test_ltdr_set_steps()
{
	pg_assert_same(array('regions', 'design', 'page_regions', 'frame'), pg_ltd_steps());
	pg_assert_same(array('set_design', 'regions', 'set_pages', 'frame'), pg_ltd_steps(true));
}

function test_ltdr_state_new_with_sets()
{
	$set = array('key' => 'abcdef0123456789', 'name' => 'Home set', 'theme_id' => 4, 'collection' => 'b',
	             'settings' => array('layout_type' => 'system'), 'styles' => array(5, '6', 5), 'pages' => 9);
	$s = pg_ltd_state_new(array(6, 9, 0), array('id' => 1), 1762200000, array($set, array('styles' => array())));
	pg_assert_same(array(5, 6, 9), $s['styles'], 'the set first, a style chosen alone inside it once');
	pg_assert_same(1, count($s['sets']), 'a set with no style is dropped');
	pg_assert_same(array('key' => 'abcdef0123456789', 'name' => 'Home set', 'theme_id' => 4, 'collection' => 'b',
	                     'settings' => array('layout_type' => 'system'), 'styles' => array(5, 6), 'design' => 0), $s['sets'][0]);
	pg_assert_same('set_design', $s['step']);
	pg_assert_same(0, pg_ltd_state_set($s));
	$s['index'] = 2;
	pg_assert_same(-1, pg_ltd_state_set($s));
	$old = pg_ltd_state_new(array(3), array(), 1);
	unset($old['sets']);
	pg_assert_same(-1, pg_ltd_state_set($old), 'a cursor written before sets');
}

function test_ltdr_state_advance_through_a_set()
{
	$s = pg_ltd_state_new(array(9), array(), 1, array(array('name' => 'S', 'styles' => array(5, 6))));
	$seen = array();
	while (!pg_ltd_state_done($s)) {
		$p = pg_ltd_state_progress($s);
		$seen[] = $p['style'] . ':' . $p['step'];
		$s = pg_ltd_state_next($s);
	}
	pg_assert_same(array('5:set_design', '5:regions', '5:set_pages', '5:frame', '6:set_design', '6:regions', '6:set_pages', '6:frame',
	                     '9:regions', '9:design', '9:page_regions', '9:frame'), $seen);
}

function test_ltdr_state_next_skips()
{
	$s = pg_ltd_state_new(array(9), array(), 1, array(array('name' => 'S', 'styles' => array(5, 6, 7))));
	$s['step'] = 'set_pages';
	$s['skip'] = 'style';
	$s = pg_ltd_state_next($s);
	pg_assert_same(array('done' => 1, 'total' => 4, 'step' => 'set_design', 'style' => 6), pg_ltd_state_progress($s));
	pg_assert_same(false, isset($s['skip']), 'the mark is used once');
	$s['skip'] = 'set';
	$s = pg_ltd_state_next($s);
	pg_assert_same(array('done' => 3, 'total' => 4, 'step' => 'regions', 'style' => 9), pg_ltd_state_progress($s), 'past the rest of the set');
	$s = pg_ltd_state_next($s);
	pg_assert_same('design', $s['step']);
	$s['skip'] = 'set';
	$s = pg_ltd_state_next($s);
	pg_assert_same(true, pg_ltd_state_done($s), 'a style alone is skipped by itself');
}

function test_ltdr_set_design_row()
{
	$set = array('name' => 'Home set', 'theme_id' => 4, 'collection' => '', 'settings' => array('social_networking_position' => 'bottom_left', 'layout_type' => 'custom'));
	$assets = array(
		'css' => array(array('id' => 'a', 'type' => 'external-css', 'content' => '/x.css'),
		               array('id' => 'b', 'type' => 'css', 'content' => 'p{}', 'pages' => array(3), 'styles' => array('Home'))),
		'js'  => array(),
	);
	$row = pg_ltd_set_design_row($set, $assets, array(array('family' => 'Inter')), 7);
	pg_assert_same(0, $row['style_id']);
	pg_assert_same(7, $row['user_id']);
	pg_assert_same('Home set', $row['name']);
	pg_assert_same('legacy', $row['framework']);
	pg_assert_same(4, $row['theme_id']);
	pg_assert_same('a', $row['collection'], 'no collection reads as the default one');
	pg_assert_same('bottom_left', $row['social_networking_position']);
	pg_assert_same('[{"id":"a","type":"external-css","content":"/x.css"},{"id":"b","type":"css","content":"p{}","pages":[3]}]', $row['style_custom_css'], 'the style names of the dry run are not stored');
	pg_assert_same('[]', $row['style_custom_js']);
	pg_assert_same('[{"family":"Inter"}]', $row['style_custom_fonts']);
	pg_assert_same(false, array_key_exists('style_tree_json', $row), 'the pages carry the trees');
}

function test_ltdr_set_wiring()
{
	$screen = (string)file_get_contents(PG_FUNCTIONS_DIR . '/legacy_to_designer.php');
	pg_assert_same(true, strpos($screen, 'pg_ltd_run_start(array_keys($chosen), $user, array_keys($chosen_sets))') !== false, 'Convert passes the chosen sets');
	pg_assert_same(true, strpos($screen, '<input type="hidden" name="sets[]" value="') !== false, 'the Convert form carries the sets the dry run did not skip');
	pg_assert_same(false, strpos($screen, 'A set is shown as a dry run only'), 'no dry-run-only note');
	$run = (string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_run.php');
	pg_assert_same(1, preg_match("/case 'set_design':.*case 'set_pages':/s", $run), 'the run step knows the set steps');
	pg_assert_same(true, strpos($run, 'pg_classic_style_convert_folder_pages($style_id, $user, $classes, $design_id)') !== false, 'folder pages go to the design');
	pg_assert_same(true, strpos($run, 'pg_ltd_save_page($design_id, $page, $user)') !== false, 'the pages are saved to the design');
	pg_assert_same(false, strpos($run, 'pg_classic_style_mark_design($style_id', strpos($run, 'function pg_ltd_run_set_pages')), 'a style of a set stays classic');
	$cs = (string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/classic_style_design.php');
	pg_assert_same(1, preg_match('/function pg_classic_style_convert_folder_pages\(\$style_id, \$user, \$body_classes = \'\', \$to_style = 0\)/', $cs));
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
