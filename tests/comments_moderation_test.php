<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for comment moderation in includes/fn/comments.php: which form record
 * a stored comment belongs to, when its first publish mails the record's
 * submitter, and which moderation paths (edit_comment.php, the external API)
 * rebuild the record's comment count and newest comment; the scheduled publish
 * in job.php uses the same rebuild and writer rule.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A stored comment is about a form record when its row says so: item type
// 'submitted_form' and a positive id. The page type is not asked; a designed
// record page is a 'standard' page.
function test_comments_moderation_record_id()
{
	$cases = array(
		array('submitted_form', 12, 12),
		array('submitted_form', '12', 12),
		array('submitted_form', 0, 0),
		array('submitted_form', -3, 0),
		array('submitted_form', 'x', 0),
		array('submitted_form', null, 0),
		array('', 12, 0),
		array('product', 12, 0),
		array('product_group', 4, 0),
		array('calendar_event', 5, 0),
		array(array(), 5, 0),
		array('submitted_form', array(1), 0),
	);

	foreach ($cases as $case) {
		pg_assert_same($case[2], pg_comment_record_id($case[0], $case[1]), var_export($case[0], true) . ', ' . var_export($case[1], true));
	}
}

// The record's submitter is mailed about a comment's first publish when the
// page has a submitter e-mail page and someone else wrote the comment.
function test_comments_moderation_mail_due()
{
	pg_assert_true(pg_comment_submitter_mail_due('submitted_form', 9, '14', 3, 7), 'a record comment on a designed page');
	pg_assert_true(pg_comment_submitter_mail_due('submitted_form', '9', 14, '3', '7'), 'values as the database returns them');

	foreach (array(0, '0', null, '', array(14)) as $page) {
		pg_assert_false(pg_comment_submitter_mail_due('submitted_form', 9, $page, 3, 7), 'no submitter e-mail page: ' . var_export($page, true));
	}

	pg_assert_false(pg_comment_submitter_mail_due('submitted_form', 9, 14, 7, 7), 'the submitter wrote the comment');
	pg_assert_false(pg_comment_submitter_mail_due('submitted_form', 9, 14, '7', 7), 'the same person, as a string');
	pg_assert_true(pg_comment_submitter_mail_due('submitted_form', 9, 14, 0, 7), 'an anonymous writer');
	pg_assert_true(pg_comment_submitter_mail_due('submitted_form', 9, 14, 0, 0), 'two anonymous people are not the same person');
	pg_assert_true(pg_comment_submitter_mail_due('submitted_form', 9, 14, 3, 0), 'an anonymous submitter');

	pg_assert_false(pg_comment_submitter_mail_due('product', 9, 14, 3, 7), 'a product comment');
	pg_assert_false(pg_comment_submitter_mail_due('', 9, 14, 3, 7), 'a page comment');
	pg_assert_false(pg_comment_submitter_mail_due('submitted_form', 0, 14, 3, 7), 'no record id');
}

// Without a connection the rebuild writes nothing, answers false and does not
// throw, whether or not class db has been declared yet.
function test_comments_moderation_refresh_without_db()
{
	pg_assert_false(pg_comment_refresh_record_info(5, 'submitted_form', 9), 'no connection');

	if (!class_exists('db')) {
		class db
		{
			public static $con;
		}
	}

	pg_assert_false(pg_comment_refresh_record_info(5, 'submitted_form', 9), 'class db without a connection');
	pg_assert_false(pg_comment_refresh_record_info(0, 'submitted_form', 9), 'no page');
	pg_assert_false(pg_comment_refresh_record_info(5, 'product', 9), 'not a record comment');
	pg_assert_false(pg_comment_refresh_record_info(5, 'submitted_form', 0), 'no record id');
	pg_assert_false(pg_comment_refresh_record_info(array(5), 'submitted_form', 9), 'a page id that is not a scalar');
}

// The moderation paths decide from the stored row and rebuild the record's
// counters before their e-mails, whose subject reads them. The scheduled
// publish (job.php) keys on the row's item type and never on the page type.
function test_comments_moderation_paths()
{
	$root = PG_FUNCTIONS_DIR;

	$edit = (string) file_get_contents($root . '/edit_comment.php');
	$call = 'pg_comment_refresh_record_info($page_id, $item_type, $item_id)';

	// A delete rebuilds on the screen; a save rebuilds in the shared
	// pg_comment_form_after_save() (includes/forms/comment_form.php), after
	// the UPDATE.
	pg_assert_same(1, substr_count($edit, $call), 'edit_comment.php rebuilds on delete');

	$delete = strpos($edit, 'DELETE FROM comments WHERE id');
	$first = strpos($edit, $call);
	$save = strpos($edit, 'pg_comment_form_save(');
	$after = strpos($edit, 'pg_comment_form_after_save($row, $new_published)');

	pg_assert_true(($delete !== false) && ($first !== false) && ($delete < $first), 'after the delete');
	pg_assert_true(($save !== false) && ($after !== false) && ($save < $after), 'the save sets off its rebuild and e-mails after the UPDATE');
	pg_assert_true(strpos($edit, 'forms.user_id AS submitter_user_id') !== false, 'the record submitter is read with the comment');

	$form = (string) file_get_contents($root . '/includes/forms/comment_form.php');
	$form_refresh = strpos($form, "pg_comment_refresh_record_info(\$comment['page_id'], \$comment['item_type'], \$comment['item_id'])");
	$form_mail = strpos($form, 'send_comment_email_to_custom_form_submitter(');

	pg_assert_true(($form_refresh !== false) && ($form_mail !== false) && ($form_refresh < $form_mail), 'the rebuild comes before the e-mails');
	pg_assert_true(strpos($form, "pg_comment_submitter_mail_due(\$comment['item_type'], \$comment['item_id'], \$comment['comments_submitter_email_page_id'], (int) \$comment['created_user_id'], (int) \$comment['submitter_user_id'])") !== false, 'the submitter e-mail follows the writer rule');
	pg_assert_true(strpos((string) file_get_contents($root . '/includes/fn/comment_list_edit.php'), 'forms.user_id AS submitter_user_id') !== false, 'the list reads the record submitter too');
	pg_assert_false(strpos($edit, "'form item view'") !== false, 'edit_comment.php does not ask the page type');
	pg_assert_false(strpos($edit, 'INSERT INTO submitted_form_info') !== false, 'and keeps no counter copy of its own');

	$api = (string) file_get_contents($root . '/includes/api/resources/comments.php');

	pg_assert_same(2, substr_count($api, 'pg_comment_refresh_record_info('), 'the API rebuilds on update and on delete');

	$api_update = strpos($api, 'api_exec("UPDATE comments SET');
	$api_first = strpos($api, 'pg_comment_refresh_record_info(');
	$api_mail = strpos($api, 'send_comment_email_to_custom_form_submitter(');
	$api_delete = strpos($api, 'api_exec("DELETE FROM comments WHERE id');
	$api_second = ($api_first !== false) ? strpos($api, 'pg_comment_refresh_record_info(', $api_first + 1) : false;
	$api_due = strpos($api, 'pg_comment_submitter_mail_due(');
	$api_dry = strpos($api, "api_dry_run_stop('updated'");
	$api_found = ($api_update !== false) && ($api_first !== false) && ($api_mail !== false) && ($api_delete !== false) && ($api_second !== false);

	pg_assert_true($api_found && ($api_update < $api_first) && ($api_first < $api_mail), 'an update rebuilds before its e-mails');
	pg_assert_true($api_found && ($api_delete < $api_second), 'a delete rebuilds after the delete');
	pg_assert_true(($api_due !== false) && ($api_dry !== false) && ($api_due < $api_dry), 'the dry run reports e-mails by the same rule');
	pg_assert_false(strpos($api, "'form item view'") !== false, 'the API does not ask the page type');
	pg_assert_false(strpos($api, 'INSERT INTO submitted_form_info') !== false, 'and keeps no counter copy of its own');
	pg_assert_false(strpos($api, 'function api_comment_refresh_form_info') !== false, 'its old copy is gone');

	$job = (string) file_get_contents($root . '/job.php');
	$due = strpos($job, '(publish_date_and_time <= NOW())');
	$select = ($due !== false) ? strrpos(substr($job, 0, $due), 'SELECT') : false;
	$from = ($select !== false) ? strpos($job, 'FROM comments', $select) : false;

	pg_assert_true(($select !== false) && ($from !== false) && (strpos(substr($job, $select, $from - $select), 'item_type') !== false), 'the scheduled publish reads the item type');

	// Only the scheduled-comment block, from its SELECT to its watcher e-mail:
	// the rest of the job (designer pages, campaigns, calendar events) may
	// read a page's type for its own work.
	$watchers = ($select !== false) ? strpos($job, 'send_comment_email_to_watchers(', $select) : false;
	$loop = ($watchers !== false) ? substr($job, $select, $watchers - $select) : '';

	$refresh = strpos($loop, 'pg_comment_refresh_record_info(');
	$admin = strpos($loop, 'send_comment_email_to_administrators(');
	$due = strpos($loop, 'pg_comment_submitter_mail_due(');
	$submitter = strpos($loop, 'send_comment_email_to_custom_form_submitter(');

	pg_assert_true($refresh !== false, 'the scheduled-comment block is found and rebuilds through the helper');
	pg_assert_false(strpos($loop, 'page_type') !== false, 'and never the page type');
	pg_assert_false(strpos($loop, 'INSERT INTO submitted_form_info') !== false, 'the scheduled publish keeps no counter copy of its own');
	pg_assert_true(($refresh !== false) && ($admin !== false) && ($submitter !== false) && ($refresh < $admin) && ($refresh < $submitter), 'it rebuilds before the e-mails');
	pg_assert_true(($due !== false) && ($submitter !== false) && ($due < $submitter) && (strpos(substr($loop, $due, $submitter - $due), '{') !== false), 'the submitter e-mail sits inside the writer rule');
	pg_assert_true(($select !== false) && ($from !== false) && (strpos(substr($job, $select, $from - $select), 'created_user_id') !== false), 'the scheduled publish reads the writer');
}
