<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// E-mail campaigns: a message, who it goes to, when, and how far the sending
// job has got.
//
// Read, held, released, cancelled, corrected and removed here as on the
// campaign screens; a campaign is put together on its own screen, where the
// recipients are picked from contact groups. An account with the e-mail right
// but the user role reaches only the campaigns it created, as on those
// screens.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// The campaigns an owner may reach: all of them from the manager role up, its
// own below that.
function api_campaign_owner_filter($owner) {

	if ((int)$owner['role'] < 3) {

		return '';

	}

	return "email_campaigns.created_user_id = '" . (int)$owner['id'] . "'";

}

function api_campaign_select() {

	return "SELECT email_campaigns.*, page.page_name, email_campaign_profiles.name AS profile_name,
			created_user.user_username AS created_username
		FROM email_campaigns
		LEFT JOIN page ON page.page_id = email_campaigns.page_id
		LEFT JOIN email_campaign_profiles ON email_campaign_profiles.id = email_campaigns.email_campaign_profile_id
		LEFT JOIN user AS created_user ON created_user.user_id = email_campaigns.created_user_id";

}

// When the job may start sending, as a unix time; 0 when no time is set,
// which means as soon as the job reaches it. The column holds the site's
// wall-clock time, which the job compares with NOW() on a connection set to
// the same offset.
function api_campaign_start_unix($value) {

	$value = (string)$value;

	if (($value === '') || (strpos($value, '0000-00-00') === 0) || (strpos($value, '1000-01-01') === 0)) {

		return 0;

	}

	$time = strtotime($value);

	return ($time === false) ? 0 : (int)$time;

}

// The column value for send_at: the column's own default when it is cleared,
// which the job reads as "now" (it sorts before any NOW()).
function api_campaign_start_time($send_at) {

	if (($send_at === '') || ($send_at === null)) {

		return '1000-01-01 00:00:00';

	}

	return date('Y-m-d H:i:s', (int)$send_at);

}

// One campaign's totals from the grouped count row. A recipient the job gave
// up on is marked complete as well, so the campaign can finish; it is counted
// as failed, never as sent.
function api_campaign_counts_from_row($row) {

	$total = (int)$row['total'];

	$sent = (int)$row['sent'];

	$failed = (int)$row['failed'];

	return array(
		'total'   => $total,
		'sent'    => $sent,
		'failed'  => $failed,
		'pending' => max(0, $total - $sent - $failed)
	);

}

// How far sending has got, for many campaigns in one query. Before the retry
// columns exist nothing is ever failed.
function api_campaign_recipient_counts($ids) {

	$counts = array();

	foreach ($ids as $id) {

		$counts[(int)$id] = array('total' => 0, 'sent' => 0, 'failed' => 0, 'pending' => 0);

	}

	if (empty($counts)) {

		return $counts;

	}

	$failed = pg_email_retry_ready() ? 'failed' : '0';

	foreach (api_rows("SELECT email_campaign_id,
			COUNT(*) AS total,
			SUM(CASE WHEN complete = 1 AND " . $failed . " = 0 THEN 1 ELSE 0 END) AS sent,
			SUM(CASE WHEN " . $failed . " = 1 THEN 1 ELSE 0 END) AS failed
		FROM email_recipients
		WHERE email_campaign_id IN (" . implode(',', array_map('intval', array_keys($counts))) . ")
		GROUP BY email_campaign_id") as $row) {

		$counts[(int)$row['email_campaign_id']] = api_campaign_counts_from_row($row);

	}

	return $counts;

}

function api_campaign_present($row, $recipients) {

	$automatic = ((string)$row['type'] === 'automatic');

	return array(
		'id'              => (int)$row['id'],
		'subject'         => (string)$row['subject'],
		// ready (waiting for its time, or sending), paused, cancelled or complete.
		'status'          => (string)$row['status'],
		// manual: sent to contact groups from the campaign screen; automatic:
		// made by a campaign profile when something happened (an order, a
		// reservation, a form).
		'type'            => (string)$row['type'],
		'purpose'         => (string)$row['purpose'],
		'format'          => (string)$row['format'],
		'from_name'       => (string)$row['from_name'],
		'from_email'      => (string)$row['from_email_address'],
		'reply_to'        => ((string)$row['reply_email_address'] !== '') ? (string)$row['reply_email_address'] : null,
		'bcc'             => ((string)$row['bcc_email_address'] !== '') ? (string)$row['bcc_email_address'] : null,
		// The page an HTML campaign is made of.
		'page_id'         => ((int)$row['page_id'] > 0) ? (int)$row['page_id'] : null,
		'page_name'       => ((int)$row['page_id'] > 0 && (string)$row['page_name'] !== '') ? (string)$row['page_name'] : null,
		'send_at'         => api_time(api_campaign_start_unix($row['start_time'])),
		'profile_id'      => ($automatic && (int)$row['email_campaign_profile_id'] > 0) ? (int)$row['email_campaign_profile_id'] : null,
		'profile_name'    => ($automatic && (string)$row['profile_name'] !== '') ? (string)$row['profile_name'] : null,
		'action'          => ((string)$row['action'] !== '') ? (string)$row['action'] : null,
		'action_item_id'  => ((int)$row['action_item_id'] > 0) ? (int)$row['action_item_id'] : null,
		'order_id'        => ((int)$row['order_id'] > 0) ? (int)$row['order_id'] : null,
		'recipients'      => $recipients,
		'created_by'      => ((string)$row['created_username'] !== '') ? (string)$row['created_username'] : null,
		'created_at'      => api_time($row['created_timestamp']),
		'updated_at'      => api_time($row['last_modified_timestamp']),
		'updated_at_unix' => (int)$row['last_modified_timestamp']
	);

}

function api_campaign_schema() {

	return array(
		'id'               => 'integer',
		'subject'          => 'string',
		'status'           => 'string',
		'type'             => 'string',
		'purpose'          => 'string',
		'format'           => 'string',
		'from_name'        => 'string',
		'from_email'       => 'string',
		'reply_to'         => 'string?',
		'bcc'              => 'string?',
		'page_id'          => 'integer?',
		'page_name'        => 'string?',
		'send_at'          => 'string?',
		'profile_id'       => 'integer?',
		'profile_name'     => 'string?',
		'action'           => 'string?',
		'action_item_id'   => 'integer?',
		'order_id'         => 'integer?',
		'recipients'       => array('total' => 'integer', 'sent' => 'integer', 'failed' => 'integer', 'pending' => 'integer'),
		'created_by'       => 'string?',
		'created_at'       => 'string?',
		'updated_at'       => 'string?',
		'updated_at_unix'  => 'integer',
		// The single read adds the message and the contact groups a manual
		// campaign was put together from: the groups it went to and the groups
		// whose members were left out.
		'body'             => 'string',
		'contact_group_ids' => 'integer[]',
		'excluded_contact_group_ids' => 'integer[]'
	);

}

function api_campaign_load_or_fail($id) {

	$where = array("email_campaigns.id = '" . (int)$id . "'");

	$app = api_current_app();

	$owner = api_campaign_owner_filter($app['owner']);

	if ($owner !== '') {

		$where[] = $owner;

	}

	$row = api_row(api_campaign_select() . " WHERE " . implode(' AND ', $where) . " LIMIT 1");

	if ($row === null) {

		api_fail_not_found(lang('Campaign'));

	}

	return $row;

}

function api_campaign_answer($id) {

	$row = api_campaign_load_or_fail($id);

	$counts = api_campaign_recipient_counts(array($row['id']));

	$out = api_campaign_present($row, $counts[(int)$row['id']]);

	// For an HTML campaign, the page as it was rendered when the campaign was
	// made, with its links already rewritten for tracking: what goes out.
	$out['body'] = (string)$row['body'];

	$out['contact_group_ids'] = array();

	$out['excluded_contact_group_ids'] = array();

	foreach (api_rows("SELECT contact_group_id, type FROM contact_groups_email_campaigns_xref
		WHERE email_campaign_id = '" . (int)$row['id'] . "'
		ORDER BY contact_group_id ASC") as $group) {

		$key = ((string)$group['type'] === 'excluded') ? 'excluded_contact_group_ids' : 'contact_group_ids';

		$out[$key][] = (int)$group['contact_group_id'];

	}

	api_ok($out);

}

// Newest first.
function api_campaigns_list($params) {

	$where = array();

	$app = api_current_app();

	$owner = api_campaign_owner_filter($app['owner']);

	if ($owner !== '') {

		$where[] = $owner;

	}

	foreach (array('status', 'type', 'purpose') as $field) {

		if (isset($params[$field])) {

			$where[] = "email_campaigns." . $field . " = '" . escape($params[$field]) . "'";

		}

	}

	if (isset($params['profile_id'])) {

		$where[] = "email_campaigns.email_campaign_profile_id = '" . (int)$params['profile_id'] . "'";

	}

	if (isset($params['q']) && $params['q'] !== '') {

		$where[] = "email_campaigns.subject LIKE '%" . escape(escape_like($params['q'])) . "%'";

	}

	if (isset($params['created_since'])) {

		$where[] = "email_campaigns.created_timestamp >= '" . (int)$params['created_since'] . "'";

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "email_campaigns.id < '" . (int)$cursor['i'] . "'";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows(api_campaign_select()
		. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)) . "
		ORDER BY email_campaigns.id DESC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	$ids = array();

	foreach ($rows as $row) {

		$ids[] = (int)$row['id'];

	}

	$counts = api_campaign_recipient_counts($ids);

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_campaign_present($row, $counts[(int)$row['id']]);

	}

	api_ok_list($out, $limit, $next);

}

function api_campaigns_get($params) {

	api_campaign_answer($params['id']);

}

// One address the campaign goes to.
function api_campaign_recipient_present($row) {

	if (!empty($row['failed'])) {

		$status = 'failed';

	} else {

		$status = ((int)$row['complete'] === 1) ? 'sent' : 'pending';

	}

	return array(
		'id'         => (int)$row['id'],
		'email'      => (string)$row['email_address'],
		'contact_id' => ((int)$row['contact_id'] > 0) ? (int)$row['contact_id'] : null,
		'status'     => $status,
		'attempts'   => isset($row['attempts']) ? (int)$row['attempts'] : 0,
		// Why the last attempt did not go through, while it is retried or after
		// the job gave up.
		'last_error' => (isset($row['last_error']) && (string)$row['last_error'] !== '') ? (string)$row['last_error'] : null
	);

}

function api_campaign_recipient_schema() {

	return array(
		'id'         => 'integer',
		'email'      => 'string',
		'contact_id' => 'integer?',
		'status'     => 'string',
		'attempts'   => 'integer',
		'last_error' => 'string?'
	);

}

// The condition for one recipient status. A failed recipient is complete as
// well, so sent has to leave it out; before the retry columns exist no
// recipient is failed.
function api_campaign_recipient_status_where($status, $retry) {

	switch ($status) {

		case 'sent':
			return "complete = 1" . ($retry ? " AND failed = 0" : '');

		case 'failed':
			return $retry ? "failed = 1" : "0 = 1";

		case 'pending':
			return "complete = 0";

	}

	return '';

}

// In the order the job sends them.
function api_campaigns_recipients($params) {

	$campaign = api_campaign_load_or_fail($params['id']);

	$retry = pg_email_retry_ready();

	$where = array("email_campaign_id = '" . (int)$campaign['id'] . "'");

	if (isset($params['status'])) {

		$condition = api_campaign_recipient_status_where($params['status'], $retry);

		if ($condition !== '') {

			$where[] = $condition;

		}

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "id > '" . (int)$cursor['i'] . "'";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows("SELECT id, email_address, contact_id, complete"
		. ($retry ? ", failed, attempts, last_error" : '') . "
		FROM email_recipients
		WHERE " . implode(' AND ', $where) . "
		ORDER BY id ASC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_campaign_recipient_present($row);

	}

	api_ok_list($out, $limit, $next);

}

// An empty address is allowed, as on the campaign screen: no reply-to, no
// blind copy, or the site's own sender.
function api_campaign_email_or_fail($value, $field) {

	$value = trim((string)$value);

	if (($value !== '') && !validate_email_address($value)) {

		api_fail_validation(lang('That is not an e-mail address.'), $field);

	}

	return $value;

}

// What the campaign screen lets be changed: its status, its words and its
// sender, its time and its purpose. A sent campaign stays as it went out.
function api_campaigns_update($params) {

	$app = api_current_app();

	$row = api_campaign_load_or_fail($params['id']);

	$id = (int)$row['id'];

	if ((string)$row['status'] === 'complete') {

		api_fail(409, 'campaign_complete', lang('This campaign has been sent, so it can no longer be changed.'));

	}

	$set = array();

	if (isset($params['status'])) {

		$set['status'] = (string)$params['status'];

	}

	if (isset($params['subject'])) {

		if (trim((string)$params['subject']) === '') {

			api_fail_validation(lang(array('string' => '{var:1} is required.', 'vars' => 'subject')), 'subject');

		}

		$set['subject'] = (string)$params['subject'];

	}

	if (isset($params['body'])) {

		if ((string)$row['format'] !== 'plain_text') {

			api_fail(409, 'campaign_body_is_a_page', lang('An HTML campaign sends the page as it was when the campaign was made, so its message cannot be changed.'), 'body');

		}

		$set['body'] = (string)$params['body'];

	}

	if (isset($params['from_name'])) {

		$set['from_name'] = (string)$params['from_name'];

	}

	if (isset($params['from_email'])) {

		$set['from_email_address'] = api_campaign_email_or_fail($params['from_email'], 'from_email');

	}

	if (array_key_exists('reply_to', $params)) {

		$set['reply_email_address'] = api_campaign_email_or_fail($params['reply_to'], 'reply_to');

	}

	if (array_key_exists('bcc', $params)) {

		if ((string)$row['type'] !== 'automatic') {

			api_fail(409, 'campaign_bcc_automatic_only', lang('Only an automatic campaign takes a blind copy address.'), 'bcc');

		}

		$set['bcc_email_address'] = api_campaign_email_or_fail($params['bcc'], 'bcc');

	}

	if (array_key_exists('send_at', $params)) {

		$set['start_time'] = api_campaign_start_time($params['send_at']);

	}

	if (isset($params['purpose'])) {

		$set['purpose'] = (string)$params['purpose'];

	}

	if (empty($set)) {

		api_fail_validation(lang('No writable field was sent.'));

	}

	api_dry_run_stop('updated', 'campaign', array('id' => $id, 'changes' => array_keys($set)));

	$columns = array();

	foreach ($set as $column => $value) {

		$columns[] = $column . " = '" . escape($value) . "'";

	}

	api_exec("UPDATE email_campaigns SET " . implode(', ', $columns) . ",
			last_modified_user_id = '" . (int)$app['owner']['id'] . "', last_modified_timestamp = UNIX_TIMESTAMP()
		WHERE id = '" . $id . "'");

	log_activity(lang(array(
		'string' => 'Campaign (subject: {var:1}) was changed through the API by the application {var:2} (key {var:3}).',
		'vars'   => array(isset($set['subject']) ? $set['subject'] : $row['subject'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_campaign_answer($id);

}

// As on the campaign screens: the campaign, the groups it was for and the
// recipients still queued go together. Whatever was already sent stays sent.
function api_campaigns_delete($params) {

	$app = api_current_app();

	$row = api_campaign_load_or_fail($params['id']);

	$id = (int)$row['id'];

	api_dry_run_stop('deleted', 'campaign', array('id' => $id, 'status' => (string)$row['status']));

	api_exec("DELETE FROM email_campaigns WHERE id = '" . $id . "'");

	api_exec("DELETE FROM contact_groups_email_campaigns_xref WHERE email_campaign_id = '" . $id . "'");

	api_exec("DELETE FROM email_recipients WHERE email_campaign_id = '" . $id . "'");

	log_activity(lang(array(
		'string' => 'Campaign (subject: {var:1}) was deleted through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($row['subject'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_ok(array('id' => $id, 'deleted' => true));

}
