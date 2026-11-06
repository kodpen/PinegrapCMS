<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Comments list's quick edit (includes/fn/comment_list_edit.php):
 * every one-line value the comment screen changes is a field, the fields a
 * row offers, the value each field opens with, and the form values the
 * comment screen's check gets for a change.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/comment_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/comment_list_edit.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $comment_edit_test_name => $comment_edit_test_value) {
	if (!defined($comment_edit_test_name)) {
		define($comment_edit_test_name, $comment_edit_test_value);
	}
}

function pg_test_comment_quick_row(array $over = array())
{
	return array_merge(array(
		'id' => '9', 'page_id' => '0', 'item_id' => '0', 'item_type' => '', 'name' => 'Ada', 'message' => "Nice\nwork",
		'rating' => '4', 'file_id' => null, 'file_name' => null, 'file_size' => null, 'published' => '1',
		'publish_date_and_time' => '0000-00-00 00:00:00', 'publish_cancel' => '0', 'featured' => '0',
		'created_user_id' => '0', 'page_name' => 'blog', 'page_type' => 'standard', 'page_folder' => '1', 'comments_submitter_email_page_id' => '0',
		'comments_watcher_email_page_id' => '0', 'comments_rating' => '1', 'created_username' => 'admin',
		'reference_code' => null, 'submitter_user_id' => null, 'product_group_address_name' => null,
		'product_address_name' => null, 'verified_order_id' => '0', 'kind' => 'comment', 'answer_count' => '0',
		'created_timestamp' => '1700000000',
	), $over);
}

function pg_test_comment_quick_scheduled(array $over = array())
{
	return pg_test_comment_quick_row(array_merge(array('published' => '0', 'publish_date_and_time' => '2030-05-06 14:30:00', 'publish_cancel' => '1'), $over));
}

// Every value the comment screen changes is an editable column of the
// list with a cell field (the message over several lines); the rest are
// text. A new column fails here
// until it is one or the other.
function test_comment_quick_every_screen_value_is_a_field()
{
	$text = array('submitted', 'go');
	$fields = pg_comment_quick_edit_columns();
	$cells = pg_comment_list_cells(pg_test_comment_quick_scheduled(), $fields);

	foreach (pg_comment_list_columns(true) as $key => $column) {
		if (in_array($key, $text, true)) {
			pg_assert_false(isset($column['editable']), $key . ' is text');
			continue;
		}

		pg_assert_true(isset($column['editable'], $fields[$key]), $key . ' is a field');
		pg_assert_same($key, $cells[$key]['attributes']['data-pg-edit'] ?? '', $key . ' cell carries its field');
	}

	foreach (array_keys($fields) as $field) {
		pg_assert_true(isset(pg_comment_list_columns(true)[$field]), $field . ' has a column');
	}

	foreach (pg_comment_list_columns(false) as $key => $column) {
		pg_assert_false(isset($column['editable']), $key . ' is text without quick edit');
	}

	foreach (pg_comment_list_cells(pg_test_comment_quick_row(), array()) as $key => $cell) {
		pg_assert_false(isset($cell['attributes']['data-pg-edit']), $key . ' has no field without quick edit');
	}
}

// The cancel switch only while scheduled, the rating only while the page
// rates comments, as the screen shows them.
function test_comment_quick_row_fields_follow_the_screen()
{
	$fields = pg_comment_quick_edit_columns();

	pg_assert_false(isset(pg_comment_quick_row_fields(pg_test_comment_quick_row(), $fields)['publish_cancel']));
	pg_assert_true(isset(pg_comment_quick_row_fields(pg_test_comment_quick_scheduled(), $fields)['publish_cancel']));
	pg_assert_false(isset(pg_comment_quick_row_fields(pg_test_comment_quick_row(array('comments_rating' => '0')), $fields)['rating']));

	$cells = pg_comment_list_cells(pg_test_comment_quick_row(array('comments_rating' => '0')), $fields);
	pg_assert_false(isset($cells['rating']['attributes']['data-pg-edit']));
	pg_assert_false(isset($cells['publish_cancel']['attributes']['data-pg-edit']));
	pg_assert_same('name', $cells['name']['attributes']['data-pg-edit']);
}

function test_comment_quick_publish_state()
{
	pg_assert_same('published', pg_comment_form_publish_state(pg_test_comment_quick_row()));
	pg_assert_same('schedule', pg_comment_form_publish_state(pg_test_comment_quick_scheduled()));
	pg_assert_same('not_published', pg_comment_form_publish_state(pg_test_comment_quick_row(array('published' => '0'))));
	pg_assert_same('not_published', pg_comment_form_publish_state(pg_test_comment_quick_row(array('published' => '0', 'publish_date_and_time' => ''))));
}

function test_comment_quick_values_and_cells()
{
	$row = pg_test_comment_quick_scheduled(array('rating' => '9'));
	$cells = pg_comment_list_cells($row, pg_comment_quick_edit_columns());

	pg_assert_same('0', pg_comment_quick_value('published', $row));
	pg_assert_same('1', pg_comment_quick_value('publish_cancel', $row));
	pg_assert_same('0', pg_comment_quick_value('rating', $row), 'an out of range rating opens as none');
	pg_assert_same('', pg_comment_quick_value('scheduled', pg_test_comment_quick_row()));
	pg_assert_same(prepare_form_data_for_output('2030-05-06 14:30:00', 'date and time', false), $cells['scheduled']['attributes']['data-pg-edit-value']);
	pg_assert_same('2030-05-06 14:30:00', $cells['scheduled']['attributes']['data-pg-sort-value']);

	// The name keeps its link to the comment screen; its field opens with
	// the stored name.
	pg_assert_contains('edit_comment.php?id=9', $cells['name']['html']);
	pg_assert_same('Ada', $cells['name']['attributes']['data-pg-edit-value']);

	// A check cell keeps its sort value beside its field.
	pg_assert_same('1', (string) pg_comment_list_cells(pg_test_comment_quick_row(), pg_comment_quick_edit_columns())['published']['attributes']['data-pg-sort-value']);

	$rated = pg_comment_list_cells(pg_test_comment_quick_row(), array());
	pg_assert_contains('★★★★<', $rated['rating']['html']);
	pg_assert_same('4', (string) $rated['rating']['attributes']['data-pg-sort-value']);
	pg_assert_same('', pg_comment_list_cells(pg_test_comment_quick_row(array('rating' => '0')), array())['rating']['html']);
}

function test_comment_quick_form_values()
{
	$published = pg_test_comment_quick_row();
	$scheduled = pg_test_comment_quick_scheduled();
	$draft = pg_test_comment_quick_row(array('published' => '0'));
	$date = prepare_form_data_for_output('2030-05-06 14:30:00', 'date and time', false);

	// The stored comment as the screen opens it.
	$values = pg_comment_quick_form_values($scheduled, 'name', 'Grace');
	pg_assert_same('Grace', $values['name']);
	pg_assert_same("Nice\nwork", $values['message']);
	pg_assert_same('schedule', $values['publish']);
	pg_assert_same($date, $values['publish_date_and_time']);
	pg_assert_same('1', $values['publish_cancel']);
	pg_assert_same('', $values['featured']);
	pg_assert_same('4', $values['rating']);

	// Published: Yes publishes; No takes a published comment off the site
	// and leaves a scheduled one as it is.
	pg_assert_same('published', pg_comment_quick_form_values($scheduled, 'published', '1')['publish']);
	pg_assert_same('not_published', pg_comment_quick_form_values($published, 'published', '0')['publish']);
	pg_assert_same('schedule', pg_comment_quick_form_values($scheduled, 'published', '0')['publish']);

	// A date schedules; a newly scheduled comment is cancelled by a newer
	// one, as the screen's switch starts; an empty date unschedules.
	$values = pg_comment_quick_form_values($draft, 'scheduled', '1/1/2031 9:00');
	pg_assert_same('schedule', $values['publish']);
	pg_assert_same('1/1/2031 9:00', $values['publish_date_and_time']);
	pg_assert_same('1', $values['publish_cancel']);
	pg_assert_same('', pg_comment_quick_form_values($scheduled, 'publish_cancel', '0')['publish_cancel']);
	pg_assert_same('', pg_comment_quick_form_values(pg_test_comment_quick_scheduled(array('publish_cancel' => '0')), 'scheduled', '1/1/2031 9:00')['publish_cancel']);
	pg_assert_same('not_published', pg_comment_quick_form_values($scheduled, 'scheduled', '')['publish']);
	pg_assert_same('published', pg_comment_quick_form_values($published, 'scheduled', '')['publish']);

	pg_assert_same('1', pg_comment_quick_form_values($published, 'featured', '1')['featured']);
	pg_assert_same('2', pg_comment_quick_form_values($published, 'rating', '2')['rating']);
}

function test_comment_quick_saved_draws_the_whole_row()
{
	$answer = pg_comment_quick_saved(pg_comment_list_cells(pg_test_comment_quick_row(), pg_comment_quick_edit_columns()), 'published');

	pg_assert_true($answer['ok']);
	pg_assert_same('1', $answer['value']);
	pg_assert_same('', $answer['cells']['scheduled']['html']);
	pg_assert_false(isset($answer['cells']['publish_cancel']['value']), 'no cancel switch once published');
	pg_assert_true(isset($answer['cells']['message']));
	pg_assert_false(isset($answer['cells']['published']));
}
