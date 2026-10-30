<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the File and Catalog Manager guided tours: each step names
 * the control it points at by the label the screen prints, not by its place
 * or shape, and the toolbar More button the last step rings has a name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function folder_tour_texts_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/view_folders.php');
}

// The two tour arrays, between the key line and the page shell.
function folder_tour_texts_block()
{
	$source = folder_tour_texts_source();
	$start = strpos($source, '$explorer_tour = ($explorer_area');
	$end = strpos($source, 'pg_page_shell(', (int) $start);
	pg_assert_true(($start !== false) && ($end !== false), 'tour block found');
	return substr($source, (int) $start, (int) $end - (int) $start);
}

function test_folder_tour_texts_no_position_or_shape_words()
{
	$block = folder_tour_texts_block();

	foreach (array(
		'panel of the menu on the left',
		'the button beside it',
		'The sliders beside it',
		'The two buttons on the right',
		'says along the top',
		'Anything in the middle of the screen',
		'the bar along the bottom',
		'the rest behind the three dots',
		'switched on and off in this menu',
	) as $phrase) {
		pg_assert_false(strpos($block, $phrase) !== false, 'gone: ' . $phrase);
	}
}

function test_folder_tour_texts_name_the_controls()
{
	$block = folder_tour_texts_block();

	foreach (array(
		'File Manager panel of the main menu',
		'Catalog Manager panel of the main menu',
		'Search, this box,',
		'The Filters button narrows',
		'Clear filters',
		'Grid view and List view',
		'the status bar under the listing',
		'More actions (the three dots)',
		'the Menu key or Shift+F10',
		'a long press',
		'More, the three dots at the end of the toolbar,',
	) as $phrase) {
		pg_assert_contains($phrase, $block, 'named: ' . $phrase);
	}
}

function test_folder_tour_texts_named_labels_are_printed()
{
	$source = folder_tour_texts_source();

	pg_assert_contains('title="<?php echo lang(\'Filters\'); ?>"', $source, 'Filters is the filter button name');
	pg_assert_contains('title="<?php echo lang(\'Grid view\'); ?>"', $source, 'Grid view is printed');
	pg_assert_contains('title="<?php echo lang(\'List view\'); ?>"', $source, 'List view is printed');
	pg_assert_contains("'more_actions' => lang('More actions')", $source, 'More actions is the status bar overflow name');
	pg_assert_contains("aria-label=\"' + esc(L.more_actions) + '\"", $source, 'the overflow button carries it');
	pg_assert_contains("'clear_filters' => lang('Clear filters')", $source, 'Clear filters is printed');
	pg_assert_contains("'items_selected' => lang('{var:1} item(s) selected')", $source, 'the status bar count is printed');
	pg_assert_contains('aria-label="<?php echo lang(\'Search\'); ?>"', $source, 'Search is the box name');
}

function test_folder_tour_texts_more_button_has_a_name()
{
	$source = folder_tour_texts_source();
	pg_assert_true(preg_match('~<button type="button" id="explorer_more_button"[^>]*\btitle="<\?php echo lang\(\'More\'\); \?>"[^>]*\baria-label="<\?php echo lang\(\'More\'\); \?>"~', $source) === 1, 'the toolbar More button has a title and an aria-label');
}

function test_folder_tour_texts_keys_and_targets()
{
	$source = folder_tour_texts_source();

	// Only the wording changed, so the keys stay where they are.
	pg_assert_contains("'explorer_catalog.4' : 'explorer_files.4'", $source, 'tour keys unchanged');

	pg_assert_true(preg_match_all("~case '[a-z_]+': return \\{ target: '([a-z_]+)'~", $source, $matches) > 0, 'tour targets found');
	foreach ($matches[1] as $id) {
		pg_assert_contains('id="' . $id . '"', $source, 'tour target in the markup: ' . $id);
	}
}
