<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Back to classic (includes/fn/legacy_to_classic.php): the style
 * a frame component was made from, the classic style a moved page came
 * from, the page_style it goes back to, the refusals, and the screen's
 * wiring.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_classic.php');

function test_ltc_category_style()
{
	pg_assert_same(12, pg_ltc_category_style('classic_style:12'));
	pg_assert_same(12, pg_ltc_category_style('classic_style:12:top:1'));
	pg_assert_same(7, pg_ltc_category_style('classic_style:7:bottom:2'));
	pg_assert_same(0, pg_ltc_category_style('classic_style:0'));
	pg_assert_same(0, pg_ltc_category_style('classic_style:'));
	pg_assert_same(0, pg_ltc_category_style('classic_style:12:Top:1'));
	pg_assert_same(0, pg_ltc_category_style('classic_style:12x'));
	pg_assert_same(0, pg_ltc_category_style('Header'));
	pg_assert_same(0, pg_ltc_category_style(''));
	pg_assert_same(0, pg_ltc_category_style(null));
}

function test_ltc_origin_style()
{
	$frames = array(4 => 12, 5 => 12, 6 => 13);
	$tree = function ($ids) {
		$children = array();
		foreach ($ids as $id) $children[] = array('type' => 'shared_ref', 'props' => array('sharedId' => $id), 'children' => array());
		return array('type' => 'root', 'children' => $children);
	};
	pg_assert_same(12, pg_ltc_origin_style($tree(array(4)), $frames), 'a whole frame');
	pg_assert_same(12, pg_ltc_origin_style($tree(array(4, 5)), $frames), 'the parts of one split frame');
	pg_assert_same(12, pg_ltc_origin_style($tree(array(9, 4)), $frames), 'another shared component is ignored');
	pg_assert_same(0, pg_ltc_origin_style($tree(array(4, 6)), $frames), 'frames of two styles');
	pg_assert_same(0, pg_ltc_origin_style($tree(array(9)), $frames), 'no frame');
	pg_assert_same(0, pg_ltc_origin_style(array('type' => 'root', 'children' => array()), $frames));
	$nested = array('type' => 'root', 'children' => array(
		array('type' => 'shared_ref', 'props' => array('sharedId' => 9), 'children' => array(
			array('type' => 'shared_ref', 'props' => array('sharedId' => 4), 'children' => array())))));
	pg_assert_same(0, pg_ltc_origin_style($nested, $frames), 'only a frame the page places itself counts');
}

function test_ltc_target_style()
{
	$folders = pg_classic_style_folder_styles(array(
		array('folder_id' => 1, 'folder_parent' => 0, 'folder_style' => 12, 'folder_level' => 0),
		array('folder_id' => 2, 'folder_parent' => 1, 'folder_style' => 0, 'folder_level' => 1),
		array('folder_id' => 3, 'folder_parent' => 1, 'folder_style' => 13, 'folder_level' => 1),
	));
	pg_assert_same(0, pg_ltc_target_style(12, false, 1, $folders), 'the folder gives the style');
	pg_assert_same(0, pg_ltc_target_style(12, false, 2, $folders), 'inherited from the parent folder');
	pg_assert_same(12, pg_ltc_target_style(12, false, 3, $folders), 'the folder gives another style now');
	pg_assert_same(12, pg_ltc_target_style(12, false, 99, $folders), 'an unknown folder');
	pg_assert_same(13, pg_ltc_target_style(13, false, 1, $folders));
	pg_assert_same(12, pg_ltc_target_style(12, true, 1, $folders), 'a page that had the style as its own keeps it');
}

function test_ltc_origins_from_runs()
{
	$state = array('origins' => array());
	$state = pg_ltd_state_origin($state, 180, 741, true);
	$state = pg_ltd_state_origin($state, 863, 608, false);
	$state = pg_ltd_state_origin($state, 0, 608, false);
	$state = pg_ltd_state_origin($state, 5, 0, false);
	pg_assert_same(array('180' => array(741, 1), '863' => array(608, 0)), $state['origins']);
	$runs = array(
		json_decode(json_encode($state), true),
		array('lines' => array()),
		array('origins' => array('863' => array(614, 1), '9' => 'x', '-3' => array(1, 0))),
	);
	pg_assert_same(array(180 => array(741, true), 863 => array(614, true)), pg_ltc_origins_merge($runs), 'a later run wins');
	pg_assert_same(array(), pg_ltc_origins_merge(array()));
}

function test_ltc_refusal()
{
	$ok = array('origin' => 12, 'origin_classic' => true, 'experiment' => false, 'held' => false);
	pg_assert_same('', pg_ltc_refusal($ok));
	pg_assert_same('no_origin', pg_ltc_refusal(array('origin' => 0) + $ok));
	pg_assert_same('origin', pg_ltc_refusal(array('origin_classic' => false) + $ok));
	pg_assert_same('experiment', pg_ltc_refusal(array('experiment' => true) + $ok));
	pg_assert_same('held', pg_ltc_refusal(array('held' => true) + $ok));
	pg_assert_same('no_origin', pg_ltc_refusal(array()));
	foreach (array('no_origin', 'origin', 'experiment', 'held') as $code) {
		pg_assert_same(true, pg_ltc_refusal_text($code) !== '', $code . ' has a sentence');
	}
	pg_assert_same('', pg_ltc_refusal_text(''));
}

function test_ltc_wiring()
{
	$screen = (string)file_get_contents(PG_FUNCTIONS_DIR . '/legacy_to_designer.php');
	$post = strpos($screen, "if (!empty(\$_POST['back']))");
	pg_assert_same(true, $post !== false, 'the screen has the back branch');
	pg_assert_same(true, strpos($screen, 'validate_token_field()') < $post, 'the token is checked first');
	pg_assert_same(true, strpos($screen, 'pg_ltd_state_read() !== null', $post) < strpos($screen, 'pg_ltc_back(', $post), 'not while a move runs');
	pg_assert_same(true, strpos($screen, 'pg_ltc_back(', $post) < strpos($screen, 'pg_ltd_plan(', $post), 'the back branch redirects before the dry run');
	pg_assert_same(true, strpos($screen, 'id="ltd-back"') !== false);
	pg_assert_same(true, strpos($screen, '#ltd-back-result') !== false);
	pg_assert_same(2, substr_count($screen, 'class="d-none md:d-table-cell"'), 'below md the classic style column goes under the page name');
	$module = (string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_classic.php');
	pg_assert_same(true, strpos($module, 'pg_page_revision_record(') < strpos($module, 'page_tree_json = NULL'), 'the tree is kept as a revision before it leaves the page');
	pg_assert_same(true, strpos($module, "(int)\$user['role'] > 1") !== false, 'only a designer');
	$run = (string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_run.php');
	pg_assert_same(2, substr_count($run, '$state = $moved($state, (int)$'), 'own and folder pages keep their origin');
}
