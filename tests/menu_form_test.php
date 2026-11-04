<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the menu form shared by the create and the edit screens
 * (includes/forms/menu_form.php): both screens use the shared form, the
 * edit screen layout and its Summary rows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/menu_form.php');

function test_menu_form_screens_use_shared_form()
{
	foreach (array('add_menu.php', 'edit_menu.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/menu_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_menu_form_cards($liveform', $source, $file . ' prints the shared cards');
		pg_assert_contains('pg_menu_form_layout(', $source, $file . ' uses the shared layout');
		pg_assert_contains('pg_menu_form_validate_name($liveform, ', $source, $file . ' checks the name the shared way');
		pg_assert_contains(($file === 'add_menu.php') ? 'pg_menu_form_values($liveform)' : 'pg_menu_form_save($liveform, ', $source, $file . ' saves the shared values');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_false(strpos($source, 'Menu Options') !== false, $file . ' keeps no copy of the card');
	}

	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/menu_form.php');
	pg_assert_contains("PG_FUNCTIONS_DIR", $form, 'the form has the gate');
	pg_assert_contains('SELECT id FROM menus WHERE (name = ?)', $form, 'the uniqueness check binds the name');
}

function test_menu_copy_name_fits_the_column()
{
	pg_assert_same('main-1', pg_dash_suffix_name('main'));
	pg_assert_same('main-3', pg_dash_suffix_name('main-2'));
	pg_assert_same('main-menu-1', pg_dash_suffix_name('main-menu'), 'a dash without a number');

	$hundred = 'pg-test-p04-long-' . str_repeat('w', 83);
	$copy = pg_dash_suffix_name($hundred, 100);
	pg_assert_same(100, mb_strlen($copy, 'UTF-8'), 'the copy fits the column');
	pg_assert_same(mb_substr($hundred, 0, 98, 'UTF-8') . '-1', $copy, 'the base is cut, not the suffix');
	pg_assert_false($copy === $hundred, 'the copy differs from the original');
	pg_assert_same(mb_substr($hundred, 0, 97, 'UTF-8') . '-10', pg_dash_suffix_name(mb_substr($hundred, 0, 98, 'UTF-8') . '-9', 100), 'a longer number cuts one more');

	$files = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/files.php');
	pg_assert_contains('pg_dash_suffix_name($name, pg_name_max_length())', $files, 'the menu branch of get_unique_name() uses the limit');
}

function test_menu_form_checks_lengths()
{
	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/menu_form.php');
	pg_assert_contains("lang('Name can be at most 100 characters long.')", $form, 'the name length is checked');
	pg_assert_contains("lang('Classes can be at most 255 characters long.')", $form, 'the classes length is checked');

	foreach (array('add_menu.php', 'edit_menu.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_menu_form_validate_classes($liveform)', $source, $file . ' checks the classes');
	}
}

function test_menu_form_name_pattern()
{
	pg_assert_same('[A-Za-z0-9\\-]+', pg_menu_form_name_pattern(null), 'a new name takes letters, numbers and dashes');
	pg_assert_same('[A-Za-z0-9\\-]+', pg_menu_form_name_pattern('main-menu'), 'a stored name without an underscore');
	pg_assert_same('[A-Za-z0-9_\\-]+', pg_menu_form_name_pattern('main_menu'), 'a stored name with an underscore keeps it');
}

function test_menu_form_wide_layout_columns_and_save_bar()
{
	$layout = pg_menu_form_layout('<div id="cards"></div>', '<button id="b"></button>');
	pg_assert_contains('<div class="row"><div id="cards"></div>', $layout, 'the cards open the row');
	pg_assert_contains('col-12 order-3 position-sticky', $layout, 'the save bar is sticky under both columns');
	pg_assert_contains('<button id="b"></button>', $layout, 'the buttons go in the bar');

	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/menu_form.php');
	$form_column = strpos($src, 'col-12 lg:col-8 2xl:col-9 order-1');
	$status_column = strpos($src, 'col-12 lg:col-4 2xl:col-3 order-2');
	pg_assert_true($form_column !== false, 'the form column');
	pg_assert_true(($status_column !== false) && ($status_column > $form_column), 'the status column follows the form column in the source');
	pg_assert_contains('style="top:3.3rem;"', $src, 'the status box sticks below the top bar');

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_menu.php');
	pg_assert_contains("'status' => pg_menu_form_items_status(\$item_count, h(\$items_url))", $edit, 'the item count sits in the status column');
	pg_assert_false(strpos($edit, "'grow'") !== false, 'the body tag left the toolbar for the Summary');
}

function test_menu_form_effect_labels_and_items_status()
{
	$labels = pg_menu_form_effect_labels();
	pg_assert_same(array('Pop-up', 'Accordion', ''), array_keys($labels), 'one label per stored effect, none included');

	$row = pg_menu_form_items_status(7, 'view_menu_items.php?id=3&amp;from=');
	pg_assert_contains('href="view_menu_items.php?id=3&amp;from="', $row, 'the count links to the items screen');
	pg_assert_contains('>7</dd>', $row, 'the count');
}
