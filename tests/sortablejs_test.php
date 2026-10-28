<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the move from jQuery UI .sortable() to the vendored
 * SortableJS: the library sits unmodified under assets/lib/sortablejs/ with
 * its license header, the three screens that drag (dashboard widgets, product
 * images, menu tree) load it, and none of them calls .sortable( any more.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function sortablejs_test_source($path)
{
	$file = dirname(__DIR__) . '/pinegrap/' . $path;

	return is_file($file) ? (string) file_get_contents($file) : '';
}

function test_sortablejs_library_is_vendored_with_its_license()
{
	$library = sortablejs_test_source('assets/lib/sortablejs/Sortable.min.js');

	pg_assert_same(0, strpos($library, '/*! Sortable 1.15.7 - MIT'), 'Sortable.min.js opens with its own license header');
	pg_assert_contains('MIT License', sortablejs_test_source('assets/lib/sortablejs/LICENSE'), 'the license file ships beside it');
}

function test_sortablejs_loaded_on_the_three_screens()
{
	foreach (array('welcome.php', 'product_builder.php', 'view_menu_items.php') as $path) {
		pg_assert_contains('assets/lib/sortablejs/Sortable.min.js', sortablejs_test_source($path), $path . ' loads SortableJS');
	}

	// Both places product_builder.php prints product_builder.js load the library first.
	pg_assert_same(
		substr_count(sortablejs_test_source('product_builder.php'), '/assets/js/product_builder.js?v='),
		substr_count(sortablejs_test_source('product_builder.php'), 'assets/lib/sortablejs/Sortable.min.js'),
		'product_builder.php loads SortableJS wherever it loads product_builder.js'
	);
}

function test_sortablejs_replaces_jquery_ui_sortable()
{
	foreach (array('welcome.php', 'assets/js/product_builder.js', 'view_menu_items.php') as $path) {
		$source = sortablejs_test_source($path);

		pg_assert_same(0, preg_match('/\.sortable\(/', $source), $path . ' calls no jQuery UI .sortable(');
		pg_assert_contains('Sortable.create(', $source, $path . ' sets up SortableJS');
	}

	pg_assert_same(0, substr_count(sortablejs_test_source('view_menu_items.php'), 'ui-sortable'), 'the menu tree no longer looks for the jQuery UI class');
}
