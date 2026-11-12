<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the add and edit screens in look A: every add or edit
 * screen that prints the form and status columns takes the edit page width,
 * the shared forms title their cards with the look's section title, and the
 * look's block sizes the columns, the cards and the save bar.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function look_edit_screens_test_root()
{
	return dirname(__DIR__) . '/pinegrap/';
}

/** The screen's own source and the files under includes/ it requires. */
function look_edit_screens_test_source_with_includes($file)
{
	$source = (string) file_get_contents($file);
	$all = $source;

	if (preg_match_all('#(?:require|include)(?:_once)?\s*\(?[^;]*?/(includes/(?:forms|templates|fn)/[a-z0-9_]+\.php)#', $source, $matches)) {
		foreach (array_unique($matches[1]) as $path) {
			$included = look_edit_screens_test_root() . $path;
			$all .= is_file($included) ? (string) file_get_contents($included) : '';
		}
	}

	return array($source, $all);
}

function test_edit_screens_take_the_edit_width()
{
	$checked = 0;

	foreach (array_merge(glob(look_edit_screens_test_root() . 'add_*.php'), glob(look_edit_screens_test_root() . 'edit_*.php')) as $file) {
		list($source, $all) = look_edit_screens_test_source_with_includes($file);

		if (strpos($all, 'lg:col-8 2xl:col-9 order-1') === false || strpos($source, "'extra classes'") === false) {
			continue;
		}

		$checked++;
		pg_assert_true((bool) preg_match("/'extra classes'\\s*=>\\s*'[^']*\\bpg-measure-edit\\b/", $source), basename($file) . ' takes the edit width');
	}

	pg_assert_true($checked > 80, 'the add and edit screens were found');
}

function test_shared_forms_title_their_cards_in_the_look()
{
	foreach (glob(look_edit_screens_test_root() . 'includes/forms/*.php') as $file) {
		pg_assert_same(false, strpos((string) file_get_contents($file), 'text-uppercase h5 fg-primary fw-bold'), basename($file) . ' keeps no upper-case card title');
	}
}

function test_edit_screen_block_sizes_columns_cards_and_save_bar()
{
	$css = (string) file_get_contents(look_edit_screens_test_root() . 'assets/css/backend.src.css');
	$start = strpos($css, 'Look A: add and edit screens.');
	$block = ($start === false) ? '' : substr($css, $start);

	pg_assert_true($block !== '', 'the block is found');
	pg_assert_contains("body.pg-measure-edit .row > .order-1.lg\\:col-8 {\n    flex: 760 1 0%;", $block, 'the form column takes 760 parts');
	pg_assert_contains("body.pg-measure-edit .row > .order-2.lg\\:col-4 {\n    flex: 336 1 0%;", $block, 'the status column takes 336 parts');
	pg_assert_contains('font-size: var(--pg-text-status-title);', $block, 'a status card title is the status title');
	pg_assert_contains('min-height: 56px;', $block, 'the save bar is 56 px');
	pg_assert_contains("body.pg-measure-edit .row > .order-1 > .card,\nbody.pg-measure-edit .row > .order-2 > .position-sticky > .card {\n  background: linear-gradient", $css, 'the cards are the look card');
	pg_assert_contains("body.pg-measure-edit .row > .order-3 > nav.buttons.navigation {\n  border-radius: var(--pg-radius-card);", $css, 'the save bar is the floating bar');
}
