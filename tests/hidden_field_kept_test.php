<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Two edit screens write only the values whose boxes they draw. Edit Comment
 * draws the star rating only while the comment's page has stars on; with them
 * off a save keeps the stored rating, which add_comment.php may have written
 * before the page switched stars off. Edit Field draws Quiz Question and
 * Correct Answer only for a custom form whose quiz is on; otherwise a save
 * keeps the field's quiz flag and correct answer. Both screens need a
 * database, so they are checked in their source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function hidden_field_kept_test_update($file, $table)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	preg_match('/"UPDATE ' . $table . '\s+SET(.*?)WHERE/s', $source, $match);

	return array($source, isset($match[1]) ? $match[1] : '');
}

function test_edit_comment_writes_rating_only_when_stars_are_drawn()
{
	// The save (includes/forms/comment_form.php) writes only the columns it
	// is given; the rating is given only while the page draws stars, on the
	// screen and in the Comments list's quick edit.
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_comment.php');
	$columns = '/\$columns = array\((.*?)\);/s';

	pg_assert_true((bool) preg_match($columns, $source, $match), 'the screen\'s columns are found');
	pg_assert_false(strpos($match[1] ?? '', 'rating') !== false, 'the columns do not hold the rating on their own');
	pg_assert_true((bool) preg_match('/if \(\$comments_rating != false\) \{\s*\$columns\[\'rating\'\] = /', $source), 'the rating is added only while the page draws stars');
	pg_assert_contains('pg_review_comment_changed($item_type, $item_id);', $source, 'the product rating is still recounted');
	pg_assert_contains('pg_comment_form_save($comment_id, $columns,', $source);

	$quick = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/comment_list_edit.php');
	pg_assert_true((bool) preg_match('/if \(!empty\(\$comment\[\'comments_rating\'\]\)\) \{\s*\$columns\[\'rating\'\] = /', $quick), 'quick edit adds the rating only while the page draws stars');
}

function test_edit_field_writes_quiz_only_when_quiz_rows_are_drawn()
{
	list($source, $update) = hidden_field_kept_test_update('edit_field.php', 'form_fields');

	pg_assert_true($update !== '', 'the field UPDATE is found');
	pg_assert_false(strpos($update, 'quiz_question = ') !== false, 'the UPDATE does not write quiz_question on its own');
	pg_assert_false(strpos($update, 'quiz_answer = ') !== false, 'the UPDATE does not write quiz_answer on its own');
	pg_assert_contains('$sql_quiz', $update, 'the quiz columns go in through their own fragment');
	pg_assert_true((bool) preg_match('/\$sql_quiz = "";\s*if \(\(\$form_type == \'custom\'\) && \(\$quiz == 1\)\) \{/', $source), 'the fragment is built only for a custom form whose quiz is on');
	pg_assert_contains("\$_POST['quiz_answer'] ?? ''", $source, 'a missing answer reads as empty');
}
