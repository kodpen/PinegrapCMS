<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Short Links list's quick edit
 * (includes/fn/short_link_list_edit.php): which cell of which link is a
 * field, the value it opens with, the pickers' choices, and the form
 * values the short link screen's checks get for a change.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/short_link_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/short_link_list_edit.php');

function pg_test_short_link_quick_link(array $over = array())
{
	return array_merge(array(
		'id' => '7', 'name' => 'promo', 'destination_type' => 'product', 'page_id' => '12', 'page_type' => 'catalog detail',
		'product_group_id' => '0', 'product_id' => '30', 'url' => '', 'file_id' => '0', 'tracking_code' => 'mail',
		'redirect_code' => '0',
	), $over);
}

function pg_test_short_link_quick_edit()
{
	return pg_short_link_quick_edit_columns(array(
		'pages' => array('12' => 'detail', '13' => 'about'),
		'group_pages' => array('11' => 'shop', '12' => 'detail'),
		'product_pages' => array('12' => 'detail'),
		'groups' => array('4' => 'Shoes'),
		'products' => array('30' => 'Boot'),
	), true, 191, 2048);
}

// Every value the short link screen changes is a field of some column; the
// rest of the columns are computed. A new column fails here until it is one
// or the other.
function test_short_link_quick_every_screen_value_is_a_field()
{
	$computed = array('created', 'last_modified', 'visit');
	$fields = array();
	$types = array(
		'page' => array(),
		'product_group' => array('product_group_id' => '4'),
		'product' => array(),
		'url' => array('url' => 'https://example.com/'),
		'file' => array('file_id' => '9'),
	);

	foreach (array_keys(pg_short_link_list_columns(true, true)) as $column) {
		$found = in_array($column, $computed, true);

		foreach ($types as $type => $over) {
			$field = pg_short_link_quick_field($column, pg_test_short_link_quick_link(array('destination_type' => $type) + $over));

			if ($field !== null) {
				$found = true;
				$fields[$field[0]] = true;
			}
		}

		pg_assert_true($found, $column . ' is a field or computed');
	}

	foreach (array_keys(pg_test_short_link_quick_edit()) as $field) {
		pg_assert_true(isset($fields[$field]), $field . ' has a cell');
	}
}

function test_short_link_quick_field_follows_destination_type()
{
	$page = pg_test_short_link_quick_link(array('destination_type' => 'page'));
	$group = pg_test_short_link_quick_link(array('destination_type' => 'product_group', 'product_group_id' => '4'));
	$url = pg_test_short_link_quick_link(array('destination_type' => 'url', 'url' => 'https://example.com/a'));

	pg_assert_same(array('page_id', '12'), pg_short_link_quick_field('page', $page));
	pg_assert_same(null, pg_short_link_quick_field('destination', $page), 'a page has no target beside its page');
	pg_assert_same(array('group_page_id', '12'), pg_short_link_quick_field('page', $group));
	pg_assert_same(array('product_group_id', '4'), pg_short_link_quick_field('destination', $group));
	pg_assert_same(array('product_page_id', '12'), pg_short_link_quick_field('page', pg_test_short_link_quick_link()));
	pg_assert_same(array('product_id', '30'), pg_short_link_quick_field('destination', pg_test_short_link_quick_link()));
	pg_assert_same(array('url', 'https://example.com/a'), pg_short_link_quick_field('destination', $url));
	pg_assert_same(null, pg_short_link_quick_field('page', $url));
	pg_assert_same(null, pg_short_link_quick_field('tracking_code', $url), 'a URL takes no tracking code');
	pg_assert_same(array('tracking_code', 'mail'), pg_short_link_quick_field('tracking_code', $page));
}

function test_short_link_quick_one_time_link_has_no_field()
{
	$token = pg_test_short_link_quick_link(array('name' => ''));

	foreach (array_keys(pg_short_link_list_columns(true, true)) as $column) {
		pg_assert_same(null, pg_short_link_quick_field($column, $token), $column);
	}
}

function test_short_link_quick_no_answer_before_upgrade()
{
	$link = pg_test_short_link_quick_link();
	unset($link['redirect_code']);

	pg_assert_same(null, pg_short_link_quick_field('redirect_code', $link));
	pg_assert_false(isset(pg_short_link_list_columns(false, true)['redirect_code']));
	pg_assert_false(isset(pg_short_link_quick_edit_columns(array(), false, 100, 255)['redirect_code']));
}

function test_short_link_quick_cell_needs_its_stored_choice()
{
	$quick_edit = pg_test_short_link_quick_edit();

	pg_assert_same('product_id', pg_short_link_quick_attributes('destination', pg_test_short_link_quick_link(), $quick_edit)['data-pg-edit'] ?? '');
	pg_assert_same(array(), pg_short_link_quick_attributes('destination', pg_test_short_link_quick_link(array('product_id' => '31')), $quick_edit), 'a product not offered (in the bin) stays text');
	pg_assert_same(array(), pg_short_link_quick_attributes('name', pg_test_short_link_quick_link(), array()), 'no quick edit, no field');
	pg_assert_same('mail', pg_short_link_quick_attributes('tracking_code', pg_test_short_link_quick_link(), $quick_edit)['data-pg-edit-value'] ?? '');
}

// The File cell searches the files (kind file): it is a field for any file
// the link has, carrying its name, and stays text for a file that is gone.
function test_short_link_quick_file_cell_searches()
{
	$quick_edit = pg_test_short_link_quick_edit();
	$file = pg_test_short_link_quick_link(array('destination_type' => 'file', 'file_id' => '9'));

	pg_assert_same('search', $quick_edit['file_id']['type']);
	pg_assert_contains('file_picker_search.php?kind=file', $quick_edit['file_id']['source']);
	pg_assert_false(isset($quick_edit['file_id']['options']), 'no list of every file');

	$attributes = pg_short_link_quick_attributes('destination', $file, $quick_edit, 'a.pdf [IN RECYCLE BIN]');

	pg_assert_same('file_id', $attributes['data-pg-edit'] ?? '');
	pg_assert_same('9', $attributes['data-pg-edit-value'] ?? '');
	pg_assert_same('a.pdf [IN RECYCLE BIN]', $attributes['data-pg-edit-text'] ?? '', 'the stored file as the field shows it');
	pg_assert_same(array(), pg_short_link_quick_attributes('destination', $file, $quick_edit, ''), 'a file that no longer exists stays text');
	pg_assert_false(isset(pg_short_link_quick_attributes('destination', pg_test_short_link_quick_link(), $quick_edit)['data-pg-edit-text']), 'a select carries no text');

	$saved = pg_short_link_quick_saved(array(
		'destination' => pg_list_cell('a.pdf', 'align-middle', $attributes),
		'name' => pg_list_cell('x', 'align-middle', array('data-pg-edit-value' => 'x')),
	), 'destination');

	pg_assert_same('9', $saved['value']);
	pg_assert_same('a.pdf [IN RECYCLE BIN]', $saved['text'], 'the answer names the file, for undo and apply');
	pg_assert_false(isset($saved['cells']['name']['text']));
}

function test_short_link_quick_pairs_read_both_pick_list_forms()
{
	pg_assert_same(array('5' => 'A & B'), pg_short_link_quick_pairs(array('-Select-' => '', 'A &amp; B' => '5')));
	pg_assert_same(array('4' => "\u{00A0}\u{00A0}Shoes"), pg_short_link_quick_pairs(array(array('label' => '', 'value' => ''), array('label' => '&nbsp;&nbsp;Shoes', 'value' => 4))));
}

function test_short_link_form_values_open_like_the_edit_screen()
{
	$values = pg_short_link_form_values(pg_test_short_link_quick_link(array('destination_type' => 'product_group', 'page_type' => 'catalog', 'product_group_id' => '4')));

	pg_assert_same('12', $values['catalog_page_id']);
	pg_assert_false(isset($values['catalog_detail_page_id']));
	pg_assert_same('4', $values['product_group_id']);
	pg_assert_same('0', $values['redirect_code']);

	$values = pg_short_link_form_values(pg_test_short_link_quick_link(array('destination_type' => 'file', 'file_id' => '9')));
	pg_assert_same('9', $values['file']);
}

function test_short_link_quick_form_values_put_the_change_in_its_field()
{
	$types = array('11' => 'catalog', '12' => 'catalog detail');
	$group = pg_test_short_link_quick_link(array('destination_type' => 'product_group', 'product_group_id' => '4'));

	$values = pg_short_link_quick_form_values($group, 'group_page_id', '11', $types);
	pg_assert_same('11', $values['catalog_page_id'], 'a catalog page goes to the catalog picker');
	pg_assert_false(isset($values['catalog_detail_page_id']));

	$values = pg_short_link_quick_form_values(pg_test_short_link_quick_link(), 'product_page_id', '13', $types);
	pg_assert_same('13', $values['catalog_detail_page_id']);
	pg_assert_same('30', $values['product_id']);

	$values = pg_short_link_quick_form_values(pg_test_short_link_quick_link(), 'destination_type', 'page', $types);
	pg_assert_same('page', $values['destination_type']);
	pg_assert_same('12', $values['page_id'], 'the stored page moves to the new type\'s picker');

	$values = pg_short_link_quick_form_values(pg_test_short_link_quick_link(), 'destination_type', 'url', $types);
	pg_assert_same('', $values['url'], 'a new URL type has no URL: the screen\'s check refuses it');

	pg_assert_same('new', pg_short_link_quick_form_values(pg_test_short_link_quick_link(), 'name', 'new', $types)['name']);
	pg_assert_same('301', pg_short_link_quick_form_values(pg_test_short_link_quick_link(), 'redirect_code', '301', $types)['redirect_code']);

	$file = pg_test_short_link_quick_link(array('destination_type' => 'file', 'file_id' => '9'));
	pg_assert_same('8', pg_short_link_quick_form_values($file, 'file_id', '8', $types)['file']);
}

function test_short_link_quick_column_of_each_field()
{
	foreach (array_keys(pg_test_short_link_quick_edit()) as $field) {
		pg_assert_true(isset(pg_short_link_list_columns(true, true)[pg_short_link_quick_column($field)]), $field);
	}
}

// The Page and Destination cells are names to read, and the list's search
// reads the cell text: "pg-ğ#x" is shown as the Pages list shows it, never
// its address form "pg-%C4%9F%23x", which no one types into the search.
function test_short_link_list_cells_show_names_as_text()
{
	// A one-time link (no name) has no Visit address to build.
	$link = array(
		'id' => '7', 'name' => '', 'token_hint' => 'abc', 'page_id' => '12', 'page_type' => 'standard',
		'product_group_id' => '0', 'product_id' => '0', 'url' => '', 'file_id' => '0', 'tracking_code' => '',
		'product_group_address_name' => 'çay-ş', 'product_address_name' => 'kılıf 50%', 'file_name' => 'özet ğ#1.pdf',
		'page_name' => 'pg-ğış#x', 'created_timestamp' => 0, 'created_username' => '',
		'last_modified_timestamp' => 0, 'last_modified_username' => '',
	);

	$page = pg_short_link_list_cells($link + array('destination_type' => 'page'), array());
	pg_assert_same('pg-ğış#x', $page['page']['html'] ?? '', 'the page name as it is');

	$group = pg_short_link_list_cells($link + array('destination_type' => 'product_group'), array());
	pg_assert_same('çay-ş', $group['destination']['html'] ?? '', 'a product group address name as it is');

	$product = pg_short_link_list_cells($link + array('destination_type' => 'product'), array());
	pg_assert_same('kılıf 50%', $product['destination']['html'] ?? '', 'a product address name as it is');

	$file = pg_short_link_list_cells(array_merge($link, array('destination_type' => 'file', 'file_id' => '9')), array());
	pg_assert_same('özet ğ#1.pdf', $file['destination']['html'] ?? '', 'a file name as it is');

	$escaped = pg_short_link_list_cells(array_merge($link, array('destination_type' => 'page', 'page_name' => 'a<b>"c')), array());
	pg_assert_same('a&lt;b&gt;&quot;c', $escaped['page']['html'] ?? '', 'still escaped for HTML');
}
