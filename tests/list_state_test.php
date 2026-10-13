<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_list_state() in includes/fn/output.php: the sort and direction
 * a list screen keeps in the session, and the page number it reads from the
 * address.
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
