<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the collection CSV reader shared by Webflow and Wix
 * (includes/import/collection.php) on tests/fixtures/import/
 * webflow_collection.csv and wix_collection.csv, files built from the
 * columns both services document for their CMS export: the kind told from
 * the header, the mapping's column rows, items with their cells, drafts,
 * dates, Wix media addresses, a cell in each kind of paired field, and the
 * old address of a Webflow item.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/importers.php');
require_once(PG_FUNCTIONS_DIR . '/includes/import/webflow.php');
require_once(PG_FUNCTIONS_DIR . '/includes/import/wix.php');
require_once(PG_FUNCTIONS_DIR . '/includes/import/write_record.php');
require_once(PG_FUNCTIONS_DIR . '/includes/import/write_redirect.php');

function pg_test_import_collection_file($service)
{
	return dirname(__FILE__) . '/fixtures/import/' . $service . '_collection.csv';
}

function pg_test_import_collection_items($service)
{
	$open = 'pg_import_' . $service . '_open';
	$next = 'pg_import_' . $service . '_next';
	$cursor = $open(pg_test_import_collection_file($service));
	$items = array();

	while (($item = $next($cursor)) !== null) {
		$items[] = $item;

		// The cursor is stored as JSON between requests.
		$cursor = json_decode(json_encode($cursor), true);
	}

	return $items;
}

function test_import_collection_detect_kind()
{
	$head = function ($service) {
		return (string) file_get_contents(pg_test_import_collection_file($service), false, null, 0, 8192);
	};

	pg_assert_same('webflow', pg_import_detect_kind($head('webflow')), 'webflow');
	pg_assert_same('wix', pg_import_detect_kind($head('wix')), 'wix');
	pg_assert_same('wix', pg_import_detect_kind("Title,ID,Owner\nA,1,2"), 'wix with Owner');
	pg_assert_same('', pg_import_detect_kind("Title,ID\nA,1"), 'an ID alone is no kind');
	pg_assert_true(isset(pg_import_kinds()['webflow']) && isset(pg_import_kinds()['wix']), 'both are kinds');
	pg_assert_true(pg_import_reader_load('webflow') && pg_import_reader_load('wix'), 'both readers load');
}

// The form, the folder, Webflow's address, then a row per column but the ids.
function test_import_collection_mapping_rows()
{
	$rows = pg_import_webflow_mapping(pg_test_import_collection_file('webflow'));

	pg_assert_same(array('form', 'file_folder', 'address_base'), array_slice(array_keys($rows), 0, 3), 'leading rows');
	pg_assert_same('text', $rows['address_base']['type'], 'the address is typed');
	pg_assert_true(!isset($rows['col_2']) && !isset($rows['col_3']) && !isset($rows['col_4']), 'Collection ID, Locale ID and Item ID left out');
	pg_assert_same('Name', $rows['col_0']['guess'], 'guessed from the heading');
	pg_assert_same('form_field', $rows['col_10']['type'], 'a field row');
	pg_assert_same('form', $rows['col_10']['form'], 'of the form row');
	pg_assert_true(in_array('file upload', $rows['col_12']['field_types'], true), 'a picture column may take a file field');

	$wix = pg_import_wix_mapping(pg_test_import_collection_file('wix'));

	pg_assert_true(!isset($wix['address_base']), 'Wix has no address');
	pg_assert_true(!isset($wix['col_6']) && !isset($wix['col_9']), 'ID and Owner left out');
	pg_assert_true(isset($wix['col_7']), 'Created Date is offered');
	pg_assert_same(array('form', 'file_folder'), array_keys(pg_import_wix_mapping('')), 'no file, no column rows');
}

// A typed row keeps what was typed; an empty one is left out.
function test_import_collection_mapping_text_row()
{
	$rows = array(
		'form'         => array('type' => 'form', 'choices' => array('12' => 'Articles'), 'required' => true),
		'address_base' => array('type' => 'text', 'choices' => array()),
	);

	pg_assert_same(array('form' => '12', 'address_base' => '/blog'), pg_import_mapping_clean($rows, array('form' => '12', 'address_base' => ' /blog '))['mapping'], 'typed');
	pg_assert_same(array('form' => '12'), pg_import_mapping_clean($rows, array('form' => '12', 'address_base' => ''))['mapping'], 'empty');
	pg_assert_same(191, mb_strlen(pg_import_mapping_clean($rows, array('form' => '12', 'address_base' => str_repeat('a', 300)))['mapping']['address_base']), 'cut');
}

function test_import_collection_webflow_items()
{
	$items = pg_test_import_collection_items('webflow');

	pg_assert_same(3, count($items), 'the blank line is no item');
	pg_assert_same('webflow:item:64a1c0ffee000000000000c1', $items[0]['source_key'], 'keyed by Item ID');
	pg_assert_same('record', $items[0]['kind'], 'a record');
	pg_assert_same('Spring garden notes', $items[0]['title'], 'title from Name');
	pg_assert_same('spring-garden-notes', $items[0]['data']['slug'], 'slug');
	pg_assert_same('publish', $items[0]['data']['status'], 'published');
	pg_assert_same(gmmktime(8, 0, 0, 3, 16, 2024), $items[0]['data']['time'], 'Published On read without its zone name');
	pg_assert_same('garden; spring', $items[0]['data']['columns']['col_13'], 'a cell by its column row');
	pg_assert_true(!isset($items[1]['data']['columns']['col_9']), 'an empty cell is not kept');
	pg_assert_same('draft', $items[1]['data']['status'], 'Draft');
	pg_assert_same(gmmktime(9, 0, 0, 1, 8, 2024), $items[1]['data']['time'], 'Created On when not published');
	pg_assert_same('draft', $items[2]['data']['status'], 'Archived');
	pg_assert_same("Plain words\non two lines", $items[2]['data']['columns']['col_10'], 'a cell over two lines');
	pg_assert_same(array('title' => 'Winter tools', 'detail' => 'winter-tools (Draft)'), pg_import_webflow_plan($items[1]), 'plan line');
}

function test_import_collection_wix_items()
{
	$items = pg_test_import_collection_items('wix');

	pg_assert_same(2, count($items), 'two items');
	pg_assert_same('wix:item:0f3c2a10-1111-4a4a-9b9b-000000000001', $items[0]['source_key'], 'keyed by ID');
	pg_assert_same('Harbour walk', $items[0]['title'], 'title from Title');
	pg_assert_same('https://static.wixstatic.com/media/11062b_a1b2c3~mv2.jpg', $items[0]['data']['columns']['col_2'], 'media address');
	pg_assert_same(gmmktime(10, 0, 0, 5, 1, 2025), $items[0]['data']['time'], 'Created Date');
	pg_assert_same('', $items[0]['data']['slug'], 'no address');
	pg_assert_same(array(), pg_import_redirect_old_addresses('wix', $items[0], array('address_base' => '/blog')), 'no redirect');

	$keyless = pg_import_collection_item('wix', array('Title'), array(''), 7);
	pg_assert_same('wix:line:7', $keyless['source_key'], 'keyed by line without an ID');
	pg_assert_same('Line 7', $keyless['title'], 'a title from the line');
}

function test_import_collection_time()
{
	pg_assert_same(0, pg_import_collection_time(''), 'empty');
	pg_assert_same(0, pg_import_collection_time('soon'), 'unreadable');
	pg_assert_same(gmmktime(9, 0, 0, 6, 1, 2025), pg_import_collection_time('2025-06-01T09:00:00Z'), 'ISO 8601');
}

function test_import_collection_webflow_address()
{
	$item = pg_test_import_collection_items('webflow')[0];

	pg_assert_same(array('/blog/spring-garden-notes'), pg_import_redirect_old_addresses('webflow', $item, array('address_base' => 'blog/')), 'path');
	pg_assert_same(array('/news/posts/spring-garden-notes'), pg_import_redirect_old_addresses('webflow', $item, array('address_base' => 'https://old.example.com/news/posts')), 'a whole address');
	pg_assert_same(array(), pg_import_redirect_old_addresses('webflow', $item, array()), 'no address typed');
	pg_assert_same('', pg_import_redirect_collection_address('/blog', ''), 'no slug');
}

// Each kind of field a column can be paired with.
function test_import_collection_column_rows()
{
	$field = function ($type, $extra = array()) {
		return $extra + array('id' => 1, 'name' => 'f', 'type' => $type, 'wysiwyg' => 0, 'multiple' => 0, 'kind' => '', 'options' => null);
	};
	$data = function ($built) {
		return array_map(function ($row) {
			return $row['data'];
		}, $built['rows']);
	};

	$rich = pg_import_record_column_rows('<p>x</p>', $field('text area', array('wysiwyg' => 1)), array('html' => '<p>clean</p>'));
	pg_assert_same(array(array('data' => '<p>clean</p>', 'type' => 'html', 'file_id' => 0)), $rich['rows'], 'rich text as prepared');
	pg_assert_same(array('<p>a &amp; b<br>c</p>'), $data(pg_import_record_column_rows("a & b\nc", $field('text area', array('wysiwyg' => 1)), array())), 'plain cell as a paragraph');
	pg_assert_same(array('Start early.'), $data(pg_import_record_column_rows('<p>Start <b>early</b>.</p>', $field('text box'), array())), 'HTML as one line');
	pg_assert_same(array('ada@example.com'), $data(pg_import_record_column_rows('ada@example.com', $field('email address'), array())), 'an address');
	pg_assert_same(1, count(pg_import_record_column_rows('not-an-address', $field('email address'), array())['notes']), 'not an address');
	pg_assert_same(array(array('data' => '2025-06-01', 'type' => 'date', 'file_id' => 0)), pg_import_record_column_rows('2025-06-01', $field('date'), array())['rows'], 'a date');
	pg_assert_same(1, count(pg_import_record_column_rows('soon', $field('date'), array())['notes']), 'not a date');
	pg_assert_same(array(array('data' => '', 'type' => 'standard', 'file_id' => 42)), pg_import_record_column_rows('https://x.test/a.jpg', $field('file upload'), array('file_id' => 42))['rows'], 'a fetched file');
	pg_assert_same(array('f was not fetched'), pg_import_record_column_rows('https://x.test/a.jpg', $field('file upload'), array('note' => 'f was not fetched'))['notes'], 'a failed fetch');

	$options = array('options' => array('garden' => 'Garden', 'spring' => 'Spring'));
	pg_assert_same(array('garden', 'spring'), $data(pg_import_record_column_rows('garden; Spring', $field('check box', $options), array())), 'a list by label or value');
	pg_assert_same(array('garden'), $data(pg_import_record_column_rows('["garden","winter"]', $field('pick list', array('multiple' => 1) + $options), array())), 'a JSON list');
	pg_assert_same(1, count(pg_import_record_column_rows('["garden","winter"]', $field('pick list', array('multiple' => 1) + $options), array())['notes']), 'an unmatched name');
	pg_assert_same(array(), $data(pg_import_record_column_rows('garden, spring', $field('radio button', $options), array())), 'one choice reads the whole cell');
	pg_assert_same(array('yes'), $data(pg_import_record_column_rows('true', $field('check box', array('options' => array('yes' => 'Featured'))), array())), 'a switch ticks a lone check box');
	pg_assert_same(array(), $data(pg_import_record_column_rows('false', $field('check box', array('options' => array('yes' => 'Featured'))), array())), 'false leaves it');
	pg_assert_same(array('ref-12'), $data(pg_import_record_column_rows('ref-12', $field('text box'), array())), 'a reference stays text');
	pg_assert_same('https://a.test/1.jpg', pg_import_record_cell_address('[{"src":"https://a.test/1.jpg"},{"src":"https://a.test/2.jpg"}]'), 'first address of a gallery');
}

// The record rows take the paired cells beside a post's parts.
function test_import_collection_record_rows()
{
	$item = pg_test_import_collection_items('webflow')[0];
	$fields = array(
		1 => array('id' => 1, 'name' => 'title', 'type' => 'text box', 'wysiwyg' => 0, 'multiple' => 0, 'kind' => '', 'options' => null),
		2 => array('id' => 2, 'name' => 'summary', 'type' => 'text area', 'wysiwyg' => 0, 'multiple' => 0, 'kind' => '', 'options' => null),
	);
	$built = pg_import_record_rows($item, array('form' => '5', 'col_0' => '1', 'col_11' => '2', 'col_10' => '99'), $fields, array());

	pg_assert_same(array(1, 2), array_column($built['rows'], 'field_id'), 'paired fields only');
	pg_assert_same('Spring garden notes', $built['rows'][0]['data'], 'title cell');
	pg_assert_same('Start early, water often.', $built['rows'][1]['data'], 'summary cell');
}
