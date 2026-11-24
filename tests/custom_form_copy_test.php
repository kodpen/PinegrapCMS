<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a form copied with its page (duplicate_page_f.php): the rows
 * are copied with every column, a field a designed widget already wrote is
 * not copied twice, a designed form keeps its settings, and the settings
 * that name a field by id point at the copy's field.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every column but the id is kept, the given ones written on top, a NULL
// kept as NULL.
function test_cf_copy_row_keeps_every_column()
{
	$row = array('id' => '14', 'page_id' => '3', 'name' => 'answer', 'quiz_question' => '1',
		'quiz_answer' => 'Paris', 'rss_field' => 'title', 'validation_regex' => '[0-9]+', 'rules' => null);

	$copy = pg_cf_copy_row($row, array('page_id' => 9, 'user' => 2));

	pg_assert_true(!array_key_exists('id', $copy), 'the id is the new row\'s own');
	pg_assert_same(9, $copy['page_id']);
	pg_assert_same(2, $copy['user']);
	pg_assert_same('Paris', $copy['quiz_answer'], 'the quiz answer goes with the copy');
	pg_assert_same('title', $copy['rss_field']);
	pg_assert_same('[0-9]+', $copy['validation_regex']);
	pg_assert_true(array_key_exists('rules', $copy) && $copy['rules'] === null, 'NULL rules stay NULL');
}

// schema_author and share_image_field_id move to the copy's field of the
// same name; a field the copy lacks falls back to the defaults.
function test_cf_copied_field_settings_move_by_name()
{
	$old_names = array(31 => 'Author', 32 => 'Photo', 33 => 'Gone');
	$new_ids = array('author' => 81, 'photo' => 82);

	pg_assert_same(array('schema_author' => 'field:81', 'share_image_field_id' => 82),
		pg_cf_copied_field_settings(array('schema_author' => 'field:31', 'share_image_field_id' => '32'), $old_names, $new_ids));
	pg_assert_same(array('schema_author' => 'contact', 'share_image_field_id' => 0),
		pg_cf_copied_field_settings(array('schema_author' => 'field:33', 'share_image_field_id' => '33'), $old_names, $new_ids), 'a field the copy lacks');
	pg_assert_same(array('schema_author' => 'organization'),
		pg_cf_copied_field_settings(array('schema_author' => 'organization'), $old_names, $new_ids), 'a choice without a field is kept');
	pg_assert_same(array('share_image_field_id' => 0),
		pg_cf_copied_field_settings(array('share_image_field_id' => '0'), $old_names, $new_ids), 'no share image field stays none');
}

// The duplicate copies the fields through the one copier, which skips a
// name the copy already has, and gives a designed form its settings before
// the first sync.
function test_cf_duplicate_page_uses_the_copier()
{
	$dup = (string) file_get_contents(PG_FUNCTIONS_DIR . '/duplicate_page_f.php');
	pg_assert_true(strpos($dup, 'pg_cf_copy_page_fields($page_id, $new_page[\'id\'], $user[\'id\'])') !== false, 'the field rows go through the copier');
	pg_assert_true(strpos($dup, 'INSERT INTO form_fields') === false, 'no column list of its own that drops columns');

	$settings = strpos($dup, 'pg_cf_copy_form_settings(');
	$sync = strpos($dup, 'pg_cf_reconcile_page_form($new_page[\'id\']');
	$refs = strpos($dup, 'pg_cf_copy_form_field_settings(');
	pg_assert_true($settings !== false && $sync !== false && $refs !== false && $settings < $sync && $sync < $refs, 'settings, then the sync, then the field references');

	$cf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/custom_form.php');
	$start = strpos($cf, 'function pg_cf_copy_page_fields(');
	$body = substr($cf, $start, strpos($cf, "\n}\n", $start) - $start);
	pg_assert_true(strpos($body, 'if (isset($have[$key]))') !== false, 'a name the copy already has is not copied again');
	pg_assert_true(strpos($body, 'target_options') !== false, 'pick list targets are copied');
}
