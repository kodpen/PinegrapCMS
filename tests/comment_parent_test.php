<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the comment a reply may answer (includes/fn/comments.php): the
 * posted parent id (pg_comment_parent_posted_id()), the decision
 * (pg_comment_parent_ok(): the parent exists, sits in the same thread and is
 * visible to the writer), the reader and the ?reply_to= check without a
 * database, the "In reply to" line of the staff e-mail, add_comment.php's
 * wiring, and that deleting a comment never deletes its replies.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A parent row as pg_comment_parent_row() reads it: comment 9 on the record
// 12 of page 5, published unless told otherwise.
function comment_parent_test_row($published = '1', $page_id = 5, $item_type = 'submitted_form', $item_id = 12)
{
	return array(
		'id' => '9',
		'page_id' => (string) $page_id,
		'item_type' => $item_type,
		'item_id' => (string) $item_id,
		'published' => $published,
		'name' => 'Jane',
	);
}

function comment_parent_test_check($posted, $parent, $page_id = 5, $item_type = 'submitted_form', $item_id = 12, $viewer = array())
{
	return pg_comment_parent_ok($posted, $parent, $page_id, $item_type, $item_id, $viewer);
}

function comment_parent_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// Whether a whole file holds $needle, without printing the file on a failure.
function comment_parent_test_has($needle, $haystack, $label)
{
	pg_assert_true(strpos($haystack, $needle) !== false, $label . ' (' . $needle . ')');
}

function test_comment_parent_posted_id()
{
	foreach (array(null, '', '  ', '0', 0, '00') as $value) {
		pg_assert_same(0, pg_comment_parent_posted_id($value), 'none: ' . var_export($value, true));
	}

	foreach (array('12', 12, ' 12 ') as $value) {
		pg_assert_same(12, pg_comment_parent_posted_id($value), 'an id: ' . var_export($value, true));
	}

	foreach (array(array('5'), 'abc', '-3', -3, '1.5', 1.5, '5x', '12345678901', true, false) as $value) {
		pg_assert_same(-1, pg_comment_parent_posted_id($value), 'malformed: ' . var_export($value, true));
	}
}

function test_comment_parent_top_level_accepted()
{
	foreach (array(null, '', '0', 0) as $posted) {
		pg_assert_same(
			array('accept' => true, 'refusal' => '', 'parent_id' => 0),
			comment_parent_test_check($posted, comment_parent_test_row()),
			'top level: ' . var_export($posted, true)
		);
	}
}

// A malformed value is refused, never stored quietly as a top-level comment.
function test_comment_parent_malformed_refused()
{
	foreach (array(array('5'), 'abc', '-3', '1.5') as $posted) {
		pg_assert_same(
			array('accept' => false, 'refusal' => 'value', 'parent_id' => 0),
			comment_parent_test_check($posted, comment_parent_test_row()),
			'malformed: ' . var_export($posted, true)
		);
	}
}

function test_comment_parent_missing_refused()
{
	pg_assert_same('missing', comment_parent_test_check('9', null)['refusal'], 'no row');

	$other = comment_parent_test_row();
	$other['id'] = '8';

	$check = comment_parent_test_check('9', $other);

	pg_assert_same('missing', $check['refusal'], 'a row of another comment');
	pg_assert_false($check['accept'], 'refused');
	pg_assert_same(0, $check['parent_id'], 'no parent');
}

function test_comment_parent_thread_rules()
{
	$parent = comment_parent_test_row();

	pg_assert_same(
		array('accept' => true, 'refusal' => '', 'parent_id' => 9),
		comment_parent_test_check('9', $parent, 5, 'submitted_form', 12),
		'the same thread'
	);

	pg_assert_true(comment_parent_test_check('9', $parent, '5', 'submitted_form', '12')['accept'], 'posted strings');

	pg_assert_same('thread', comment_parent_test_check('9', $parent, 6, 'submitted_form', 12)['refusal'], 'another page');
	pg_assert_same('thread', comment_parent_test_check('9', $parent, 5, '', 12)['refusal'], 'the page thread');
	pg_assert_same('thread', comment_parent_test_check('9', $parent, 5, 'product', 12)['refusal'], 'another item type');
	pg_assert_same('thread', comment_parent_test_check('9', $parent, 5, 'submitted_form', 13)['refusal'], 'another record');

	$page_parent = comment_parent_test_row('1', 5, '', 0);

	pg_assert_true(comment_parent_test_check('9', $page_parent, 5, null, null)['accept'], 'the page thread, nothing posted for the item');
	pg_assert_true(comment_parent_test_check('9', $page_parent, 5, '', '0')['accept'], 'the page thread, posted empty');
}

function test_comment_parent_visibility_rules()
{
	$held = comment_parent_test_row('0');

	pg_assert_same('hidden', comment_parent_test_check('9', $held)['refusal'], 'held, nobody');
	pg_assert_true(comment_parent_test_check('9', $held, 5, 'submitted_form', 12, array('own_ids' => array(9)))['accept'], 'the writer\'s own pending comment');
	pg_assert_true(comment_parent_test_check('9', $held, 5, 'submitted_form', 12, array('own_ids' => array('9')))['accept'], 'own ids as the session keeps them');
	pg_assert_same('hidden', comment_parent_test_check('9', $held, 5, 'submitted_form', 12, array('own_ids' => array(8)))['refusal'], 'another own comment');
	pg_assert_true(comment_parent_test_check('9', $held, 5, 'submitted_form', 12, array('staff' => true))['accept'], 'staff');
	pg_assert_true(comment_parent_test_check('9', $held, 5, 'submitted_form', 12, array('form_editor' => true))['accept'], 'the form editor');

	pg_assert_true(comment_parent_test_check('9', comment_parent_test_row(1))['accept'], 'published as an integer');

	$scheduled = comment_parent_test_row('0');
	$scheduled['publish_date_and_time'] = '2030-01-01 10:00:00';

	pg_assert_same('hidden', comment_parent_test_check('9', $scheduled)['refusal'], 'scheduled is not published yet');
}

function test_comment_parent_thread_before_visibility()
{
	pg_assert_same('thread', comment_parent_test_check('9', comment_parent_test_row('0', 6))['refusal'], 'held in another thread');
}

function test_comment_parent_viewer_shapes()
{
	$held = comment_parent_test_row('0');

	foreach (array(null, 'x', array('own_ids' => 'x'), array('own_ids' => array(array(9)))) as $viewer) {
		pg_assert_same('hidden', comment_parent_test_check('9', $held, 5, 'submitted_form', 12, $viewer)['refusal'], 'viewer ' . var_export($viewer, true));
	}
}

// Without the column (before 11.0.31) or a connection, no parent is read and
// a reply is refused rather than stored as a top-level comment.
function test_comment_parent_without_db()
{
	pg_assert_false(pg_comment_replies_ready(), 'not ready without a connection');
	pg_assert_same(null, pg_comment_parent_row('9'), 'no row without a connection');
	pg_assert_same(null, pg_comment_parent_row('abc'), 'no row for a value that is not an id');

	foreach (array(null, '', '0', 'abc', array('9')) as $value) {
		pg_assert_same(null, pg_comment_reply_to($value, 5, 'submitted_form', 12, array('staff' => true)), 'reply_to ' . var_export($value, true));
	}

	pg_assert_same(null, pg_comment_reply_to('9', 5, 'submitted_form', 12, array('staff' => true)), 'an id, even for staff');
}

function test_comment_parent_mail_line()
{
	pg_assert_same('', pg_comment_parent_mail_line(null), 'no parent');
	pg_assert_same('', pg_comment_parent_mail_line(array()), 'an empty row');
	pg_assert_same('', pg_comment_parent_mail_line(array('id' => 0, 'name' => 'Jane')), 'no id');
	pg_assert_same("In reply to: Jane (#9)\n\n", pg_comment_parent_mail_line(array('id' => 9, 'name' => 'Jane')), 'the line');
	pg_assert_same("In reply to: Jane (#9)\n\n", pg_comment_parent_mail_line(array('id' => '9', 'name' => 'Jane')), 'the id as a string');
	pg_assert_same("In reply to: Jane  Doe (#9)\n\n", pg_comment_parent_mail_line(array('id' => 9, 'name' => "Jane\r\nDoe")), 'a name stays on one line');
	pg_assert_same("In reply to:  (#9)\n\n", pg_comment_parent_mail_line(array('id' => 9, 'name' => array('Jane'))), 'a name that is not a value');
}

// add_comment.php reads the posted parent, asks after the page gates, the
// thread refusals and the record view check, and before the review gate, the
// upload and the INSERT; a refusal goes back to the form with what was typed.
function test_comment_parent_add_comment_wiring()
{
	$add = comment_parent_test_source('add_comment.php');

	comment_parent_test_has("\$parent_id_posted = \$liveform->get_field_value('parent_id');", $add, 'the posted parent is read');
	pg_assert_same(1, substr_count($add, 'pg_comment_parent_ok('), 'asked once');

	$check = strpos($add, 'pg_comment_parent_ok(');
	$item_refusal = strpos($add, "\$comment_thread['refusal'] === 'item'");
	$record_block = strpos($add, "if (\$item_type == 'submitted_form') {");
	$review_gate = strpos($add, '$is_product_review = pg_review_gate_applies($item_type, $item_id)');
	$upload = strpos($add, 'copy($file_temp_name');
	$insert = strpos($add, 'INSERT INTO comments');
	$found = ($check !== false) && ($item_refusal !== false) && ($record_block !== false) && ($review_gate !== false) && ($upload !== false) && ($insert !== false);

	pg_assert_true($found, 'every anchor is found');
	pg_assert_true($found && ($item_refusal < $check) && ($record_block < $check), 'after the thread refusals and the record view check');
	pg_assert_true($found && ($check < $review_gate) && ($check < $upload) && ($check < $insert), 'before the review gate, the upload and the INSERT');

	$refusal = strpos($add, "if (!\$comment_parent_check['accept']) {");

	pg_assert_true(($refusal !== false) && ($check !== false) && ($check < $refusal), 'the refusal follows the check');

	if ($refusal === false) {
		return;
	}

	$block = substr($add, $refusal);
	$block = substr($block, 0, (int) strpos($block, "\n}\n") + 2);

	pg_assert_contains('$liveform->add_error(', $block, 'a form error');
	pg_assert_contains("\$liveform->set_field('parent_id', array());", $block, 'the parent is dropped from the form state');
	pg_assert_true(preg_match("/^.*Location:.*#software_add_comment'\\);\$/m", $block) === 1, 'back to the form, on one line');
	pg_assert_contains('exit();', $block, 'and nothing after it');
	pg_assert_false(strpos($block, 'output_error(') !== false, 'no error page: the typed text is kept');
	pg_assert_false(strpos($block, 'remove_form(') !== false, 'the form state is kept');

	comment_parent_test_has("if (pg_comment_replies_ready()) {\n    \$sql_parent_column = \",\n        parent_id\";", $add, 'the INSERT names the column only once it exists');

	$columns = substr($add, (int) $insert, (int) strpos($add, 'VALUES', (int) $insert) - (int) $insert);

	pg_assert_false(strpos($columns, 'parent_id') !== false, 'no literal parent_id in the column list');
	comment_parent_test_has('last_modified_timestamp$sql_review_column$sql_parent_column)', $add, 'the column after the review column');
	comment_parent_test_has('UNIX_TIMESTAMP()$sql_review_value$sql_parent_value)', $add, 'the value after the review value');

	pg_assert_same(2, substr_count($add, 'pg_comment_parent_mail_line($comment_parent)'), 'both staff e-mails name the parent');
}

// The staff e-mail sent when a scheduled comment is published (job.php)
// names the parent as add_comment.php's own e-mails do. The column is read
// only once it is there, and a comment that answers none gets '' (its body
// stays as it was, the product comment of submit_order.php included).
function test_comment_parent_published_mail_names_the_parent()
{
	$mail = comment_parent_test_source('includes/fn/mail.php');
	$start = strpos($mail, 'function send_comment_email_to_administrators(');
	$end = ($start !== false) ? strpos($mail, "\nfunction ", $start + 1) : false;

	pg_assert_true(($start !== false) && ($end !== false), 'the function is found');

	if (($start === false) || ($end === false)) {
		return;
	}

	$function = substr($mail, $start, $end - $start);

	comment_parent_test_has("\$parent_column = pg_comment_replies_ready() ? ',\n\n            parent_id' : '';", $function, 'the column is named only once it exists');
	comment_parent_test_has('message$parent_column', $function, 'read with the comment');
	comment_parent_test_has("\$parent = (!empty(\$comment['parent_id'])) ? pg_comment_parent_row(\$comment['parent_id']) : null;", $function, 'the parent is read only for a reply');

	$display = strpos($function, "lang('Display Name') . ': ' . \$comment['name'] . \"\\n\" . \"\\n\" . pg_comment_parent_mail_line(\$parent) . \$comment_label . ': ' . \$comment['message']");

	pg_assert_true($display !== false, 'the line sits between the display name and the comment, as in add_comment.php');
}

// A copied page keeps who answered whom: a reply on the copy names the copy
// of the comment it answers. Pure.
function test_comment_parent_copy_parent_id()
{
	$copied = array(9 => 31, 10 => 32);

	pg_assert_same(31, pg_comment_copy_parent_id(9, $copied), 'the copy of the comment it answers');
	pg_assert_same(32, pg_comment_copy_parent_id('10', $copied), 'an id as a string');
	pg_assert_same(0, pg_comment_copy_parent_id(0, $copied), 'a comment that answers none');
	pg_assert_same(0, pg_comment_copy_parent_id('', $copied), 'an empty value');
	pg_assert_same(0, pg_comment_copy_parent_id(null, $copied), 'no value (before 11.0.31)');
	pg_assert_same(0, pg_comment_copy_parent_id(7, $copied), 'a deleted comment, or one not on the page: answers none');
	pg_assert_same(0, pg_comment_copy_parent_id(array(9), $copied), 'not a value');
	pg_assert_same(0, pg_comment_copy_parent_id(9, null), 'no copies yet');
	pg_assert_same(0, pg_comment_copy_parent_id(9, array(9 => 0)), 'a copy that was not stored');
}

// duplicate_page_f.php copies the comments oldest first, keeps the new id of
// each and writes the copied parent, naming the column only once it exists.
function test_comment_parent_page_copy_wiring()
{
	$copy = comment_parent_test_source('duplicate_page_f.php');

	comment_parent_test_has('$query = "SELECT * FROM comments WHERE page_id = ? ORDER BY id";', $copy, 'oldest first, so a comment is copied before its replies');
	comment_parent_test_has('$comment_replies = pg_comment_replies_ready();', $copy, 'the column is asked for once');
	comment_parent_test_has("pg_comment_copy_parent_id(isset(\$comment['parent_id']) ? \$comment['parent_id'] : 0, \$comment_copies)", $copy, 'the parent is mapped to its copy');
	comment_parent_test_has("\$comment_copies[(int) \$comment['id']] = (int) mysqli_insert_id(db::\$con);", $copy, 'the new id of each copy is kept');
	comment_parent_test_has('last_modified_timestamp$sql_parent_column)', $copy, 'the column after the last one');
	comment_parent_test_has('?$sql_parent_value)";', $copy, 'the value after the last one');
	comment_parent_test_has("(string) \$comment['last_modified_timestamp'])) or output_error", $copy, 'the last value bound before the parent');

	$insert = strpos($copy, 'INSERT INTO comments');
	$columns = ($insert !== false) ? substr($copy, $insert, (int) strpos($copy, 'VALUES', $insert) - $insert) : '';

	pg_assert_false(strpos($columns, 'parent_id') !== false, 'no literal parent_id in the column list');
}

// Deleting a comment leaves its replies; they read as comments of their own.
function test_comment_parent_deletes_keep_replies()
{
	foreach (array('edit_comment.php', 'includes/api/resources/comments.php', 'edit_page.php', 'edit_pages.php', 'includes/explorer/recycle_bin.php') as $file) {
		$source = comment_parent_test_source($file);

		pg_assert_true(strpos($source, 'DELETE FROM comments') !== false, $file . ' deletes comments');

		preg_match_all('/DELETE FROM comments[^\n]*/', $source, $statements);

		foreach ($statements[0] as $statement) {
			pg_assert_false(strpos($statement, 'parent_id') !== false, $file . ': ' . $statement);
		}
	}

	comment_parent_test_has('DELETE FROM comments WHERE id', comment_parent_test_source('edit_comment.php'), 'edit_comment.php deletes one comment');
	comment_parent_test_has('api_exec("DELETE FROM comments WHERE id', comment_parent_test_source('includes/api/resources/comments.php'), 'the API deletes one comment');
}
