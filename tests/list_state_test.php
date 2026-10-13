<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_list_state() in includes/fn/output.php: the sort and direction
 * a list screen keeps in the session, and the page number it reads from the
 * address. Also the sort levels of a list sorted by more than one column:
 * reading them from the address, the Shift+click step, the ORDER BY they
 * become, the heading links that carry them and the sort menu's links.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * Runs pg_list_state() with the given request and session, and puts both
 * back afterwards.
 */
function list_state_run($request, $stored = null)
{
	$saved_request = $_REQUEST;
	$saved_session = $_SESSION ?? null;

	$_REQUEST = $request;
	$_SESSION = array();

	if ($stored !== null) {
		$_SESSION['software']['list_state']['products'] = $stored;
	}

	$columns = array('name' => 'Name', 'price' => 'Price', 'modified' => 'Last Modified');
	$state = pg_list_state('products', $columns, array('sort' => 'modified', 'order' => 'desc'));
	$kept = $_SESSION['software']['list_state']['products'];

	$_REQUEST = $saved_request;
	$_SESSION = $saved_session;

	return array($state, $kept);
}

function test_list_state_unknown_column_falls_back_to_the_default()
{
	list($state, $kept) = list_state_run(array('sort' => 'password', 'order' => 'asc'));

	pg_assert_same('modified', $state['sort'], 'default column');
	pg_assert_same('desc', $state['order'], 'default direction');
	pg_assert_same(array('sort' => 'modified', 'order' => 'desc'), $kept, 'the default is what is kept');
}

function test_list_state_order_other_than_desc_becomes_asc()
{
	list($state) = list_state_run(array('sort' => 'price', 'order' => 'DROP'));
	pg_assert_same('asc', $state['order'], 'DROP');

	list($state) = list_state_run(array('sort' => 'price', 'order' => 'DESC'));
	pg_assert_same('desc', $state['order'], 'any case of desc');

	list($state) = list_state_run(array('sort' => 'price', 'order' => array('desc')));
	pg_assert_same('asc', $state['order'], 'an array');
}

function test_list_state_sort_without_order_starts_in_the_default_direction()
{
	list($state) = list_state_run(array('sort' => 'price'), array('sort' => 'name', 'order' => 'desc'));
	pg_assert_same(array('price', 'asc'), array($state['sort'], $state['order']), 'another column starts ascending');

	list($state) = list_state_run(array('sort' => 'modified'), array('sort' => 'name', 'order' => 'asc'));
	pg_assert_same(array('modified', 'desc'), array($state['sort'], $state['order']), 'the default column starts in its own direction');
}

function test_list_state_keeps_the_stored_choice()
{
	list($state) = list_state_run(array(), array('sort' => 'name', 'order' => 'asc'));
	pg_assert_same(array('name', 'asc'), array($state['sort'], $state['order']), 'stored sort');

	list($state) = list_state_run(array('order' => 'desc'), array('sort' => 'name', 'order' => 'asc'));
	pg_assert_same(array('name', 'desc'), array($state['sort'], $state['order']), 'direction changed alone');

	list($state) = list_state_run(array(), array('sort' => 'Price', 'order' => 'asc'));
	pg_assert_same('modified', $state['sort'], 'a stored heading is not a column');
}

function test_list_state_maps_a_heading_to_its_code()
{
	list($state, $kept) = list_state_run(array('sort' => 'Price', 'order' => 'desc'));

	pg_assert_same(array('price', 'desc'), array($state['sort'], $state['order']), 'heading from an old link');
	pg_assert_same('price', $kept['sort'], 'kept as the code');
}

function test_list_state_screen_is_a_positive_whole_number()
{
	$cases = array('3' => 3, '0' => 1, '-2' => 1, '2.5' => 1, 'abc' => 1, '' => 1, '1 OR 1=1' => 1);

	foreach ($cases as $given => $expected) {
		list($state) = list_state_run(array('screen' => (string) $given));
		pg_assert_same($expected, $state['screen'], 'screen ' . var_export((string) $given, true));
	}

	list($state) = list_state_run(array());
	pg_assert_same(1, $state['screen'], 'no screen');
}

function test_list_state_reads_several_levels_from_the_address()
{
	list($state, $kept) = list_state_run(array('sort' => 'price:desc,name:asc'));

	pg_assert_same(array(array('sort' => 'price', 'order' => 'desc'), array('sort' => 'name', 'order' => 'asc')), $state['sorts'], 'both levels in order');
	pg_assert_same(array('price', 'desc'), array($state['sort'], $state['order']), 'the first level is the sort');
	pg_assert_same(array('sort' => 'price', 'order' => 'desc', 'then' => array(array('sort' => 'name', 'order' => 'asc'))), $kept, 'the rest kept under then');
}

function test_list_state_drops_unknown_and_repeated_levels()
{
	list($state) = list_state_run(array('sort' => 'price:desc,password:asc,price:asc,name:DESC,modified:drop'));
	pg_assert_same(
		array(array('sort' => 'price', 'order' => 'desc'), array('sort' => 'name', 'order' => 'desc'), array('sort' => 'modified', 'order' => 'desc')),
		$state['sorts'], 'unknown column and repeat gone, direction cleaned');

	list($state, $kept) = list_state_run(array('sort' => 'password:asc,1 OR 1=1:desc'));
	pg_assert_same(array(array('sort' => 'modified', 'order' => 'desc')), $state['sorts'], 'nothing left is the default');
	pg_assert_same(array('sort' => 'modified', 'order' => 'desc'), $kept, 'a single level is kept as before');
}

function test_list_state_keeps_stored_levels_until_a_single_sort_replaces_them()
{
	$stored = array('sort' => 'price', 'order' => 'desc', 'then' => array(array('sort' => 'name', 'order' => 'asc')));

	list($state) = list_state_run(array(), $stored);
	pg_assert_same(array('price', 'name'), array_column($state['sorts'], 'sort'), 'stored levels');

	list($state) = list_state_run(array('order' => 'asc'), $stored);
	pg_assert_same(array(array('sort' => 'price', 'order' => 'asc'), array('sort' => 'name', 'order' => 'asc')), $state['sorts'], 'a direction alone turns the first level');

	list($state, $kept) = list_state_run(array('sort' => 'name', 'order' => 'desc'), $stored);
	pg_assert_same(array(array('sort' => 'name', 'order' => 'desc')), $state['sorts'], 'an older single-sort link');
	pg_assert_false(isset($kept['then']), 'no levels left behind');
}

function test_list_sort_toggle_adds_turns_and_drops_a_level()
{
	$one = array(array('sort' => 'price', 'order' => 'desc'));
	$two = array(array('sort' => 'price', 'order' => 'desc'), array('sort' => 'name', 'order' => 'asc'));

	pg_assert_same($two, pg_list_sort_toggle($one, 'name'), 'a new column joins ascending');
	pg_assert_same(array(array('sort' => 'price', 'order' => 'desc'), array('sort' => 'name', 'order' => 'desc')), pg_list_sort_toggle($two, 'name'), 'ascending turns descending');
	pg_assert_same(array(array('sort' => 'name', 'order' => 'asc')), pg_list_sort_toggle($two, 'price'), 'descending leaves');
	pg_assert_same(array(array('sort' => 'price', 'order' => 'asc')), pg_list_sort_toggle($one, 'price'), 'the only level turns ascending');
}

function test_list_order_by_writes_only_the_mapped_columns()
{
	$map = array('price' => 'products.price', 'name' => 'products.name');
	$levels = array(array('sort' => 'price', 'order' => 'desc'), array('sort' => 'name; DROP TABLE x', 'order' => 'asc'), array('sort' => 'name', 'order' => 'x'));

	pg_assert_same('products.price desc, products.name asc', pg_list_order_by($levels, $map), 'unmapped code skipped, odd direction ascending');
	pg_assert_same('price:desc,name:asc', pg_list_sort_value(array(array('sort' => 'price', 'order' => 'desc'), array('sort' => 'name', 'order' => 'asc'))), 'address value');
}

function test_list_sort_heading_links_keep_or_replace_the_levels()
{
	$saved = $_SERVER['PHP_SELF'] ?? null;
	$_SERVER['PHP_SELF'] = '/pinegrap/view_products.php';

	$levels = array(array('sort' => 'price', 'order' => 'asc'), array('sort' => 'name', 'order' => 'desc'));

	$price = get_column_heading('Price', $levels, 'asc', '', 'price');
	pg_assert_contains('href="/pinegrap/view_products.php?sort=price:desc"', $price, 'a click reverses the leading level alone');
	pg_assert_contains('data-pg-sort-add="/pinegrap/view_products.php?sort=price:desc,name:desc"', $price, 'Shift+click keeps the rest');
	pg_assert_contains('bi-1-circle-fill', $price, 'its number');

	$stock = get_column_heading('Stock', $levels, 'asc', '', 'stock');
	pg_assert_contains('href="/pinegrap/view_products.php?sort=stock:asc"', $stock, 'another column starts ascending');
	pg_assert_contains('sort=price:asc,name:desc,stock:asc"', $stock, 'Shift+click adds it last');
	pg_assert_false(strpos($stock, '-circle-fill') !== false, 'no number when not sorted by');

	$single = get_column_heading('Price', array(array('sort' => 'price', 'order' => 'asc')), 'asc', '', 'price');
	pg_assert_false(strpos($single, '-circle-fill') !== false, 'no number with one level');

	pg_assert_contains('?sort=price&order=desc', get_column_heading('Price', 'price', 'asc', '', 'price'), 'a single sort keeps the old links');

	if ($saved === null) {
		unset($_SERVER['PHP_SELF']);
	} else {
		$_SERVER['PHP_SELF'] = $saved;
	}
}

function test_list_sort_menu_keeps_the_rest_of_the_address()
{
	$columns = array('name' => 'Name', 'price' => 'Price', 'modified' => 'Last Modified');
	$default = array('sort' => 'modified', 'order' => 'desc');
	$levels = array(array('sort' => 'price', 'order' => 'asc'), array('sort' => 'name', 'order' => 'desc'));

	$menu = pg_list_sort_menu('view_contacts.php', $columns, $levels, $default, array('filter' => 'my_customers'));
	pg_assert_contains('href="view_contacts.php?sort=price:desc,name:desc&amp;filter=my_customers"', $menu, 'reversing keeps the view');
	pg_assert_contains('href="view_contacts.php?sort=name:desc&amp;filter=my_customers"', $menu, 'removing keeps the view');
	pg_assert_contains('href="view_contacts.php?sort=modified:desc&amp;filter=my_customers"', $menu, 'the reset keeps the view');
	pg_assert_contains('<input type="hidden" name="filter" value="my_customers"><select name="sort"', $menu, 'the form sends the view');

	$odd = pg_list_sort_menu('view_contacts.php', $columns, $levels, $default, array('filter' => 'a&b="c"'));
	pg_assert_contains('&amp;filter=a%26b%3D%22c%22"', $odd, 'the value is encoded in links');
	pg_assert_contains('value="a&amp;b=&quot;c&quot;"', $odd, 'and escaped in the form');

	$plain = pg_list_sort_menu('view_products.php', $columns, $levels, $default);
	pg_assert_contains('href="view_products.php?sort=price:desc,name:desc"', $plain, 'no extra address without one');
	pg_assert_false(strpos($plain, 'type="hidden"') !== false, 'no hidden field without one');
}
