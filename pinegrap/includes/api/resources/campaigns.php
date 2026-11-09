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

function api_campaign_answer($id, $status_code = 200) {

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

	api_ok($out, $status_code);

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

// SMS campaigns (includes/fn/sms_campaigns.php): a text queued once for the
// mobile numbers of chosen contact groups, beside the e-mail campaigns and in
// their own table. Read here as My SMS Campaigns lists them; an account with
// the e-mail right but the user role sees only the campaigns it created, as
// on that screen.

// The SMS campaigns an owner may reach: all from the manager role up, its own
// below that.
function api_sms_campaign_owner_filter($owner) {

	if ((int)$owner['role'] < 3) {

		return '';

	}

	return "sms_campaigns.created_by = '" . (int)$owner['id'] . "'";

}

// One SMS campaign. outbox is what the SMS queue still holds of a queued
// campaign's messages (sent rows are swept after 7 days, failed ones after
// 30), null before it is queued; queued and refused are the lasting counts.
function api_sms_campaign_present($row, $groups, $outbox) {

	$queued = ((string)$row['status'] === 'queued');

	return array(
		'id'                => (int)$row['id'],
		'name'              => (string)$row['name'],
		'body'              => (string)$row['body'],
		// draft (kept, never sent), ready (waiting for send_at), queued (handed
		// to the SMS queue) or cancelled.
		'status'            => (string)$row['status'],
		'send_at'           => api_time($row['start_at']),
		'queued_at'         => api_time($row['queued_at']),
		'queued'            => (int)$row['queued_count'],
		'refused'           => (int)$row['refused_count'],
		'outbox'            => $queued ? array(
			'sent'    => isset($outbox['sent']) ? (int)$outbox['sent'] : 0,
			'waiting' => (isset($outbox['queued']) ? (int)$outbox['queued'] : 0) + (isset($outbox['sending']) ? (int)$outbox['sending'] : 0),
			'failed'  => (isset($outbox['failed']) ? (int)$outbox['failed'] : 0) + (isset($outbox['uncertain']) ? (int)$outbox['uncertain'] : 0)
		) : null,
		'contact_group_ids'          => isset($groups['included']) ? $groups['included'] : array(),
		'excluded_contact_group_ids' => isset($groups['excluded']) ? $groups['excluded'] : array(),
		'created_by'        => ((string)$row['created_username'] !== '') ? (string)$row['created_username'] : null,
		'created_at'        => api_time($row['created_at']),
		'updated_at'        => api_time($row['updated_at'])
	);

}

function api_sms_campaign_schema() {

	return array(
		'id'                => 'integer',
		'name'              => 'string',
		'body'              => 'string',
		'status'            => 'string',
		'send_at'           => 'string?',
		'queued_at'         => 'string?',
		'queued'            => 'integer',
		'refused'           => 'integer',
		'outbox'            => 'SmsCampaignOutbox?',
		'contact_group_ids' => 'integer[]',
		'excluded_contact_group_ids' => 'integer[]',
		'created_by'        => 'string?',
		'created_at'        => 'string?',
		'updated_at'        => 'string?'
	);

}

// What api_sms_campaign_present() gives as outbox.
function api_sms_campaign_outbox_schema() {

	return array(
		'sent'    => 'integer',
		'waiting' => 'integer',
		'failed'  => 'integer'
	);

}

// The campaign id a queue reference belongs to (smscampaign:<id>:<contact>),
// 0 for any other reference. Pure.
function api_sms_campaign_reference_id($reference) {

	if (preg_match('/^smscampaign:(\d+):/', (string)$reference, $match) !== 1) {

		return 0;

	}

	return (int)$match[1];

}

function api_campaigns_sms_list($params) {

	// Not one of the modules functions.php loads: My SMS Campaigns and the
	// general job require it the same way.
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/sms_campaigns.php');

	if (!function_exists('pg_sms_campaigns_ready') || !pg_sms_campaigns_ready()) {

		api_fail(503, 'service_unavailable', lang('The database has not been upgraded yet. Run the software update first.'));

	}

	$where = array();

	$app = api_current_app();

	$owner = api_sms_campaign_owner_filter($app['owner']);

	if ($owner !== '') {

		$where[] = $owner;

	}

	if (isset($params['status'])) {

		$where[] = "sms_campaigns.status = '" . escape($params['status']) . "'";

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "sms_campaigns.id < '" . (int)$cursor['i'] . "'";

	}

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$rows = api_rows("SELECT sms_campaigns.*, created_user.user_username AS created_username
		FROM sms_campaigns
		LEFT JOIN user AS created_user ON created_user.user_id = sms_campaigns.created_by"
		. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)) . "
		ORDER BY sms_campaigns.id DESC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	$ids = array();
	$queued_ids = array();

	foreach ($rows as $row) {

		$ids[] = (int)$row['id'];

		if ((string)$row['status'] === 'queued') {

			$queued_ids[] = (int)$row['id'];

		}

	}

	// The groups and the queue figures of the whole page, one read each.
	$groups = array();

	if ($ids) {

		foreach (api_rows("SELECT sms_campaign_id, contact_group_id, type FROM contact_groups_sms_campaigns_xref
			WHERE sms_campaign_id IN (" . implode(',', $ids) . ")
			ORDER BY contact_group_id ASC") as $group) {

			$groups[(int)$group['sms_campaign_id']][(string)$group['type']][] = (int)$group['contact_group_id'];

		}

	}

	$outbox = array();

	if ($queued_ids) {

		$likes = array();

		foreach ($queued_ids as $queued_id) {

			$likes[] = "reference LIKE 'smscampaign:" . $queued_id . ":%'";

		}

		foreach (api_rows("SELECT SUBSTRING_INDEX(reference, ':', 2) AS campaign, status, COUNT(*) AS number
			FROM sms_outbox
			WHERE " . implode(' OR ', $likes) . "
			GROUP BY campaign, status") as $count) {

			$campaign_id = api_sms_campaign_reference_id($count['campaign'] . ':');

			$outbox[$campaign_id][(string)$count['status']] = (int)$count['number'];

		}

	}

	$out = array();

	foreach ($rows as $row) {

		$key = (int)$row['id'];

		$out[] = api_sms_campaign_present($row, isset($groups[$key]) ? $groups[$key] : array(), isset($outbox[$key]) ? $outbox[$key] : array());

	}

	api_ok_list($out, $limit, $next);

}

// The contact group ids sent in one list, checked against the groups that
// exist and that the owner may reach (validate_contact_group_access(), as on
// the create screen). Returns the ids, or the first one refused as a string.
function api_campaign_group_ids($ids, $owner) {

	$ids = array_values(array_unique(array_map('intval', (array)$ids)));

	if (empty($ids)) {

		return array();

	}

	$known = array();

	foreach (api_rows("SELECT id FROM contact_groups WHERE id IN (" . implode(',', $ids) . ")") as $row) {

		$known[(int)$row['id']] = true;

	}

	foreach ($ids as $id) {

		if (!isset($known[$id]) || !validate_contact_group_access($owner, $id)) {

			return (string)$id;

		}

	}

	return $ids;

}

// A new manual campaign, made as the create screen makes one: the same
// checks, the same recipients picked from the same groups and the same rows
// (includes/fn/email_campaign_create.php). It is kept paused (a draft) until
// POST /campaigns/{id}/schedule releases it, so creating one never sends.
// A plain-text message only: an HTML campaign is a page rendered for the
// panel user who picks it, which an application has no session for.
function api_campaigns_create($params) {

	$app = api_current_app();

	$owner = $app['owner'];

	require_once(PG_FUNCTIONS_DIR . '/includes/forms/email_campaign_form.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_create.php');

	$values = array(
		'subject'             => trim((string)$params['subject']),
		'from_email_address'  => trim((string)$params['from_email']),
		'reply_email_address' => isset($params['reply_to']) ? trim((string)$params['reply_to']) : ''
	);

	$fields = array('subject' => 'subject', 'from_email_address' => 'from_email', 'reply_email_address' => 'reply_to');

	foreach (pg_email_campaign_field_errors($values) as $key => $message) {

		api_fail_validation($message, $fields[$key]);

	}

	$included = api_campaign_group_ids(isset($params['contact_group_ids']) ? $params['contact_group_ids'] : array(), $owner);
	$excluded = api_campaign_group_ids(isset($params['excluded_contact_group_ids']) ? $params['excluded_contact_group_ids'] : array(), $owner);

	foreach (array('contact_group_ids' => $included, 'excluded_contact_group_ids' => $excluded) as $field => $ids) {

		if (!is_array($ids)) {

			api_fail_validation(lang(array('string' => 'There is no contact group {var:1} that you may use.', 'vars' => $ids)), $field);

		}

	}

	$entered = isset($params['email_address']) ? trim((string)$params['email_address']) : '';

	if (($entered !== '') && !validate_email_address($entered)) {

		api_fail_validation(lang('That is not an e-mail address.'), 'email_address');

	}

	if (empty($included) && ($entered === '')) {

		api_fail_validation(lang('Please select at least one recipient.'), 'contact_group_ids');

	}

	$groups = array();

	foreach ($excluded as $id) { $groups[$id] = 'excluded'; }
	foreach ($included as $id) { $groups[$id] = 'included'; }

	api_dry_run_stop('created', 'campaign', array('contact_group_ids' => $included, 'excluded_contact_group_ids' => $excluded));

	$picked = pg_email_campaign_pick_recipients($owner, (string)$params['purpose'], $groups, $entered);

	if ($picked === false) {

		api_fail(500, 'server_error', lang('The campaign could not be saved.'));

	}

	if (empty($picked['recipients'])) {

		api_fail_validation(lang('The contact groups chosen hold no address this campaign may go to.'), 'contact_group_ids');

	}

	$id = pg_email_campaign_insert(array(
		'from_name'           => isset($params['from_name']) ? (string)$params['from_name'] : '',
		'from_email_address'  => $values['from_email_address'],
		'reply_email_address' => $values['reply_email_address'],
		'subject'             => $values['subject'],
		'format'              => 'plain_text',
		'body'                => (string)$params['body'],
		'page_id'             => '',
		'start_time'          => api_campaign_start_time(isset($params['send_at']) ? $params['send_at'] : ''),
		'purpose'             => (string)$params['purpose'],
		'status'              => 'paused'
	), $picked, (int)$owner['id']);

	if (!$id) {

		api_fail(500, 'server_error', lang('The campaign could not be saved.'));

	}

	log_activity(lang(array(
		'string' => 'Campaign (subject: {var:1}) was created through the API by the application {var:2} (key {var:3}).',
		'vars'   => array($values['subject'], $app['name'], $app['api_key'])
	)), $owner['username']);

	api_campaign_answer($id, 201);

}

// Releases a draft (paused) campaign for the e-mail job, at send_at or as
// soon as the job runs. Nothing is sent by this call.
function api_campaigns_schedule($params) {

	$app = api_current_app();

	$row = api_campaign_load_or_fail($params['id']);

	$id = (int)$row['id'];

	if ((string)$row['status'] !== 'paused') {

		api_fail(409, 'campaign_not_paused', lang('Only a paused campaign can be scheduled.'));

	}

	$start_time = api_campaign_start_time(array_key_exists('send_at', $params) ? $params['send_at'] : '');

	api_dry_run_stop('updated', 'campaign', array('id' => $id, 'status' => 'ready', 'start_time' => $start_time));

	api_exec("UPDATE email_campaigns SET status = 'ready', start_time = '" . escape($start_time) . "',
			last_modified_user_id = '" . (int)$app['owner']['id'] . "', last_modified_timestamp = UNIX_TIMESTAMP()
		WHERE id = '" . $id . "' AND status = 'paused'");

	log_activity(lang(array(
		'string' => 'Campaign (subject: {var:1}) was scheduled through the API by the application {var:2} (key {var:3}).',
		'vars'   => array((string)$row['subject'], $app['name'], $app['api_key'])
	)), $app['owner']['username']);

	api_campaign_answer($id);

}
