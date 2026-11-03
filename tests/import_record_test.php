<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the record and comment writers of a content import
 * (includes/import/write_record.php, write_comment.php): which form_data
 * rows a post becomes for each kind of mapped field, how categories and tags
 * meet option and reference fields, the author's key, a comment's text, and
 * the posts of the WordPress fixture through the row builder.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/import/write_record.php');
require_once(PG_FUNCTIONS_DIR . '/includes/import/write_comment.php');

// A form with one field of each kind the mapping can name.
function pg_test_import_record_fields()
{
	$field = function ($id, $name, $type, $extra = array()) {
		return $extra + array('id' => $id, 'name' => $name, 'type' => $type, 'wysiwyg' => 0, 'multiple' => 0, 'kind' => '', 'options' => null);
	};

	return array(
		1 => $field(1, 'title', 'text box'),
		2 => $field(2, 'body', 'text area', array('wysiwyg' => 1)),
		3 => $field(3, 'summary', 'text area'),
		4 => $field(4, 'publish_date', 'date and time'),
		5 => $field(5, 'cover_image', 'file upload'),
		6 => $field(6, 'section', 'pick list', array('options' => array('news' => 'News', 'sport' => 'Sport'))),
		7 => $field(7, 'tags', 'text box'),
		8 => $field(8, 'day', 'date'),
		9 => $field(9, 'topics', 'check box', array('kind' => 'reference', 'options' => array('AB12' => 'Travel', 'CD34' => 'Food'))),
		10 => $field(10, 'plain_body', 'text area'),
	);
}

function pg_test_import_record_item($data = array())
{
	return array(
		'kind'       => 'record',
		'source_key' => 'example.com:post:7',
		'title'      => 'Hello world',
		'data'       => $data + array(
			'status'     => 'publish',
			'body'       => '<p>Body &amp; more</p>',
			'excerpt'    => 'Short <b>one</b>',
			'time'       => 1700000000,
			'slug'       => 'hello-world',
			'author'     => array('login' => 'jane', 'name' => 'Jane', 'email' => 'jane@example.com'),
			'categories' => array('Sport', 'Opinion'),
			'tags'       => array('red', 'blue'),
			'picture'    => '',
		),
	);
}

// Field id => list of data values, for comparing rows.
function pg_test_import_record_by_field($rows)
{
	$by = array();

	foreach ($rows as $row) {
		$by[$row['field_id']][] = ($row['file_id'] > 0) ? 'file:' . $row['file_id'] : $row['data'];
	}

	return $by;
}

function test_import_record_rows_each_kind()
{
	$previous = date_default_timezone_get();
	date_default_timezone_set('UTC');

	$mapping = array('form' => '50', 'title' => '1', 'body' => '2', 'excerpt' => '3', 'date' => '4', 'picture' => '5', 'categories' => '6', 'tags' => '7');
	$built = pg_import_record_rows(pg_test_import_record_item(), $mapping, pg_test_import_record_fields(), array('body' => '<p>Body &amp; more</p>', 'picture_id' => 12));
	$by = pg_test_import_record_by_field($built['rows']);

	date_default_timezone_set($previous);

	pg_assert_same(array('Hello world'), $by[1], 'title');
	pg_assert_same(array('<p>Body &amp; more</p>'), $by[2], 'rich body as given');
	pg_assert_same(array('Short one'), $by[3], 'plain summary');
	pg_assert_same(array('2023-11-14 22:13'), $by[4], 'date and time');
	pg_assert_same(array('file:12'), $by[5], 'picture');
	pg_assert_same(array('sport'), $by[6], 'single pick list takes the first match');
	pg_assert_same(array('red, blue'), $by[7], 'text box joins');

	$types = array();
	foreach ($built['rows'] as $row) {
		$types[$row['field_id']] = $row['type'];
	}
	pg_assert_same('html', $types[2], 'rich text type');
	pg_assert_same('date and time', $types[4], 'date type');
	pg_assert_same('standard', $types[1], 'standard type');

	pg_assert_same(1, count($built['notes']), 'one note');
	pg_assert_true(strpos($built['notes'][0], 'Opinion') !== false, 'the unmatched category is named');
}

function test_import_record_rows_terms_into_one_field()
{
	$mapping = array('title' => '1', 'categories' => '7', 'tags' => '7');
	$built = pg_import_record_rows(pg_test_import_record_item(array('tags' => array('red', 'Sport'))), $mapping, pg_test_import_record_fields(), array());
	$by = pg_test_import_record_by_field($built['rows']);

	pg_assert_same(array('Sport, Opinion, red'), $by[7], 'categories and tags in one field, each once');
	pg_assert_same(array(), $built['notes'], 'a text field takes every name');
}

function test_import_record_rows_reference_and_check_box()
{
	$mapping = array('title' => '1', 'categories' => '9');
	$built = pg_import_record_rows(pg_test_import_record_item(array('categories' => array('food', 'Travel', 'Cars'))), $mapping, pg_test_import_record_fields(), array());
	$by = pg_test_import_record_by_field($built['rows']);

	pg_assert_same(array('CD34', 'AB12'), $by[9], 'one row per code, matched by label without case');
	pg_assert_true(strpos($built['notes'][0], 'Cars') !== false, 'the unknown one is named');
}

function test_import_record_rows_plain_and_date_fields()
{
	$previous = date_default_timezone_get();
	date_default_timezone_set('UTC');

	$mapping = array('title' => '1', 'body' => '10', 'date' => '8');
	$built = pg_import_record_rows(pg_test_import_record_item(), $mapping, pg_test_import_record_fields(), array('body' => "<p>One &amp; two</p>\n<p>Three<br>four</p>"));
	$by = pg_test_import_record_by_field($built['rows']);

	date_default_timezone_set($previous);

	pg_assert_same(array("One & two\n\nThree\nfour"), $by[10], 'a plain text area takes the text');
	pg_assert_same(array('2023-11-14'), $by[8], 'date');
}

function test_import_record_rows_untitled_and_missing()
{
	$mapping = array('title' => '1', 'picture' => '5', 'date' => '99');
	$item = pg_test_import_record_item(array('picture' => 'example.com:post:9'));
	$item['title'] = '';
	$built = pg_import_record_rows($item, $mapping, pg_test_import_record_fields(), array('picture_id' => 0, 'picture_note' => 'The featured image was not imported.'));
	$by = pg_test_import_record_by_field($built['rows']);

	pg_assert_same(array('hello-world'), $by[1], 'an untitled post takes its slug');
	pg_assert_false(isset($by[5]), 'no picture row without a file');
	pg_assert_false(isset($by[99]), 'a field of no form is left out');
	pg_assert_same(array('The featured image was not imported.'), $built['notes'], 'picture note');
}

function test_import_record_rows_empty_values_left_out()
{
	$mapping = array('title' => '1', 'excerpt' => '3', 'tags' => '7');
	$built = pg_import_record_rows(pg_test_import_record_item(array('excerpt' => '  ', 'tags' => array())), $mapping, pg_test_import_record_fields(), array());

	pg_assert_same(array(1), array_keys(pg_test_import_record_by_field($built['rows'])), 'only the title');
}

function test_import_record_author_key()
{
	pg_assert_same('example.com:author:jane', pg_import_record_author_key(pg_test_import_record_item()), 'site and login');

	$item = pg_test_import_record_item(array('author' => array('login' => '')));
	pg_assert_same('', pg_import_record_author_key($item), 'no login');

	$item = pg_test_import_record_item();
	$item['source_key'] = 'nokey';
	pg_assert_same('', pg_import_record_author_key($item), 'no site in the key');
}

function test_import_comment_text()
{
	pg_assert_same("Hi & welcome\n\nSecond line\nthird", pg_import_comment_text("<p>Hi &amp; welcome</p><p>Second line<br />third</p>"), 'paragraphs and breaks');
	pg_assert_same('Visit example (link)', pg_import_comment_text('Visit <a href="https://example.com">example</a> (link)'), 'tags dropped');
	pg_assert_same("a\n\nb", pg_import_comment_text("a\r\n\r\n\r\n\r\nb"), 'blank lines folded');
	pg_assert_same('', pg_import_comment_text('   '), 'empty');
}

function test_import_comment_writer_refusals()
{
	pg_assert_same('skipped', pg_import_comment_target_problem(array('post_kind' => 'file'), 0)['result'], 'a comment on a file');
	pg_assert_same('skipped', pg_import_comment_target_problem(array('post_kind' => 'record'), 0)['result'], 'post not imported');
	pg_assert_same(null, pg_import_comment_target_problem(array('post_kind' => 'record'), 5), 'post found');
	pg_assert_same(null, pg_import_comment_target_problem(array('post_kind' => 'page'), 5), 'page found');
}

// The fixture's posts through the reader and the row builder: every record
// gives a title row, and a draft stays a draft.
function test_import_record_fixture_posts()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/import/wordpress.php');

	$file = PG_FUNCTIONS_DIR . '/../tests/fixtures/import/wordpress.xml';
	$cursor = pg_import_wordpress_open($file);
	$records = 0;
	$drafts = 0;

	while (($item = pg_import_wordpress_next($cursor)) !== null) {
		if ($item['kind'] !== 'record') {
			continue;
		}

		$records++;
		$built = pg_import_record_rows($item, array('title' => '1'), pg_test_import_record_fields(), array());
		pg_assert_same(1, count($built['rows']), 'title row of ' . $item['source_key']);
		pg_assert_true(trim($built['rows'][0]['data']) !== '', 'title text of ' . $item['source_key']);

		if (!pg_import_record_complete($item)) {
			$drafts++;
		}
	}

	pg_assert_true($records > 0, 'the fixture has records');
	pg_assert_true($drafts > 0, 'the fixture has a draft');
}

function test_import_record_writers_wired()
{
	$record = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/import/write_record.php');
	$comment = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/import/write_comment.php');

	pg_assert_true(strpos($record, 'pg_sanitize_rich_text(') !== false, 'body sanitised');
	pg_assert_true(strpos($record, 'pg_import_media_localize(') !== false, 'body pictures fetched');
	pg_assert_true(strpos($record, 'pg_comment_delete_record_thread(') !== false, 'record undo removes its thread');
	pg_assert_true(strpos($record, 'update_submitted_form_address_name(') !== false, 'pretty address');
	pg_assert_true(strpos($comment, 'pg_comment_refresh_record_info(') !== false, 'record counters');
	pg_assert_true(strpos($comment, 'pg_comment_replies_ready()') !== false, 'parent column only when ready');
}
