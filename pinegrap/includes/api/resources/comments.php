<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Comments: what visitors wrote under pages, form records, products and
// events, and the moderation the comments screen does with it.
//
// A comment is published, held for moderation (pending) or scheduled to be
// published at a time. Moderation here is the comments screen's: publish or
// hold it, feature it, remove it. The words are the visitor's and are not
// rewritten through the API.
//
// Publishing one does what the screen does when it publishes: the people
// watching the page, and the person who submitted the form record the comment
// is about, get their e-mail, and a form record's comment count is brought up
// to date for the form lists.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

function api_comment_select() {

	return "SELECT comments.id, comments.page_id, comments.item_id, comments.item_type, comments.name,
			comments.message, comments.rating, comments.published, comments.publish_date_and_time,
			comments.publish_cancel, comments.featured, comments.file_id, comments.created_user_id,
			comments.created_timestamp, comments.last_modified_timestamp,
			page.page_name, page.page_type, page.comments_submitter_email_page_id,
			page.comments_watcher_email_page_id,
			files.name AS file_name
		FROM comments
		LEFT JOIN page ON page.page_id = comments.page_id
		LEFT JOIN files ON files.id = comments.file_id";

}

// No schedule is written as the zero date.
function api_comment_scheduled_at($row) {

	$value = (string)$row['publish_date_and_time'];

	if (($value === '') || (strpos($value, '0000-00-00') === 0)) {

		return 0;

	}

	$unix = strtotime($value);

	return ($unix > 0) ? $unix : 0;

}

function api_comment_status($row) {

	if ((int)$row['published'] === 1) {

		return 'published';

	}

	return (api_comment_scheduled_at($row) > 0) ? 'scheduled' : 'pending';

}

function api_comment_present($row) {

	$scheduled = api_comment_scheduled_at($row);

	$status = api_comment_status($row);

	$base = URL_SCHEME . HOSTNAME_SETTING . PATH;

	return array(
		'id'              => (int)$row['id'],
		'page_id'         => (int)$row['page_id'],
		'page_name'       => isset($row['page_name']) ? (string)$row['page_name'] : null,
		'item_type'       => ((string)$row['item_type'] !== '') ? (string)$row['item_type'] : null,
		'item_id'         => ((int)$row['item_id'] > 0) ? (int)$row['item_id'] : null,
		'name'            => (string)$row['name'],
		'message'         => (string)$row['message'],
		'rating'          => ((int)$row['rating'] > 0) ? (int)$row['rating'] : null,
		'status'          => $status,
		'publish_at'      => ($status === 'scheduled') ? api_time($scheduled) : null,
		'cancel_if_newer' => ($status === 'scheduled') && ((int)$row['publish_cancel'] === 1),
		'featured'        => ((int)$row['featured'] === 1),
		'file_url'        => ((string)$row['file_name'] !== '') ? $base . encode_url_path($row['file_name']) : null,
		'user_id'         => ((int)$row['created_user_id'] > 0) ? (int)$row['created_user_id'] : null,
		'created_at'      => api_time($row['created_timestamp']),
		'created_at_unix' => (int)$row['created_timestamp'],
		'updated_at'      => api_time($row['last_modified_timestamp']),
		'updated_at_unix' => (int)$row['last_modified_timestamp']
	);

}

function api_comment_schema() {

	return array(
		'id'              => 'integer',
		'page_id'         => 'integer',
		'page_name'       => 'string?',
		'item_type'       => 'string?',
		'item_id'         => 'integer?',
		'name'            => 'string',
		'message'         => 'string',
		'rating'          => 'integer?',
		'status'          => 'string',
		'publish_at'      => 'string?',
		'cancel_if_newer' => 'boolean',
		'featured'        => 'boolean',
		'file_url'        => 'string?',
		'user_id'         => 'integer?',
		'created_at'      => 'string?',
		'created_at_unix' => 'integer',
		'updated_at'      => 'string?',
		'updated_at_unix' => 'integer'
	);

}

function api_comment_load_or_fail($id) {

	$row = api_row(api_comment_select() . " WHERE comments.id = '" . (int)$id . "' LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Comment'));

	}

	return $row;

}

// Newest first.
function api_comments_list($params) {

	$where = array();

	if (isset($params['page_id'])) {

		$where[] = "comments.page_id = '" . (int)$params['page_id'] . "'";

	}

	if (isset($params['item_type'])) {

		$where[] = "comments.item_type = '" . escape($params['item_type'] === 'page' ? '' : $params['item_type']) . "'";

	}

	if (isset($params['item_id'])) {

		$where[] = "comments.item_id = '" . (int)$params['item_id'] . "'";

	}

	if (isset($params['status']) && ($params['status'] !== 'all')) {

		$zero = "(comments.publish_date_and_time = '0000-00-00 00:00:00' OR comments.publish_date_and_time IS NULL)";

		switch ($params['status']) {

			case 'published':
				$where[] = "comments.published = '1'";
				break;

			case 'pending':
				$where[] = "comments.published = '0' AND " . $zero;
				break;

			case 'scheduled':
				$where[] = "comments.published = '0' AND NOT " . $zero;
				break;

		}

	}

	if (isset($params['featured'])) {

		$where[] = "comments.featured = '" . ($params['featured'] ? '1' : '0') . "'";

	}

	if (isset($params['q']) && $params['q'] !== '') {

		$q = escape(escape_like($params['q']));

		$where[] = "(comments.name LIKE '%" . $q . "%' OR comments.message LIKE '%" . $q . "%')";

	}

	if (isset($params['created_since'])) {

		$where[] = "comments.created_timestamp >= '" . (int)$params['created_since'] . "'";

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "comments.id < '" . (int)$cursor['i'] . "'";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows(api_comment_select()
		. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)) . "
		ORDER BY comments.id DESC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_comment_present($row);

	}

	api_ok_list($out, $limit, $next);

}

function api_comments_get($params) {

	api_ok(api_comment_present(api_comment_load_or_fail($params['id'])));

}

// A form record's comment count and newest comment, which the form lists
// read instead of counting (edit_comment.php keeps the same row).
function api_comment_refresh_form_info($row) {

	if ((string)$row['page_type'] !== 'form item view') {

		return;

	}

	$page_id = (int)$row['page_id'];

	$item_id = (int)$row['item_id'];

	$item_type = escape((string)$row['item_type']);

	$views = (int)api_value("SELECT number_of_views FROM submitted_form_info
		WHERE submitted_form_id = '" . $item_id . "' AND page_id = '" . $page_id . "' LIMIT 1");

	$published = "comments.page_id = '" . $page_id . "' AND comments.item_id = '" . $item_id . "'
		AND comments.item_type = '" . $item_type . "' AND comments.published = '1'";

	$count = (int)api_value("SELECT COUNT(*) FROM comments WHERE " . $published);

	$newest = ($count > 0) ? (int)api_value("SELECT id FROM comments WHERE " . $published . " ORDER BY created_timestamp DESC LIMIT 1") : 0;

	api_exec("DELETE FROM submitted_form_info WHERE submitted_form_id = '" . $item_id . "' AND page_id = '" . $page_id . "'");

	api_exec("INSERT INTO submitted_form_info (submitted_form_id, page_id, number_of_views, number_of_comments, newest_comment_id)
		VALUES ('" . $item_id . "', '" . $page_id . "', '" . $views . "', '" . $count . "', '" . ($newest > 0 ? $newest : '') . "')");

}

function api_comments_update($params) {

	$app = api_current_app();

	$id = (int)$params['id'];

	$row = api_comment_load_or_fail($id);

	$set = array();

	$was_published = ((int)$row['published'] === 1);

	$publishing = false;

	if (isset($params['published'])) {

		if ($params['published']) {

			$set['published'] = '1';
			$publishing = !$was_published;

		} else {

			$set['published'] = '0';

		}

		// Either way the schedule is over: published now, or held until a
		// person publishes it.
		$set['publish_date_and_time'] = '';
		$set['publish_cancel'] = '0';

	}

	if (isset($params['featured'])) {

		$set['featured'] = $params['featured'] ? '1' : '0';

	}

	if (empty($set)) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	api_dry_run_stop('updated', 'comment', array(
		'id'      => $id,
		'page_id' => (int)$row['page_id'],
		'changes' => array_keys($set),
		'emails'  => $publishing && (((int)$row['comments_watcher_email_page_id'] > 0) || (((string)$row['page_type'] === 'form item view') && ((int)$row['comments_submitter_email_page_id'] > 0)))
	));

	$sql = array();

	foreach ($set as $column => $value) {

		$sql[] = $column . " = '" . escape($value) . "'";

	}

	api_exec("UPDATE comments SET " . implode(', ', $sql) . ",
			last_modified_user_id = '" . (int)$app['owner']['id'] . "', last_modified_timestamp = UNIX_TIMESTAMP()
		WHERE id = '" . $id . "'");

	log_activity(lang(array(
		'string' => 'A comment on page ({var:1}) was changed through the API by the application {var:2} (key {var:3}).',
		'vars'   => array((string)$row['page_name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	// What the comments screen sends when it publishes a comment.
	if ($publishing) {

		if (((string)$row['page_type'] === 'form item view') && ((int)$row['comments_submitter_email_page_id'] > 0)) {

			send_comment_email_to_custom_form_submitter($id);

		}

		if ((int)$row['comments_watcher_email_page_id'] > 0) {

			send_comment_email_to_watchers($id);

		}

	}

	if (isset($set['published'])) {

		api_comment_refresh_form_info($row);

	}

	api_ok(api_comment_present(api_comment_load_or_fail($id)));

}

// As on the comments screen: the attachment goes with it unless another
// comment shares the file (duplicated pages copy their comments), and so
// does the panel notification about it.
function api_comments_delete($params) {

	$app = api_current_app();

	$id = (int)$params['id'];

	$row = api_comment_load_or_fail($id);

	$file_id = (int)$row['file_id'];

	$file_name = (string)$row['file_name'];

	$drop_file = ($file_id > 0) && ($file_name !== '')
		&& ((int)api_value("SELECT COUNT(*) FROM comments WHERE file_id = '" . $file_id . "' AND id <> '" . $id . "'") === 0);

	api_dry_run_stop('deleted', 'comment', array('id' => $id, 'page_id' => (int)$row['page_id'], 'file_deleted' => $drop_file));

	if ($drop_file) {

		api_exec("DELETE FROM files WHERE id = '" . $file_id . "'");

		@unlink(FILE_DIRECTORY_PATH . '/' . $file_name);

	}

	api_exec("DELETE FROM comments WHERE id = '" . $id . "'");

	api_exec("DELETE FROM notifications WHERE comment_id = '" . $id . "'");

	api_comment_refresh_form_info($row);

	log_activity(lang(array(
		'string' => 'A comment on page ({var:1}) was deleted through the API by the application {var:2} (key {var:3}).',
		'vars'   => array((string)$row['page_name'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_ok(array('id' => $id, 'deleted' => true));

}
